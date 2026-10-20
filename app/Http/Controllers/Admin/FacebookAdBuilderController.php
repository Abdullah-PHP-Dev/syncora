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

        // Facebook or Instagram page (same Meta ad account, different rules).
        return $builder->forChannel(request()->input('channel'));
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

    /** Job titles / employers / schools / fields of study (Meta search). */
    public function workSearch(Request $request): JsonResponse
    {
        $request->validate(['q' => ['required', 'string', 'min:2', 'max:100'], 'type' => ['required', 'in:' . implode(',', array_keys(FacebookCampaignBuilder::WORK_SEARCH))]]);

        return $this->respond(fn ($b) => $b->workSearch($request->string('q')->value(), $request->string('type')->value()));
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

    /** Edit mode: campaign, ad set (budget, schedule, audience, placements) and ad copy. */
    public function update(Request $request, \App\Models\Admin\AdCampaign $campaign): JsonResponse
    {
        $builder = $this->builder();
        abort_unless(in_array($campaign->platform, ['facebook', 'instagram'], true), 404);
        if ((int) $campaign->social_account_id !== (int) $builder->graph()->account->id) {
            return response()->json(['success' => false, 'message' => __('ads_builder.server.other_account')], 409);
        }
        $campaign->load(['adGroups.creatives', 'ads']);

        $data = $request->validate([
            'status' => ['required', 'in:PAUSED,ACTIVE'],
            'campaign.name' => ['required', 'string', 'max:400'],
            'campaign.budget' => ['nullable', 'numeric', 'min:1'],
            'adset' => ['nullable', 'array'],
            'adset.name' => ['nullable', 'string', 'max:400'],
            'adset.budget' => ['nullable', 'numeric', 'min:1'],
            'adset.start_time' => ['nullable', 'date'],
            'adset.end_time' => ['nullable', 'date', 'after:now'],
            'targeting' => ['nullable', 'array'],
            'targeting.locations' => ['nullable', 'array', 'max:1000'],
            'targeting.age_min' => ['nullable', 'integer', 'between:13,65'],
            'targeting.age_max' => ['nullable', 'integer', 'between:13,65', 'gte:targeting.age_min'],
            'placements' => ['nullable', 'array'],
            'placements.mode' => ['nullable', 'in:advantage,manual'],
            'creative' => ['nullable', 'array'],
            'creative.primary_text' => ['nullable', 'string', 'max:2200'],
            'creative.headline' => ['nullable', 'string', 'max:255'],
            'creative.link' => ['nullable', 'url', 'max:2048'],
            'creative.cta' => ['nullable', 'string', 'max:40'],
            'ad' => ['nullable', 'array'],
            'ad.name' => ['nullable', 'string', 'max:400'],
        ]);
        // Targeting keeps every builder key (interests, narrow, audiences...), not only the validated ones.
        $data['targeting'] = $request->input('targeting');
        $data['placements'] = $request->input('placements');
        $group = $campaign->adGroups->first();
        if ($group?->budget_mode === 'lifetime_budget' && empty($data['adset']['end_time'])) {
            return response()->json(['success' => false, 'message' => __('ads_builder.server.lifetime_end')], 422);
        }

        try {
            $result = $builder->updateCampaign($campaign, $data);
        } catch (MetaApiException|\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
        $result['redirect_url'] = route('admin.ads.campaigns.index', ['platform' => $campaign->platform]);

        return response()->json($result);
    }
    public function submit(FacebookAdBuilderRequest $request): JsonResponse
    {
        $result = $this->builder()->submit($request->builderInput(), $request->string('idempotency_key')->value());
        $status = $result['success'] ? 200 : ($result['status'] ?? 422);

        if ($result['success']) {
            $result['redirect_url'] = route('admin.ads.campaigns.index', ['platform' => $this->builder()->channel()]);
        }

        return response()->json($result, $status);
    }
}
