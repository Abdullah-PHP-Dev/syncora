<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\GoogleAdBuilderRequest;
use App\Services\AdServices\GoogleCampaignBuilder as B;
use App\Support\Ads\GoogleAdsApiException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * JSON endpoints behind the Google campaign builder
 * (GoogleCampaignBuilder.vue). Everything is scoped to the signed-in
 * seller's selected Google Ads customer; the OAuth tokens stay on the
 * server.
 */
class GoogleAdBuilderController extends Controller
{
    private ?B $builder = null;

    private function builder(): B
    {
        $this->builder ??= B::forUser((int) Auth::id());
        abort_unless($this->builder, 409, __('google_builder.server.connect_first'));

        return $this->builder;
    }

    /** Runs a lookup; Google errors become a readable 422. */
    private function respond(callable $call): JsonResponse
    {
        try {
            return response()->json(['success' => true, 'data' => $call($this->builder())]);
        } catch (GoogleAdsApiException|\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'reconnect' => $e instanceof GoogleAdsApiException && $e->isAuthError()], 422);
        }
    }

    public function bootstrap(): JsonResponse
    {
        return $this->respond(fn ($b) => $b->bootstrap());
    }

    public function locations(Request $request): JsonResponse
    {
        $request->validate(['q' => ['required', 'string', 'min:2', 'max:100']]);

        return $this->respond(fn ($b) => $b->locations($request->string('q')->value(), app()->getLocale() === 'ar' ? 'ar' : 'en'));
    }

    public function languages(): JsonResponse
    {
        return $this->respond(fn ($b) => $b->languages());
    }

    public function audiences(): JsonResponse
    {
        return $this->respond(fn ($b) => $b->audiences());
    }

    public function interests(): JsonResponse
    {
        return $this->respond(fn ($b) => $b->interests());
    }

    public function topics(): JsonResponse
    {
        return $this->respond(fn ($b) => $b->topics());
    }

    /** Operating systems, carriers, app categories, Performance Max audiences. */
    public function options(string $type): JsonResponse
    {
        abort_unless(in_array($type, B::OPTION_TYPES, true), 404);

        return $this->respond(fn ($b) => $b->optionList($type));
    }

    public function keywordIdeas(Request $request): JsonResponse
    {
        $request->validate([
            'keywords' => ['nullable', 'array', 'max:20'],
            'keywords.*' => ['string', 'max:80'],
            'url' => ['nullable', 'url', 'max:2048', 'required_without:keywords'],
            'locations' => ['nullable', 'array', 'max:10'],
            'locations.*' => ['regex:/^\d+$/'],
            'language' => ['nullable', 'regex:/^\d+$/'],
        ]);

        return $this->respond(fn ($b) => $b->keywordIdeas((array) $request->input('keywords', []), $request->input('url'), (array) $request->input('locations', []), $request->input('language')));
    }

    /** Edit mode: name, status, dates, budget amount and ad copy of an existing campaign. */
    public function update(Request $request, \App\Models\Admin\AdCampaign $campaign): JsonResponse
    {
        $builder = $this->builder();
        abort_unless(in_array($campaign->platform, ['google', 'youtube'], true), 404);
        if ((int) $campaign->social_account_id !== (int) $builder->api()->account->id) {
            return response()->json(['success' => false, 'message' => __('google_builder.server.other_account')], 409);
        }
        $campaign->load(['adGroups.creatives', 'ads']);
        $type = B::editData($campaign)['campaign_type'];
        $limits = B::LIMITS[B::limitKey($type)];
        $copy = $type !== 'PERFORMANCE_MAX';
        $text = fn (string $key) => $copy && isset($limits[$key]) ? ['required', 'array', 'min:' . $limits[$key][0], 'max:' . $limits[$key][1]] : ['nullable', 'array'];

        $data = $request->validate([
            'status' => ['required', 'in:PAUSED,ACTIVE'],
            'campaign.name' => ['required', 'string', 'max:255'],
            'campaign.budget' => ['required', 'numeric', 'min:' . B::MIN_DAILY_BUDGET, 'max:10000000'],
            'campaign.start_date' => ['nullable', 'date_format:Y-m-d'],
            'campaign.end_date' => [$campaign->budget_mode === 'total' ? 'required' : 'nullable', 'date_format:Y-m-d', 'after_or_equal:today'],
            'ad.final_url' => ['required', 'url', 'starts_with:http://,https://', 'max:2048'],
            'ad.headlines' => $text('headlines'),
            'ad.headlines.*' => ['string', 'distinct:ignore_case', 'max:' . ($limits['headlines'][2] ?? 30)],
            'ad.descriptions' => $text('descriptions'),
            'ad.descriptions.*' => ['string', 'distinct:ignore_case', 'max:' . ($limits['descriptions'][2] ?? 90)],
            'ad.long_headlines' => $text('long_headlines'),
            'ad.long_headlines.*' => ['string', 'max:90'],
            'ad.business_name' => [$copy && isset($limits['business_name']) ? 'required' : 'nullable', 'string', 'max:25'],
        ]);
        if (! empty($data['campaign']['start_date']) && ! empty($data['campaign']['end_date']) && $data['campaign']['end_date'] < $data['campaign']['start_date']) {
            return response()->json(['success' => false, 'message' => __('google_builder.server.end_before_start')], 422);
        }

        try {
            $result = $builder->updateCampaign($campaign, $data);
        } catch (GoogleAdsApiException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'reconnect' => $e->isAuthError()], 422);
        }
        $result['redirect_url'] = route('admin.ads.campaigns.index', ['platform' => $campaign->platform]);

        return response()->json($result);
    }
    public function submit(GoogleAdBuilderRequest $request): JsonResponse
    {
        $result = $this->builder()->submit($request->builderInput(), $request->string('idempotency_key')->value());
        $status = $result['success'] ? 200 : ($result['status'] ?? 422);

        if ($result['success']) {
            $result['redirect_url'] = route('admin.ads.campaigns.index', ['platform' => $request->input('campaign_type') === 'DEMAND_GEN' ? 'youtube' : 'google']);
        }

        return response()->json($result, $status);
    }
}
