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

    public function submit(GoogleAdBuilderRequest $request): JsonResponse
    {
        $result = $this->builder()->submit($request->builderInput(), $request->string('idempotency_key')->value());
        $status = $result['success'] ? 200 : ($result['status'] ?? 422);

        if ($result['success']) {
            $result['redirect_url'] = route('admin.ads.campaigns.index', ['platform' => 'google']);
        }

        return response()->json($result, $status);
    }
}
