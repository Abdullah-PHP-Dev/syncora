<?php

namespace App\Http\Requests\Admin;

use App\Services\AdServices\XCampaignBuilder as B;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Shape of an X campaign-builder submission (XCampaignBuilder::submit()):
 * objective, campaign (new / existing), line item with targeting, ad
 * (new promoted-only Tweet or existing Tweets). Ownership of every id is
 * checked against X by the builder itself.
 */
class XAdBuilderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    public function rules(): array
    {
        $objective = (string) $this->input('objective');
        $spec = B::OBJECTIVES[$objective] ?? [];
        $newCampaign = $this->input('campaign.mode') === 'new';
        $bid = $this->input('lineitem.bid_strategy');
        $goal = $this->input('lineitem.goal');
        $existing = $this->input('ad.source') === 'existing';
        $card = $this->input('ad.card');
        $needsApp = ! empty($spec['needs_app']);

        return [
            'idempotency_key' => ['required', 'string', 'min:16', 'max:64', 'regex:/^[A-Za-z0-9-]+$/'],
            'objective' => ['required', Rule::in(array_keys(B::OBJECTIVES))],
            'status' => ['required', 'in:ACTIVE,PAUSED'],

            'campaign.mode' => ['required', 'in:new,existing'],
            'campaign.id' => [Rule::requiredIf(! $newCampaign), 'nullable', 'string', 'max:40'],
            'campaign.name' => ['nullable', 'string', 'max:255'],
            'campaign.funding_instrument_id' => [Rule::requiredIf($newCampaign), 'nullable', 'string', 'max:40'],
            'campaign.daily_budget' => [Rule::requiredIf($newCampaign), 'nullable', 'numeric', 'min:1', 'max:100000000'],
            'campaign.total_budget' => ['nullable', 'numeric', 'min:1', 'max:1000000000', 'gte:campaign.daily_budget'],
            'campaign.purchase_order_number' => ['nullable', 'string', 'max:50'],

            'lineitem.name' => ['nullable', 'string', 'max:255'],
            'lineitem.goal' => ['required', Rule::in($spec['goals'] ?? [])],
            'lineitem.bid_strategy' => ['required', Rule::in($spec['bids'] ?? [])],
            'lineitem.bid_amount' => [Rule::requiredIf(in_array($bid, ['MAX', 'TARGET'], true)), 'nullable', 'numeric', 'gt:0', 'max:100000'],
            'lineitem.pay_by' => ['nullable', Rule::in(B::PAY_BY[$goal] ?? [])],
            'lineitem.placement_mode' => ['required', 'in:all,custom'],
            'lineitem.placements' => ['nullable', 'array', 'required_if:lineitem.placement_mode,custom'],
            'lineitem.placements.*' => [Rule::in(B::PLACEMENTS)],
            'lineitem.daily_budget' => ['nullable', 'numeric', 'min:1', 'max:100000000'],
            'lineitem.total_budget' => ['nullable', 'numeric', 'min:1', 'max:1000000000'],
            'lineitem.standard_delivery' => ['nullable', 'boolean'],
            'lineitem.start_time' => ['nullable', 'date'],
            'lineitem.end_time' => ['nullable', 'date', 'after:lineitem.start_time'],
            'lineitem.frequency_cap' => ['nullable', 'integer', 'between:1,100'],
            'lineitem.frequency_days' => ['nullable', Rule::in(B::FREQUENCY_DAYS)],
            'lineitem.web_event_tag' => [Rule::requiredIf($goal === 'WEBSITE_CONVERSIONS'), 'nullable', 'string', 'max:40', 'regex:/^[A-Za-z0-9_-]+$/'],
            'lineitem.impression_tag' => ['nullable', 'url:https', 'max:1000'],
            'lineitem.click_tracker' => ['nullable', 'url:https', 'max:1000'],
            ...$this->targetingRules('lineitem.targeting'),

            'app.ios_id' => [Rule::requiredIf($needsApp && ! $this->filled('app.android_id')), 'nullable', 'regex:/^\d{5,12}$/'],
            'app.android_id' => [Rule::requiredIf($needsApp && ! $this->filled('app.ios_id')), 'nullable', 'regex:/^[A-Za-z][A-Za-z0-9_]*(\.[A-Za-z0-9_]+)+$/', 'max:150'],
            'app.country_code' => [Rule::requiredIf($needsApp), 'nullable', 'alpha', 'size:2'],

            'ad.name' => ['nullable', 'string', 'max:80'],
            'ad.as_user_id' => ['required', 'regex:/^\d{1,25}$/'],
            'ad.source' => ['required', 'in:new,existing'],
            'ad.tweet_ids' => [Rule::requiredIf($existing), 'nullable', 'array', 'min:1', 'max:50'],
            'ad.tweet_ids.*' => ['regex:/^\d{1,25}$/'],
            'ad.text' => [Rule::requiredIf(! $existing && $card !== 'app' && ! $this->filled('ad.media_ids')), 'nullable', 'string', 'max:280'],
            'ad.media_ids' => [Rule::requiredIf(! $existing && in_array($card, ['website', 'app'], true)), 'nullable', 'array', 'max:4'],
            'ad.media_ids.*' => ['integer'],
            'ad.card' => [Rule::requiredIf(! $existing), 'nullable', 'in:none,website,app'],
            'ad.headline' => [Rule::requiredIf(! $existing && $card === 'website'), 'nullable', 'string', 'max:70'],
            'ad.url' => [Rule::requiredIf(! $existing && ! empty($spec['needs_url'])), 'nullable', 'url:https,http', 'max:1000'],
            'ad.app_label' => ['nullable', Rule::in(B::APP_CARD_LABELS)],
            'ad.conversation_settings' => ['nullable', Rule::in(B::CONVERSATION_SETTINGS)],
        ];
    }

    /** Targeting rules, shared with the audience estimate endpoint. */
    public function targetingRules(string $p): array
    {
        return [
            "{$p}.locations" => ['nullable', 'array', 'max:2000'],
            "{$p}.locations.*.id" => ['required', 'string', 'max:40'],
            "{$p}.languages" => ['nullable', 'array', 'max:50'],
            "{$p}.languages.*" => ['string', 'max:12'],
            "{$p}.gender" => ['nullable', Rule::in(B::GENDERS)],
            "{$p}.age" => ['nullable', Rule::in(B::AGE_BUCKETS)],
            "{$p}.platforms" => ['nullable', 'array', 'max:10'],
            "{$p}.platforms.*" => ['string', 'max:20'],
            "{$p}.platform_versions" => ['nullable', 'array', 'max:200'],
            "{$p}.platform_versions.*" => ['string', 'max:20'],
            "{$p}.devices" => ['nullable', 'array', 'max:500'],
            "{$p}.devices.*" => ['string', 'max:40'],
            "{$p}.network_operators" => ['nullable', 'array', 'max:200'],
            "{$p}.network_operators.*" => ['string', 'max:40'],
            "{$p}.app_categories" => ['nullable', 'array', 'max:100'],
            "{$p}.app_categories.*" => ['string', 'max:40'],
            "{$p}.interests" => ['nullable', 'array', 'max:100'],
            "{$p}.interests.*" => ['string', 'max:40'],
            "{$p}.conversations" => ['nullable', 'array', 'max:500'],
            "{$p}.conversations.*" => ['string', 'max:40'],
            "{$p}.event" => ['nullable', 'string', 'max:40'],
            "{$p}.keywords" => ['nullable', 'array', 'max:1000'],
            "{$p}.keywords.*.value" => ['required', 'string', 'max:100'],
            "{$p}.keywords.*.type" => ['required', Rule::in(B::KEYWORD_TYPES)],
            "{$p}.excluded_keywords" => ['nullable', 'array', 'max:1000'],
            "{$p}.excluded_keywords.*.value" => ['required', 'string', 'max:100'],
            "{$p}.excluded_keywords.*.type" => ['required', Rule::in(B::KEYWORD_TYPES)],
            "{$p}.own_followers" => ['nullable', 'boolean'],
            "{$p}.similar_followers" => ['nullable', 'array', 'max:100'],
            "{$p}.similar_followers.*" => ['regex:/^\d{1,25}$/'],
            "{$p}.audiences" => ['nullable', 'array', 'max:100'],
            "{$p}.audiences.*" => ['string', 'max:40'],
            "{$p}.excluded_audiences" => ['nullable', 'array', 'max:100'],
            "{$p}.excluded_audiences.*" => ['string', 'max:40'],
            "{$p}.audience_lookalike" => ['nullable', 'boolean'],
            "{$p}.audience_expansion" => ['nullable', Rule::in(B::AUDIENCE_EXPANSION)],
            "{$p}.engagement" => ['nullable', 'array'],
            "{$p}.engagement.type" => ['nullable', Rule::in(B::ENGAGEMENT_TYPES)],
            "{$p}.engagement.campaign_ids" => ['nullable', 'array', 'max:50'],
            "{$p}.engagement.campaign_ids.*" => ['string', 'max:40'],
            "{$p}.engagement.organic" => ['nullable', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            $e = $this->input('lineitem.targeting.engagement') ?? [];
            if (! empty($e['type']) && empty($e['campaign_ids']) && empty($e['organic'])) {
                $v->errors()->add('lineitem.targeting.engagement.type', __('x_builder.server.engagement_source'));
            }
            if ($this->input('ad.source') === 'new' && trim((string) $this->input('ad.text')) === '' && ! $this->filled('ad.media_ids')) {
                $v->errors()->add('ad.text', __('x_builder.errors.text'));
            }
        });
    }

    public function messages(): array
    {
        return [
            'campaign.total_budget.gte' => __('x_builder.errors.total_vs_daily'),
        ];
    }

    /** The validated builder input (without the key). */
    public function builderInput(): array
    {
        return collect($this->validated())->except('idempotency_key')->all();
    }
}
