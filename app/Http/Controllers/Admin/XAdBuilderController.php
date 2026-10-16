<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\XAdBuilderRequest;
use App\Services\AdServices\XCampaignBuilder as B;
use App\Support\Ads\XApiException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * JSON endpoints behind the X campaign builder (XCampaignBuilder.vue).
 * Everything is scoped to the signed-in seller's selected X ad account;
 * the OAuth 1.0a token and secret stay on the server.
 */
class XAdBuilderController extends Controller
{
    private ?B $builder = null;

    private function builder(): B
    {
        $this->builder ??= B::forUser((int) Auth::id());
        abort_unless($this->builder, 409, __('x_builder.server.connect_first'));

        return $this->builder;
    }

    /** Runs a lookup; X errors become a readable 422. */
    private function respond(callable $call): JsonResponse
    {
        try {
            return response()->json(['success' => true, 'data' => $call($this->builder())]);
        } catch (XApiException|\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'reconnect' => $e instanceof XApiException && $e->isAuthError()], 422);
        }
    }

    public function bootstrap(): JsonResponse
    {
        return $this->respond(fn ($b) => $b->bootstrap());
    }

    public function campaigns(Request $request): JsonResponse
    {
        return $this->respond(fn ($b) => $b->campaigns($request->string('q')->trim()->value() ?: null));
    }

    public function tweets(Request $request): JsonResponse
    {
        $request->validate(['user_id' => ['required', 'regex:/^\d{1,25}$/'], 'cursor' => ['nullable', 'string', 'max:100']]);

        return $this->respond(fn ($b) => $b->tweets($request->string('user_id')->value(), $request->query('cursor')));
    }

    public function audiences(): JsonResponse
    {
        return $this->respond(fn ($b) => $b->audiences());
    }

    public function locations(Request $request): JsonResponse
    {
        $request->validate(['q' => ['required', 'string', 'min:2', 'max:100'], 'type' => ['nullable', 'in:COUNTRIES,REGIONS,METROS,CITIES,POSTAL_CODES'], 'country_code' => ['nullable', 'alpha', 'size:2']]);

        return $this->respond(fn ($b) => $b->locations($request->string('q')->value(), $request->query('type'), $request->query('country_code')));
    }

    public function languages(): JsonResponse
    {
        return $this->respond(fn ($b) => $b->languages());
    }

    public function interests(Request $request): JsonResponse
    {
        $request->validate(['q' => ['nullable', 'string', 'max:100']]);

        return $this->respond(fn ($b) => $b->interests((string) $request->query('q', '')));
    }

    public function conversations(Request $request): JsonResponse
    {
        $request->validate(['q' => ['nullable', 'string', 'max:100'], 'type' => ['nullable', Rule::in(B::CONVERSATION_TYPES)]]);

        return $this->respond(fn ($b) => $b->conversations((string) $request->query('q', ''), $request->query('type')));
    }

    public function events(Request $request): JsonResponse
    {
        $request->validate(['type' => ['required', Rule::in(B::EVENT_TYPES)], 'country_code' => ['nullable', 'alpha', 'size:2']]);

        return $this->respond(fn ($b) => $b->events($request->string('type')->value(), $request->query('country_code')));
    }

    public function platforms(): JsonResponse
    {
        return $this->respond(fn ($b) => ['platforms' => $b->platforms(), 'versions' => $b->platformVersions()]);
    }

    public function devices(Request $request): JsonResponse
    {
        $request->validate(['q' => ['required', 'string', 'min:2', 'max:60']]);

        return $this->respond(fn ($b) => $b->devices($request->string('q')->value()));
    }

    public function networkOperators(Request $request): JsonResponse
    {
        $request->validate(['country_code' => ['required', 'alpha', 'size:2'], 'q' => ['nullable', 'string', 'max:60']]);

        return $this->respond(fn ($b) => $b->networkOperators(strtoupper($request->string('country_code')->value()), $request->query('q')));
    }

    public function appCategories(Request $request): JsonResponse
    {
        $request->validate(['q' => ['required', 'string', 'min:2', 'max:60'], 'os' => ['nullable', 'in:IOS,ANDROID']]);

        return $this->respond(fn ($b) => $b->appStoreCategories($request->string('q')->value(), $request->query('os')));
    }

    public function users(Request $request): JsonResponse
    {
        $request->validate(['handles' => ['required', 'array', 'min:1', 'max:100'], 'handles.*' => ['string', 'max:20']]);

        return $this->respond(fn ($b) => $b->users($request->input('handles')));
    }

    public function estimate(Request $request): JsonResponse
    {
        $request->validate(['objective' => ['required', Rule::in(array_keys(B::OBJECTIVES))], 'targeting' => ['required', 'array']]);
        $targeting = (new XAdBuilderRequest())->targetingRules('targeting');
        $request->validate($targeting);

        return $this->respond(fn ($b) => $b->estimate($request->input('targeting'), $request->string('objective')->value()));
    }

    public function submit(XAdBuilderRequest $request): JsonResponse
    {
        $result = $this->builder()->submit($request->builderInput(), $request->string('idempotency_key')->value());
        $status = $result['success'] ? 200 : ($result['status'] ?? 422);

        if ($result['success']) {
            $result['redirect_url'] = route('admin.ads.campaigns.index', ['platform' => 'x']);
        }

        return response()->json($result, $status);
    }
}
