<?php

namespace App\Http\Requests\Admin;

use App\Services\AdServices\FacebookCampaignBuilder;
use App\Support\Ads\MetaGoals;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Shape of a campaign-builder submission (FacebookCampaignBuilder::submit()):
 * goal + destination, then campaign / ad set / creative each "existing"
 * (an id) or "new" (its settings), then the ad. Ownership of the ids is
 * checked against Meta by the builder itself.
 */
class FacebookAdBuilderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    public function rules(): array
    {
        $goal = (string) $this->input('goal');
        $destinations = array_keys(MetaGoals::find($goal)['destinations'] ?? []);
        $newCampaign = $this->input('campaign.mode') === 'new';
        $newAdSet = $this->input('adset.mode') === 'new';
        $newCreative = $this->input('creative.mode') === 'new';
        $campaignBudget = $newCampaign && $this->boolean('campaign.campaign_budget');
        $format = strtoupper((string) $this->input('creative.format'));
        $newCarousel = $newCreative && $format === 'CAROUSEL';

        return [
            'idempotency_key' => ['required', 'string', 'min:16', 'max:64', 'regex:/^[A-Za-z0-9-]+$/'],
            'channel' => ['nullable', Rule::in(FacebookCampaignBuilder::CHANNELS)],
            'goal' => ['required', Rule::in(array_keys(MetaGoals::all()))],
            'destination' => ['required', Rule::in($destinations)],

            'campaign.mode' => ['required', 'in:existing,new'],
            'campaign.id' => [Rule::requiredIf(! $newCampaign), 'nullable', 'regex:/^\d+$/'],
            'campaign.name' => [Rule::requiredIf($newCampaign), 'nullable', 'string', 'max:255'],
            'campaign.special_ad_category' => ['nullable', 'in:HOUSING,EMPLOYMENT,CREDIT,ISSUES_ELECTIONS_POLITICS,FINANCIAL_PRODUCTS_SERVICES'],
            'campaign.campaign_budget' => ['nullable', 'boolean'],
            'campaign.budget_type' => ['nullable', 'in:daily,lifetime'],
            'campaign.budget' => [Rule::requiredIf($campaignBudget), 'nullable', 'numeric', 'min:1'],
            'campaign.bid_strategy' => ['nullable', 'in:LOWEST_COST_WITHOUT_CAP,COST_CAP,LOWEST_COST_WITH_BID_CAP'],

            'adset.mode' => ['required', 'in:existing,new'],
            'adset.id' => [Rule::requiredIf(! $newAdSet), 'nullable', 'regex:/^\d+$/'],
            'adset.name' => [Rule::requiredIf($newAdSet), 'nullable', 'string', 'max:255'],
            'adset.optimization_goal' => ['nullable', 'string', 'max:60'],
            'adset.budget_type' => ['nullable', 'in:daily,lifetime'],
            'adset.budget' => [Rule::requiredIf($newAdSet && ! $campaignBudget && $this->input('campaign.mode') === 'new'), 'nullable', 'numeric', 'min:1'],
            'adset.bid_strategy' => ['nullable', 'in:LOWEST_COST_WITHOUT_CAP,COST_CAP,LOWEST_COST_WITH_BID_CAP'],
            'adset.bid_amount' => [Rule::requiredIf(in_array($this->input('adset.bid_strategy'), ['COST_CAP', 'LOWEST_COST_WITH_BID_CAP'], true)), 'nullable', 'numeric', 'min:0.01'],
            'adset.start_time' => ['nullable', 'date', 'after_or_equal:today'],
            'adset.end_time' => [Rule::requiredIf($newAdSet && $this->input('adset.budget_type') === 'lifetime'), 'nullable', 'date', 'after:adset.start_time'],
            'adset.page_id' => ['nullable', 'regex:/^\d+$/'],
            'adset.pixel_id' => ['nullable', 'regex:/^\d+$/'],
            'adset.custom_event_type' => ['nullable', Rule::in(array_keys(MetaGoals::CONVERSION_EVENTS))],
            'adset.application_id' => ['nullable', 'regex:/^\d+$/'],
            'adset.object_store_url' => ['nullable', 'url'],
            'adset.targeting' => ['nullable', 'array'],
            'adset.targeting.locations' => [Rule::requiredIf($newAdSet && ! $this->input('adset.targeting.saved_audience_id')), 'array'],
            'adset.targeting.locations.*.key' => ['required', 'string', 'max:60'],
            'adset.targeting.locations.*.type' => ['required', Rule::in(array_keys(FacebookCampaignBuilder::LOCATION_TYPES))],
            'adset.targeting.locations.*.radius' => ['nullable', 'integer', 'min:1', 'max:80'],
            'adset.targeting.excluded_locations' => ['nullable', 'array'],
            'adset.targeting.age_min' => ['nullable', 'integer', 'between:18,65'],
            'adset.targeting.age_max' => ['nullable', 'integer', 'between:18,65', 'gte:adset.targeting.age_min'],
            'adset.targeting.genders' => ['nullable', 'array'],
            'adset.targeting.genders.*' => ['in:1,2'],
            'adset.targeting.locales' => ['nullable', 'array', 'max:50'],
            'adset.targeting.interests' => ['nullable', 'array', 'max:100'],
            'adset.targeting.interests.*.id' => ['required', 'regex:/^\d+$/'],
            'adset.targeting.narrow' => ['nullable', 'array', 'max:5'],
            'adset.targeting.custom_audiences' => ['nullable', 'array', 'max:50'],
            'adset.targeting.custom_audiences.*' => ['regex:/^\d+$/'],
            'adset.targeting.excluded_custom_audiences' => ['nullable', 'array', 'max:50'],
            'adset.targeting.excluded_custom_audiences.*' => ['regex:/^\d+$/'],
            'adset.targeting.saved_audience_id' => ['nullable', 'regex:/^\d+$/'],
            'adset.targeting.advantage_audience' => ['nullable', 'boolean'],
            'adset.placements.mode' => ['nullable', 'in:advantage,manual'],
            'adset.placements.publisher_platforms' => ['nullable', 'array'],
            'adset.placements.publisher_platforms.*' => ['in:facebook,instagram,messenger,audience_network'],
            'adset.placements.instagram_positions' => ['nullable', 'array'],
            'adset.placements.instagram_positions.*' => [Rule::in(FacebookCampaignBuilder::INSTAGRAM_POSITIONS)],
            'adset.targeting.interests.*.type' => ['nullable', 'string', 'max:40', 'regex:/^[a-z_]+$/'],

            'creative.mode' => ['required', 'in:existing,new'],
            'creative.id' => [Rule::requiredIf(! $newCreative), 'nullable', 'regex:/^\d+$/'],
            'creative.name' => ['nullable', 'string', 'max:255'],
            'creative.format' => [Rule::requiredIf($newCreative), 'nullable', 'in:IMAGE,VIDEO,CAROUSEL,image,video,carousel'],
            'creative.page_id' => [Rule::requiredIf($newCreative), 'nullable', 'regex:/^\d+$/'],
            'creative.instagram_user_id' => [Rule::requiredIf($newCreative && $this->input('channel') === 'instagram'), 'nullable', 'regex:/^\d+$/'],
            'creative.primary_text' => [Rule::requiredIf($newCreative), 'nullable', 'string', 'max:2200'],
            'creative.headline' => ['nullable', 'string', 'max:255'],
            'creative.description' => ['nullable', 'string', 'max:255'],
            'creative.link' => ['nullable', 'url'],
            'creative.cta' => ['nullable', 'string', 'max:40', 'regex:/^[A-Z_]+$/'],
            'creative.lead_form_id' => ['nullable', 'regex:/^\d+$/'],
            'creative.media' => [Rule::requiredIf($newCreative && $format !== 'CAROUSEL'), 'nullable', 'array', 'max:1'],
            'creative.media.*.asset_id' => ['required', 'integer'],
            'creative.thumbnail_asset_id' => ['nullable', 'integer'],
            // Carousel cards only count for a new carousel; any other format
            // drops them (an empty [] would otherwise fail min:2).
            'creative.cards' => [Rule::excludeIf(! $newCarousel), 'required', 'array', 'min:2', 'max:10'],
            'creative.cards.*.asset_id' => [Rule::excludeIf(! $newCarousel), 'required', 'integer'],
            'creative.cards.*.headline' => [Rule::excludeIf(! $newCarousel), 'nullable', 'string', 'max:255'],
            'creative.cards.*.description' => [Rule::excludeIf(! $newCarousel), 'nullable', 'string', 'max:255'],
            'creative.cards.*.link' => [Rule::excludeIf(! $newCarousel), 'nullable', 'url'],

            'ad.name' => ['required', 'string', 'max:255'],
            'ad.status' => ['required', 'in:PAUSED,ACTIVE'],
        ];
    }

    public function messages(): array
    {
        $m = fn (string $key) => __("ads_builder.validation.{$key}");

        return [
            'destination.in' => $m('destination'),
            'campaign.id.required' => $m('campaign_id'),
            'campaign.name.required' => $m('campaign_name'),
            'campaign.budget.required' => $m('campaign_budget'),
            'adset.id.required' => $m('adset_id'),
            'adset.name.required' => $m('adset_name'),
            'adset.budget.required' => $m('adset_budget'),
            'adset.bid_amount.required' => $m('bid_amount'),
            'adset.end_time.required' => $m('end_required'),
            'adset.end_time.after' => $m('end_after'),
            'adset.start_time.after_or_equal' => $m('start_past'),
            'adset.targeting.locations.required' => $m('locations'),
            'adset.targeting.age_max.gte' => $m('age_max'),
            'creative.id.required' => $m('creative_id'),
            'creative.page_id.required' => $m('creative_page'),
            'creative.instagram_user_id.required' => $m('creative_instagram'),
            'creative.primary_text.required' => $m('primary_text'),
            'creative.media.required' => $m('media'),
            'creative.cards.required' => $m('cards_required'),
            'creative.cards.min' => $m('cards_min'),
            'creative.link.url' => $m('link_url'),
            'ad.name.required' => $m('ad_name'),
        ];
    }

    /** The validated builder settings (without the idempotency key). */
    public function builderInput(): array
    {
        $input = $this->validated();
        unset($input['idempotency_key']);

        // Unvalidated nested keys the builder reads are passed through
        // only from validated parents (targeting/placements/creative).
        $input['adset']['targeting'] = $this->input('adset.targeting', []);
        $input['adset']['placements'] = $this->input('adset.placements', []);
        $input['creative'] = array_merge($this->input('creative', []), $input['creative'] ?? []);

        return $input;
    }
}
