<?php

namespace App\Http\Requests\Admin;

use App\Services\AdServices\TiktokCampaignBuilder as B;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Shape of a TikTok campaign-builder submission (TiktokCampaignBuilder::submit()):
 * objective, campaign (new / existing), ad group with targeting, ad.
 * Ownership of every id is checked against TikTok by the builder itself.
 */
class TiktokAdBuilderRequest extends FormRequest
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
        $cbo = $newCampaign && $this->boolean('campaign.budget_optimize_on');
        $existingCbo = ! $newCampaign && $this->boolean('campaign.cbo');
        $spark = $this->input('ad.source') === 'spark';
        $customBid = $this->input('adgroup.bid_type') === 'BID_TYPE_CUSTOM';
        $lifetime = $this->input('adgroup.budget_mode') === 'BUDGET_MODE_TOTAL' && ! $cbo && ! $existingCbo;

        return [
            'idempotency_key' => ['required', 'string', 'min:16', 'max:64', 'regex:/^[A-Za-z0-9-]+$/'],
            'objective' => ['required', Rule::in(array_keys(B::OBJECTIVES))],

            'campaign.mode' => ['required', 'in:new,existing'],
            'campaign.id' => [Rule::requiredIf(! $newCampaign), 'nullable', 'string', 'max:40'],
            'campaign.cbo' => ['nullable', 'boolean'],
            'campaign.name' => ['nullable', 'string', 'max:512'],
            'campaign.budget_optimize_on' => ['nullable', 'boolean'],
            'campaign.budget_mode' => [Rule::requiredIf($cbo), 'nullable', 'in:BUDGET_MODE_DAY,BUDGET_MODE_TOTAL'],
            'campaign.budget' => [Rule::requiredIf($cbo), 'nullable', 'numeric', 'min:1', 'max:100000000'],
            'campaign.app_promotion_type' => [Rule::requiredIf($newCampaign && $objective === 'APP_PROMOTION'), 'nullable', Rule::in(B::APP_PROMOTION_TYPES)],
            'campaign.special_industries' => ['nullable', 'array', 'max:3'],
            'campaign.special_industries.*' => [Rule::in(B::SPECIAL_INDUSTRIES)],

            'adgroup.name' => ['nullable', 'string', 'max:512'],
            'adgroup.optimization_goal' => ['required', Rule::in(array_keys($spec['goals'] ?? []))],
            'adgroup.placement_type' => ['required', 'in:AUTOMATIC,NORMAL'],
            'adgroup.placements' => ['nullable', 'array', 'required_if:adgroup.placement_type,NORMAL'],
            'adgroup.placements.*' => [Rule::in(B::PLACEMENTS)],
            'adgroup.search_result_enabled' => ['nullable', 'boolean'],
            'adgroup.comment_disabled' => ['nullable', 'boolean'],
            'adgroup.video_download_disabled' => ['nullable', 'boolean'],
            'adgroup.share_disabled' => ['nullable', 'boolean'],
            'adgroup.brand_safety_type' => ['nullable', Rule::in(B::BRAND_SAFETY)],
            'adgroup.budget_mode' => [Rule::requiredIf(! $cbo && ! $existingCbo), 'nullable', 'in:BUDGET_MODE_DAY,BUDGET_MODE_TOTAL'],
            'adgroup.budget' => [Rule::requiredIf(! $cbo && ! $existingCbo), 'nullable', 'numeric', 'min:1', 'max:100000000'],
            'adgroup.schedule_start' => ['nullable', 'date'],
            'adgroup.schedule_end' => [Rule::requiredIf($lifetime), 'nullable', 'date', 'after:adgroup.schedule_start'],
            'adgroup.dayparting' => ['nullable', 'array'],
            'adgroup.dayparting.enabled' => ['nullable', 'boolean'],
            'adgroup.dayparting.days' => ['nullable', 'array'],
            'adgroup.dayparting.days.*' => ['integer', 'between:0,6'],
            'adgroup.dayparting.from' => ['nullable', 'integer', 'between:0,23'],
            'adgroup.dayparting.to' => ['nullable', 'integer', 'between:1,24', 'gt:adgroup.dayparting.from'],
            'adgroup.bid_type' => ['required', 'in:BID_TYPE_NO_BID,BID_TYPE_CUSTOM'],
            'adgroup.bid' => [Rule::requiredIf($customBid), 'nullable', 'numeric', 'gt:0'],
            'adgroup.pacing' => ['nullable', 'in:PACING_MODE_SMOOTH,PACING_MODE_FAST'],
            'adgroup.pixel_id' => [Rule::requiredIf(! empty($spec['needs_pixel'])), 'nullable', 'string', 'max:40'],
            'adgroup.optimization_event' => [Rule::requiredIf(! empty($spec['needs_pixel'])), 'nullable', 'string', 'max:60'],
            'adgroup.app_id' => [Rule::requiredIf(! empty($spec['needs_app'])), 'nullable', 'string', 'max:40'],
            'adgroup.frequency' => ['nullable', 'integer', 'between:1,1000'],
            'adgroup.frequency_schedule' => ['nullable', 'integer', 'between:1,30'],

            'adgroup.targeting.locations' => ['required', 'array', 'min:1', 'max:3000'],
            'adgroup.targeting.locations.*.id' => ['required', 'string', 'max:40'],
            'adgroup.targeting.locations.*.type' => ['nullable', 'in:COUNTRY,PROVINCE,CITY,DISTRICT,DMA,ZIP_CODE'],
            'adgroup.targeting.languages' => ['nullable', 'array', 'max:50'],
            'adgroup.targeting.languages.*' => ['string', 'max:12'],
            'adgroup.targeting.gender' => ['nullable', Rule::in(B::GENDERS)],
            'adgroup.targeting.age_groups' => ['nullable', 'array'],
            'adgroup.targeting.age_groups.*' => [Rule::in(B::AGE_GROUPS)],
            'adgroup.targeting.spending_power' => ['nullable', 'in:ALL,HIGH'],
            'adgroup.targeting.household_income' => ['nullable', 'array'],
            'adgroup.targeting.household_income.*' => [Rule::in(B::HOUSEHOLD_INCOME)],
            'adgroup.targeting.audience_ids' => ['nullable', 'array', 'max:100'],
            'adgroup.targeting.audience_ids.*' => ['string', 'max:40'],
            'adgroup.targeting.excluded_audience_ids' => ['nullable', 'array', 'max:100'],
            'adgroup.targeting.excluded_audience_ids.*' => ['string', 'max:40'],
            'adgroup.targeting.interests' => ['nullable', 'array', 'max:500'],
            'adgroup.targeting.interests.*.id' => ['required', 'string', 'max:40'],
            'adgroup.targeting.interests.*.type' => ['required', Rule::in(B::INTEREST_TYPES)],
            'adgroup.targeting.action_period' => ['nullable', Rule::in(B::ACTION_PERIODS)],
            'adgroup.targeting.video_actions' => ['nullable', 'array'],
            'adgroup.targeting.video_actions.*' => [Rule::in(B::VIDEO_ACTIONS)],
            'adgroup.targeting.operating_systems' => ['nullable', 'array', 'max:2'],
            'adgroup.targeting.operating_systems.*' => ['in:ANDROID,IOS'],
            'adgroup.targeting.min_android_version' => ['nullable', 'string', 'max:10'],
            'adgroup.targeting.min_ios_version' => ['nullable', 'string', 'max:10'],
            'adgroup.targeting.device_model_ids' => ['nullable', 'array', 'max:500'],
            'adgroup.targeting.device_model_ids.*' => ['string', 'max:40'],
            'adgroup.targeting.network_types' => ['nullable', 'array'],
            'adgroup.targeting.network_types.*' => [Rule::in(B::NETWORK_TYPES)],
            'adgroup.targeting.device_price' => ['nullable', 'array'],
            'adgroup.targeting.device_price.min' => ['required_with:adgroup.targeting.device_price', 'integer', 'between:0,1000'],
            'adgroup.targeting.device_price.max' => ['required_with:adgroup.targeting.device_price', 'integer', 'between:50,1000', 'gt:adgroup.targeting.device_price.min'],
            'adgroup.targeting.smart_audience' => ['nullable', 'boolean'],
            'adgroup.targeting.smart_interest_behavior' => ['nullable', 'boolean'],

            'ad.name' => ['nullable', 'string', 'max:512'],
            'ad.identity_id' => ['required', 'string', 'max:80'],
            'ad.identity_type' => ['required', 'in:TT_USER,BC_AUTH_TT,AUTH_CODE'],
            'ad.source' => ['required', 'in:upload,spark'],
            'ad.tiktok_item_id' => [Rule::requiredIf($spark), 'nullable', 'string', 'max:40'],
            'ad.format' => [Rule::requiredIf(! $spark), 'nullable', 'in:SINGLE_VIDEO,SINGLE_IMAGE'],
            'ad.media_id' => [Rule::requiredIf(! $spark), 'nullable', 'integer'],
            'ad.cover_id' => ['nullable', 'integer'],
            // TikTok: up to 100 characters, no emoji.
            'ad.text' => [Rule::requiredIf(! $spark), 'nullable', 'string', 'max:100', 'not_regex:/[\x{1F000}-\x{1FAFF}\x{2600}-\x{27BF}]/u'],
            'ad.cta' => ['nullable', Rule::in(B::CTAS)],
            'ad.url' => [Rule::requiredIf(($spec['url'] ?? '') === 'required'), 'nullable', 'url:https,http', 'max:1000'],
            'ad.lead_form_id' => [Rule::requiredIf(! empty($spec['needs_form'])), 'nullable', 'string', 'max:40'],
            'ad.tracking_pixel_id' => ['nullable', 'string', 'max:40'],
            'ad.impression_tracking_url' => ['nullable', 'url:https', 'max:1000'],
            'ad.click_tracking_url' => ['nullable', 'url:https', 'max:1000'],
            'ad.status' => ['required', 'in:ENABLE,DISABLE'],
        ];
    }

    public function messages(): array
    {
        return [
            'ad.text.not_regex' => __('tiktok_builder.server.no_emoji'),
            'adgroup.targeting.locations.required' => __('tiktok_builder.server.need_location'),
        ];
    }

    /** The validated builder input (without the key). */
    public function builderInput(): array
    {
        return collect($this->validated())->except('idempotency_key')->all();
    }
}
