<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TiktokAdBuilderRequest;
use App\Services\AdServices\TiktokCampaignBuilder;
use App\Support\Ads\TiktokApiException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * JSON endpoints behind the TikTok campaign builder (TiktokCampaignBuilder).
 * Everything is scoped to the signed-in seller's selected TikTok ad
 * account; the TikTok token stays on the server.
 */
class TiktokAdBuilderController extends Controller
{
    private ?TiktokCampaignBuilder $builder = null;

    private function builder(): TiktokCampaignBuilder
    {
        $this->builder ??= TiktokCampaignBuilder::forUser((int) Auth::id());
        abort_unless($this->builder, 409, __('tiktok_builder.server.connect_first'));

        return $this->builder;
    }

    /** Runs a lookup; TikTok errors become a readable 422. */
    private function respond(callable $call): JsonResponse
    {
        try {
            return response()->json(['success' => true, 'data' => $call($this->builder())]);
        } catch (TiktokApiException|\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'reconnect' => $e instanceof TiktokApiException && $e->isAuthError()], 422);
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

    public function sparkPosts(Request $request): JsonResponse
    {
        $request->validate(['identity_id' => ['required', 'string', 'max:80'], 'identity_type' => ['required', 'in:TT_USER,BC_AUTH_TT,AUTH_CODE'], 'cursor' => ['nullable', 'string', 'max:40']]);

        return $this->respond(fn ($b) => $b->sparkPosts($request->string('identity_id')->value(), $request->string('identity_type')->value(), null, $request->query('cursor')));
    }

    public function pixels(): JsonResponse
    {
        return $this->respond(fn ($b) => $b->pixels());
    }

    public function audiences(): JsonResponse
    {
        return $this->respond(fn ($b) => $b->audiences());
    }

    public function apps(): JsonResponse
    {
        return $this->respond(fn ($b) => $b->apps());
    }

    public function leadForms(): JsonResponse
    {
        return $this->respond(fn ($b) => $b->leadForms());
    }

    public function locations(Request $request): JsonResponse
    {
        $request->validate(['q' => ['required', 'string', 'min:2', 'max:100'], 'objective' => ['nullable', 'string', 'max:40']]);

        return $this->respond(fn ($b) => $b->locations($request->string('q')->value(), $request->string('objective')->value()));
    }

    public function languages(): JsonResponse
    {
        return $this->respond(fn ($b) => $b->languages());
    }

    public function interests(Request $request): JsonResponse
    {
        $request->validate(['q' => ['required', 'string', 'min:2', 'max:100'], 'types' => ['nullable', 'array'], 'types.*' => ['in:' . implode(',', TiktokCampaignBuilder::INTEREST_TYPES)]]);

        return $this->respond(fn ($b) => $b->interests($request->string('q')->value(), (array) $request->query('types', [])));
    }

    public function deviceModels(Request $request): JsonResponse
    {
        $request->validate(['q' => ['required', 'string', 'min:2', 'max:60']]);

        return $this->respond(fn ($b) => $b->deviceModels($request->string('q')->value()));
    }

    public function osVersions(Request $request): JsonResponse
    {
        $request->validate(['os' => ['required', 'in:ANDROID,IOS']]);

        return $this->respond(fn ($b) => $b->osVersions($request->string('os')->value()));
    }

    public function submit(TiktokAdBuilderRequest $request): JsonResponse
    {
        $result = $this->builder()->submit($request->builderInput(), $request->string('idempotency_key')->value());
        $status = $result['success'] ? 200 : ($result['status'] ?? 422);

        if ($result['success']) {
            $result['redirect_url'] = route('admin.ads.campaigns.index', ['platform' => 'tiktok']);
        }

        return response()->json($result, $status);
    }
}
