<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\FacebookAdBuilderRequest;
use App\Services\AdServices\FacebookCampaignBuilder;
use App\Support\Ads\MetaApiException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * JSON endpoints behind the Facebook campaign builder (FacebookCampaignBuilder).
 * Everything is scoped to the signed-in seller's selected ad account; the
 * Meta token stays on the server.
 */
class FacebookAdBuilderController extends Controller
{
    private function builder(): FacebookCampaignBuilder
    {
        $builder = FacebookCampaignBuilder::forUser((int) Auth::id());
        abort_unless($builder, 409, __('ads_builder.server.connect_first'));

        return $builder;
    }

    /** Runs a lookup; Meta errors become a readable 422. */
    private function respond(callable $call): JsonResponse
    {
        try {
            return response()->json(['success' => true, 'data' => $call($this->builder())]);
        } catch (MetaApiException|\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
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

    public function adSets(string $campaign): JsonResponse
    {
        return $this->respond(fn ($b) => $b->adSets($campaign));
    }

    public function creatives(Request $request): JsonResponse
    {
        return $this->respond(fn ($b) => $b->creatives($request->string('q')->trim()->value() ?: null));
    }

    public function creativePreview(Request $request, string $creative): JsonResponse
    {
        $format = in_array($request->query('format'), ['MOBILE_FEED_STANDARD', 'DESKTOP_FEED_STANDARD', 'INSTAGRAM_STANDARD', 'INSTAGRAM_STORY'], true)
            ? $request->query('format') : 'MOBILE_FEED_STANDARD';

        return $this->respond(fn ($b) => ['html' => $b->creativePreview($creative, $format)]);
    }

    public function audiences(): JsonResponse
    {
        return $this->respond(fn ($b) => $b->audiences());
    }

    public function pixels(): JsonResponse
    {
        return $this->respond(fn ($b) => $b->pixels());
    }

    public function apps(): JsonResponse
    {
        return $this->respond(fn ($b) => $b->apps());
    }

    public function instagramAccounts(): JsonResponse
    {
        return $this->respond(fn ($b) => $b->instagramAccounts());
    }

    public function leadForms(string $page): JsonResponse
    {
        return $this->respond(fn ($b) => $b->leadForms($page));
    }

    public function targetingSearch(Request $request): JsonResponse
    {
        $request->validate(['q' => ['required', 'string', 'min:2', 'max:100'], 'type' => ['nullable', 'in:interests,behaviors,demographics,life_events,industries,income,education_statuses,family_statuses,relationship_statuses']]);

        return $this->respond(fn ($b) => $b->targetingSearch($request->string('q')->value(), $request->query('type')));
    }

    public function locations(Request $request): JsonResponse
    {
        $request->validate(['q' => ['required', 'string', 'min:2', 'max:100']]);

        return $this->respond(fn ($b) => $b->locations($request->string('q')->value()));
    }

    public function locales(Request $request): JsonResponse
    {
        $request->validate(['q' => ['required', 'string', 'min:1', 'max:60']]);

        return $this->respond(fn ($b) => $b->locales($request->string('q')->value()));
    }

    public function estimate(Request $request): JsonResponse
    {
        return $this->respond(fn ($b) => $b->estimate($request->all()));
    }

    public function submit(FacebookAdBuilderRequest $request): JsonResponse
    {
        $result = $this->builder()->submit($request->builderInput(), $request->string('idempotency_key')->value());
        $status = $result['success'] ? 200 : ($result['status'] ?? 422);

        if ($result['success']) {
            $result['redirect_url'] = route('admin.ads.campaigns.index', ['platform' => 'facebook']);
        }

        return response()->json($result, $status);
    }
}
