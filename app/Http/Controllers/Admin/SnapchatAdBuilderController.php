<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SnapchatAdBuilderRequest;
use App\Services\AdServices\SnapchatCampaignBuilder as B;
use App\Support\Ads\SnapchatApiException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * JSON endpoints behind the Snapchat campaign builder
 * (SnapchatCampaignBuilder.vue). Everything is scoped to the signed-in
 * seller's selected Snapchat ad account; the OAuth tokens stay on the
 * server.
 */
class SnapchatAdBuilderController extends Controller
{
    private ?B $builder = null;

    private function builder(): B
    {
        $this->builder ??= B::forUser((int) Auth::id());
        abort_unless($this->builder, 409, __('snapchat_builder.server.connect_first'));

        return $this->builder;
    }

    /** Runs a lookup; Snap errors become a readable 422. */
    private function respond(callable $call): JsonResponse
    {
        try {
            return response()->json(['success' => true, 'data' => $call($this->builder())]);
        } catch (SnapchatApiException|\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'reconnect' => $e instanceof SnapchatApiException && $e->isAuthError()], 422);
        }
    }

    public function bootstrap(): JsonResponse
    {
        return $this->respond(fn ($b) => $b->bootstrap());
    }

    public function campaigns(): JsonResponse
    {
        return $this->respond(fn ($b) => $b->campaigns());
    }

    public function audiences(): JsonResponse
    {
        return $this->respond(fn ($b) => $b->audiences());
    }

    public function countries(): JsonResponse
    {
        return $this->respond(fn ($b) => $b->countries());
    }

    public function regions(Request $request): JsonResponse
    {
        $request->validate(['country_code' => ['required', 'alpha', 'size:2']]);

        return $this->respond(fn ($b) => $b->regions($request->string('country_code')->value()));
    }

    public function languages(): JsonResponse
    {
        return $this->respond(fn ($b) => $b->languages());
    }

    public function advancedDemographics(): JsonResponse
    {
        return $this->respond(fn ($b) => $b->advancedDemographics());
    }

    public function interests(Request $request): JsonResponse
    {
        $request->validate(['country_code' => ['required', 'alpha', 'size:2']]);

        return $this->respond(fn ($b) => $b->interests($request->string('country_code')->value()));
    }

    public function osVersions(Request $request): JsonResponse
    {
        $request->validate(['os' => ['required', 'in:iOS,ANDROID']]);

        return $this->respond(fn ($b) => $b->osVersions($request->string('os')->value()));
    }

    public function connectionTypes(): JsonResponse
    {
        return $this->respond(fn ($b) => $b->connectionTypes());
    }

    public function estimate(Request $request): JsonResponse
    {
        $request->validate([
            'targeting' => ['required', 'array'],
            'adsquad' => ['required', 'array'],
            'adsquad.goal' => ['required', Rule::in(collect(B::OBJECTIVES)->pluck('destinations')->flatten()->unique()->all())],
            'adsquad.bid_strategy' => ['nullable', Rule::in(B::BID_STRATEGIES)],
            'adsquad.bid_amount' => ['nullable', 'numeric', 'min:0.01'],
            'adsquad.budget_type' => ['nullable', 'in:daily,lifetime'],
            'adsquad.budget' => ['nullable', 'numeric', 'min:' . B::MIN_SQUAD_DAILY],
            'adsquad.placement_mode' => ['nullable', 'in:auto,custom'],
            'adsquad.positions' => ['nullable', 'array'],
            'adsquad.positions.*' => [Rule::in(B::POSITIONS)],
            ...(new SnapchatAdBuilderRequest())->targetingRules('targeting'),
        ]);

        return $this->respond(fn ($b) => $b->estimate($request->input('targeting'), $request->input('adsquad')));
    }

    public function bidEstimate(Request $request): JsonResponse
    {
        $request->validate([
            'goal' => ['required', Rule::in(collect(B::OBJECTIVES)->pluck('destinations')->flatten()->unique()->all())],
            'targeting' => ['required', 'array'],
            ...(new SnapchatAdBuilderRequest())->targetingRules('targeting'),
        ]);

        return $this->respond(fn ($b) => $b->bidEstimate($request->input('targeting'), $request->string('goal')->value()));
    }

    public function submit(SnapchatAdBuilderRequest $request): JsonResponse
    {
        $result = $this->builder()->submit($request->builderInput(), $request->string('idempotency_key')->value());
        $status = $result['success'] ? 200 : ($result['status'] ?? 422);

        if ($result['success']) {
            $result['redirect_url'] = route('admin.ads.campaigns.index', ['platform' => 'snapchat']);
        }

        return response()->json($result, $status);
    }
}
