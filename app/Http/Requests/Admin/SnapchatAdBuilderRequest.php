<?php

namespace App\Http\Requests\Admin;

use App\Services\AdServices\SnapchatCampaignBuilder as B;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Shape of a Snapchat campaign-builder submission
 * (SnapchatCampaignBuilder::submit()): objective + destination, campaign
 * (new / existing), ad squad with targeting, app details, ad creative.
 * Ownership of every id is checked against Snap by the builder itself.
 */
class SnapchatAdBuilderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    public function rules(): array
    {
        $objective = (string) $this->input('objective');
        $destinations = B::OBJECTIVES[$objective]['destinations'] ?? [];
        $destination = (string) $this->input('destination');
        $goals = $destinations[$destination] ?? [];
        $newCampaign = $this->input('campaign.mode') === 'new';
        $bid = $this->input('adsquad.bid_strategy');
        $lifetime = $this->input('adsquad.budget_type') === 'lifetime';
        $isApp = in_array($destination, ['app_install', 'deep_link'], true);

        return [
            'idempotency_key' => ['required', 'string', 'min:16', 'max:64', 'regex:/^[A-Za-z0-9-]+$/'],
            'objective' => ['required', Rule::in(array_keys(B::OBJECTIVES))],
            'destination' => ['required', Rule::in(array_keys($destinations))],
            'status' => ['required', 'in:ACTIVE,PAUSED'],

            'campaign.mode' => ['required', 'in:new,existing'],
            'campaign.id' => [Rule::requiredIf(! $newCampaign), 'nullable', 'uuid'],
            'campaign.name' => ['nullable', 'string', 'max:375'],
            'campaign.daily_cap' => ['nullable', 'numeric', 'min:' . B::MIN_CAMPAIGN_CAP, 'max:100000000'],
            'campaign.lifetime_cap' => ['nullable', 'numeric', 'min:' . B::MIN_CAMPAIGN_CAP, 'max:1000000000'],

            'adsquad.name' => ['nullable', 'string', 'max:375'],
            'adsquad.goal' => ['required', Rule::in($goals)],
            'adsquad.bid_strategy' => ['required', Rule::in(B::BID_STRATEGIES)],
            'adsquad.bid_amount' => [Rule::requiredIf($bid !== 'AUTO_BID'), 'nullable', 'numeric', 'min:0.01', 'max:100000'],
            'adsquad.pacing' => ['nullable', 'in:STANDARD,ACCELERATED'],
            'adsquad.budget_type' => ['required', 'in:daily,lifetime'],
            'adsquad.budget' => ['required', 'numeric', 'min:' . B::MIN_SQUAD_DAILY, 'max:1000000000'],
            'adsquad.start_time' => ['nullable', 'date'],
            'adsquad.end_time' => [Rule::requiredIf($lifetime), 'nullable', 'date', 'after:adsquad.start_time'],
            'adsquad.placement_mode' => ['required', 'in:auto,custom'],
            'adsquad.positions' => ['nullable', 'array', 'required_if:adsquad.placement_mode,custom'],
            'adsquad.positions.*' => [Rule::in(B::POSITIONS)],
            'adsquad.content_mode' => ['nullable', 'in:include,exclude'],
            'adsquad.content_types' => ['nullable', 'array', 'max:11'],
            'adsquad.content_types.*' => [Rule::in(B::CONTENT_TYPES)],
            'adsquad.pixel_id' => [Rule::requiredIf(in_array($this->input('adsquad.goal'), B::PIXEL_GOALS, true)), 'nullable', 'uuid'],
            'adsquad.conversion_window' => ['nullable', Rule::in(B::CONVERSION_WINDOWS)],
            'adsquad.frequency_cap' => ['nullable', 'integer', 'between:1,100'],
            'adsquad.frequency_interval' => ['nullable', 'integer', 'min:1', $this->input('adsquad.frequency_unit') === 'HOURS' ? 'max:720' : 'max:30'],
            'adsquad.frequency_unit' => ['nullable', Rule::in(B::FREQUENCY_UNITS)],
            'adsquad.schedule' => ['nullable', 'array'],
            'adsquad.schedule.*' => ['array', 'max:24'],
            'adsquad.schedule.*.*' => ['integer', 'between:0,23'],
            ...$this->targetingRules('adsquad.targeting'),

            'app.app_name' => [Rule::requiredIf($isApp), 'nullable', 'string', 'max:30'],
            'app.ios_app_id' => [Rule::requiredIf($isApp && ! $this->filled('app.android_app_url')), 'nullable', 'regex:/^\d{5,12}$/'],
            'app.android_app_url' => [Rule::requiredIf($isApp && ! $this->filled('app.ios_app_id')), 'nullable', 'regex:/^[A-Za-z][A-Za-z0-9_]*(\.[A-Za-z0-9_]+)+$/', 'max:150'],
            'app.icon_media_id' => [Rule::requiredIf($isApp), 'nullable', 'integer'],
            'app.deep_link_uri' => [Rule::requiredIf($destination === 'deep_link'), 'nullable', 'string', 'max:2048', 'regex:/^[A-Za-z][A-Za-z0-9+.-]*:\/\/\S+$/'],
            'app.web_fallback_url' => ['nullable', 'url:https', 'max:2048'],

            'ad.name' => ['nullable', 'string', 'max:300'],
            'ad.profile_id' => ['required', 'uuid'],
            'ad.headline' => ['required', 'string', 'max:34'],
            'ad.brand_name' => ['nullable', 'string', 'max:32'],
            'ad.media_ids' => ['required', 'array', 'min:1', 'max:' . B::MAX_ADS],
            'ad.media_ids.*' => ['integer', 'distinct'],
            'ad.cta' => [Rule::requiredIf($destination !== 'none'), 'nullable', Rule::in(B::CTAS[$destination] ?? [])],
            'ad.url' => [Rule::requiredIf($destination === 'web'), 'nullable', 'url:https', 'max:2048'],
            'ad.shareable' => ['nullable', 'boolean'],
            'ad.block_preload' => ['nullable', 'boolean'],
        ];
    }

    /** Targeting rules, shared with the audience-size and bid estimate endpoints. */
    public function targetingRules(string $p): array
    {
        return [
            "{$p}.countries" => ['required', 'array', 'min:1', 'max:100'],
            "{$p}.countries.*" => ['string', 'alpha', 'size:2'],
            "{$p}.regions" => ['nullable', 'array', 'max:500'],
            "{$p}.regions.*.id" => ['required', 'string', 'max:20'],
            "{$p}.regions.*.country" => ['required', 'alpha', 'size:2'],
            "{$p}.excluded_regions" => ['nullable', 'array', 'max:500'],
            "{$p}.excluded_regions.*.id" => ['required', 'string', 'max:20'],
            "{$p}.excluded_regions.*.country" => ['required', 'alpha', 'size:2'],
            "{$p}.postal_codes" => ['nullable', 'array', 'max:2000'],
            "{$p}.postal_codes.*.code" => ['required', 'string', 'max:12', 'regex:/^[A-Za-z0-9 -]+$/'],
            "{$p}.postal_codes.*.country" => ['required', 'alpha', 'size:2'],
            "{$p}.metros" => ['nullable', 'array', 'max:500'],
            "{$p}.metros.*.id" => ['required', 'string', 'max:20'],
            "{$p}.metros.*.country" => ['required', 'alpha', 'size:2'],
            "{$p}.excluded_metros" => ['nullable', 'array', 'max:500'],
            "{$p}.excluded_metros.*.id" => ['required', 'string', 'max:20'],
            "{$p}.excluded_metros.*.country" => ['required', 'alpha', 'size:2'],
            "{$p}.visitation" => ['nullable', 'array', 'max:200'],
            "{$p}.visitation.*" => ['string', 'max:60'],
            "{$p}.nielsen" => ['nullable', 'array', 'max:200'],
            "{$p}.nielsen.*" => ['string', 'max:60'],
            "{$p}.location_categories" => ['nullable', 'array', 'max:200'],
            "{$p}.location_categories.*" => ['string', 'max:60'],
            "{$p}.carriers" => ['nullable', 'array', 'max:100'],
            "{$p}.carriers.*" => ['string', 'max:60'],
            "{$p}.device_makes" => ['nullable', 'array', 'max:200'],
            "{$p}.device_makes.*" => ['string', 'max:120'],
            "{$p}.circles" => ['nullable', 'array', 'max:500'],
            "{$p}.circles.*.latitude" => ['required', 'numeric', 'between:-90,90'],
            "{$p}.circles.*.longitude" => ['required', 'numeric', 'between:-180,180'],
            "{$p}.circles.*.radius" => ['required', 'numeric', 'between:0.1,100'],
            "{$p}.gender" => ['nullable', Rule::in(B::GENDERS)],
            "{$p}.min_age" => ['nullable', 'integer', 'between:' . B::MIN_AGE[0] . ',' . B::MIN_AGE[1]],
            "{$p}.max_age" => ['nullable', 'integer', 'between:' . B::MAX_AGE[0] . ',' . B::MAX_AGE[1]],
            "{$p}.languages" => ['nullable', 'array', 'max:50'],
            "{$p}.languages.*" => ['string', 'max:12'],
            "{$p}.advanced_demographics" => ['nullable', 'array', 'max:100'],
            "{$p}.advanced_demographics.*" => ['string', 'max:60'],
            "{$p}.interests" => ['nullable', 'array', 'max:200'],
            "{$p}.interests.*" => ['string', 'max:60'],
            "{$p}.excluded_interests" => ['nullable', 'array', 'max:200'],
            "{$p}.excluded_interests.*" => ['string', 'max:60'],
            "{$p}.os_types" => ['nullable', 'array', 'max:2'],
            "{$p}.os_types.*" => ['in:iOS,ANDROID'],
            "{$p}.os_versions" => ['nullable', 'array'],
            "{$p}.os_versions.*.min" => ['nullable', 'string', 'max:20', 'regex:/^[0-9.]+$/'],
            "{$p}.os_versions.*.max" => ['nullable', 'string', 'max:20', 'regex:/^[0-9.]+$/'],
            "{$p}.connection_type" => ['nullable', 'string', 'max:20', 'regex:/^[A-Z_]+$/'],
            "{$p}.audiences" => ['nullable', 'array', 'max:100'],
            "{$p}.audiences.*" => ['string', 'max:40'],
            "{$p}.excluded_audiences" => ['nullable', 'array', 'max:100'],
            "{$p}.excluded_audiences.*" => ['string', 'max:40'],
            "{$p}.regulated_content" => ['nullable', 'boolean'],
            "{$p}.interest_expansion" => ['nullable', 'boolean'],
            "{$p}.audience_expansion" => ['nullable', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            if (in_array($this->input('adsquad.bid_strategy'), ['TARGET_COST'], true) && $this->input('adsquad.goal') === 'IMPRESSIONS') {
                $v->errors()->add('adsquad.bid_strategy', __('snapchat_builder.server.target_cost_impressions'));
            }
            if ($this->input('adsquad.pacing') === 'ACCELERATED' && $this->input('adsquad.bid_strategy') !== 'LOWEST_COST_WITH_MAX_BID') {
                $v->errors()->add('adsquad.pacing', __('snapchat_builder.server.accelerated'));
            }
            if ($this->filled('adsquad.schedule') && $this->input('adsquad.budget_type') !== 'lifetime') {
                $v->errors()->add('adsquad.schedule', __('snapchat_builder.server.schedule_lifetime'));
            }
            $daily = $this->input('campaign.daily_cap');
            if ($this->input('campaign.mode') === 'new' && $daily && $this->input('adsquad.budget_type') === 'daily'
                && (float) $daily < 0.25 * (float) $this->input('adsquad.budget')) {
                $v->errors()->add('campaign.daily_cap', __('snapchat_builder.server.campaign_cap_low'));
            }
        });
    }

    /** The validated builder input (without the key). */
    public function builderInput(): array
    {
        return collect($this->validated())->except('idempotency_key')->all();
    }
}
