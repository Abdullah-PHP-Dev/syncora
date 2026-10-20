<?php

namespace App\Http\Requests\Admin;

use App\Services\AdServices\GoogleCampaignBuilder as B;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Submit payload of the Google campaign builder. Shape and limits follow
 * the Google Ads API (RSA / responsive display ad / asset group limits,
 * criterion enums); per-type eligibility is enforced again in
 * GoogleCampaignBuilder::validateSelection().
 */
class GoogleAdBuilderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    public function rules(): array
    {
        $type = $this->input('campaign_type');
        $limits = B::LIMITS[match ($type) { 'DISPLAY' => 'rda', 'PERFORMANCE_MAX' => 'pmax', default => 'rsa' }];
        $text = fn (string $key) => [$limits[$key][0], $limits[$key][1], $limits[$key][2]];
        [$hMin, $hMax, $hLen] = $text('headlines');
        [$dMin, $dMax, $dLen] = $text('descriptions');
        $search = $type === 'SEARCH';

        return [
            'idempotency_key' => ['required', 'string', 'min:16', 'max:64', 'regex:/^[A-Za-z0-9-]+$/'],
            'status' => ['required', 'in:PAUSED,ACTIVE'],
            'goal' => ['nullable', Rule::in(array_keys(B::GOALS))],
            'campaign_type' => ['required', Rule::in(array_keys(B::TYPES))],

            'campaign' => ['required', 'array'],
            'campaign.name' => ['required', 'string', 'max:255'],
            'campaign.budget' => ['required', 'numeric', 'min:' . B::MIN_DAILY_BUDGET, 'max:10000000'],
            'campaign.budget_type' => ['nullable', 'in:daily,lifetime'],
            'campaign.bidding' => ['required', Rule::in($type && isset(B::TYPES[$type]) ? B::TYPES[$type]['bidding'] : [])],
            'campaign.target_cpa' => ['nullable', 'numeric', 'min:0.01'],
            'campaign.target_roas' => ['nullable', 'numeric', 'min:1', 'max:100000'],
            'campaign.max_cpc' => ['nullable', 'required_if:campaign.bidding,MANUAL_CPC', 'numeric', 'min:0.01'],
            'campaign.cpc_ceiling' => ['nullable', 'required_if:campaign.bidding,TARGET_IMPRESSION_SHARE', 'numeric', 'min:0.01'],
            'campaign.is_location' => ['nullable', 'required_if:campaign.bidding,TARGET_IMPRESSION_SHARE', Rule::in(B::IMPRESSION_SHARE_LOCATIONS)],
            'campaign.is_fraction' => ['nullable', 'required_if:campaign.bidding,TARGET_IMPRESSION_SHARE', 'numeric', 'min:1', 'max:100'],
            'campaign.start_date' => ['nullable', 'required_if:campaign.budget_type,lifetime', 'date_format:Y-m-d', 'after_or_equal:' . now()->subDay()->toDateString()],
            'campaign.end_date' => ['nullable', 'required_if:campaign.budget_type,lifetime', 'date_format:Y-m-d', 'after_or_equal:today'],
            'campaign.geo_mode' => ['nullable', Rule::in(B::GEO_MODES)],
            'campaign.search_partners' => ['nullable', 'boolean'],
            'campaign.display_expansion' => ['nullable', 'boolean'],
            'campaign.goals' => ['nullable', 'array', 'max:50'],
            'campaign.goals.*.category' => ['required', 'string', 'regex:/^[A-Z_]+$/'],
            'campaign.goals.*.origin' => ['required', 'string', 'regex:/^[A-Z_]+$/'],
            'campaign.goals.*.biddable' => ['required', 'boolean'],

            'targeting' => ['nullable', 'array'],
            'targeting.locations' => ['nullable', 'array', 'max:1000'],
            'targeting.locations.*.id' => ['required', 'regex:/^\d+$/'],
            'targeting.locations.*.name' => ['nullable', 'string', 'max:255'],
            'targeting.locations.*.negative' => ['nullable', 'boolean'],
            'targeting.radius' => ['nullable', 'array', 'max:100'],
            'targeting.radius.*.latitude' => ['required', 'numeric', 'between:-90,90'],
            'targeting.radius.*.longitude' => ['required', 'numeric', 'between:-180,180'],
            'targeting.radius.*.radius' => ['required', 'numeric', 'min:1', 'max:800'],
            'targeting.radius.*.unit' => ['required', 'in:KILOMETERS,MILES'],
            'targeting.languages' => ['nullable', 'array', 'max:100'],
            'targeting.languages.*' => ['regex:/^\d+$/'],
            'targeting.schedule' => ['nullable', 'array', 'max:42'],
            'targeting.schedule.*.day' => ['required', Rule::in(B::DAYS)],
            'targeting.schedule.*.start_hour' => ['required', 'integer', 'between:0,23'],
            'targeting.schedule.*.end_hour' => ['required', 'integer', 'between:1,24', 'gt:targeting.schedule.*.start_hour'],
            'targeting.schedule.*.bid_adjustment' => ['nullable', 'numeric', 'between:-90,900'],
            'targeting.devices' => ['nullable', 'array'],
            'targeting.devices.*' => ['nullable', 'numeric', 'between:-100,900'],
            'targeting.ages' => ['nullable', 'array'],
            'targeting.ages.*' => [Rule::in(B::AGES)],
            'targeting.genders' => ['nullable', 'array'],
            'targeting.genders.*' => [Rule::in(B::GENDERS)],
            'targeting.parental' => ['nullable', 'array'],
            'targeting.parental.*' => [Rule::in(B::PARENTAL)],
            'targeting.incomes' => ['nullable', 'array'],
            'targeting.incomes.*' => [Rule::in(B::INCOMES)],
            'targeting.audiences' => ['nullable', 'array', 'max:200'],
            'targeting.audiences.*.id' => ['required', 'string', 'regex:#^customers/\d+/(userLists|userInterests|customAudiences|lifeEvents|detailedDemographics)/\d+$#'],
            'targeting.audiences.*.kind' => ['required', 'in:user_list,user_interest,custom_audience,life_event,detailed_demographic'],
            'targeting.audiences.*.name' => ['nullable', 'string', 'max:255'],
            'targeting.audience_mode' => ['nullable', 'in:observation,targeting'],
            'targeting.negative_keywords' => ['nullable', 'array', 'max:5000'],
            'targeting.negative_keywords.*.text' => ['required', 'string', 'max:80'],
            'targeting.negative_keywords.*.match_type' => ['required', Rule::in(B::MATCH_TYPES)],
            'targeting.placements' => ['nullable', 'array', 'max:500'],
            'targeting.placements.*' => ['string', 'max:255', 'regex:/^[a-z0-9.\-\/:]+$/i'],
            'targeting.excluded_placements' => ['nullable', 'array', 'max:500'],
            'targeting.excluded_placements.*' => ['string', 'max:255', 'regex:/^[a-z0-9.\-\/:]+$/i'],
            'targeting.topics' => ['nullable', 'array', 'max:200'],
            'targeting.topics.*.id' => ['required', 'regex:/^\d+$/'],
            'targeting.content_labels' => ['nullable', 'array'],
            'targeting.content_labels.*' => [Rule::in(B::CONTENT_LABELS)],
            'targeting.audience_exclusions' => ['nullable', 'array', 'max:200'],
            'targeting.audience_exclusions.*.id' => ['required', 'string', 'regex:#^customers/\d+/userLists/\d+$#'],
            'targeting.audience_exclusions.*.name' => ['nullable', 'string', 'max:255'],
            'targeting.optimized_targeting' => ['nullable', 'boolean'],
            'targeting.excluded_topics' => ['nullable', 'array', 'max:200'],
            'targeting.excluded_topics.*.id' => ['required', 'regex:/^\d+$/'],
            'targeting.app_categories' => ['nullable', 'array', 'max:200'],
            'targeting.app_categories.*.id' => ['required', 'regex:/^\d+$/'],
            'targeting.operating_systems' => ['nullable', 'array', 'max:100'],
            'targeting.operating_systems.*.id' => ['required', 'regex:/^\d+$/'],
            'targeting.carriers' => ['nullable', 'array', 'max:200'],
            'targeting.carriers.*.id' => ['required', 'regex:/^\d+$/'],
            // Single addresses or CIDR ranges; Google allows up to 500 per campaign.
            'targeting.ip_exclusions' => ['nullable', 'array', 'max:500'],
            'targeting.ip_exclusions.*' => ['string', 'max:64', 'distinct', 'regex:#^[0-9a-fA-F:.]+(/\d{1,3})?$#'],
            'targeting.frequency_cap' => ['nullable', 'array'],
            'targeting.frequency_cap.cap' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'targeting.frequency_cap.unit' => ['nullable', 'required_with:targeting.frequency_cap.cap', Rule::in(B::FREQUENCY_UNITS)],
            'targeting.search_themes' => ['nullable', 'array', 'max:' . B::MAX_SEARCH_THEMES],
            'targeting.search_themes.*' => ['string', 'max:80', 'distinct:ignore_case'],
            'targeting.audience_signal' => ['nullable', 'array'],
            'targeting.audience_signal.id' => ['nullable', 'string', 'regex:#^customers/\d+/audiences/\d+$#'],
            'targeting.audience_signal.name' => ['nullable', 'string', 'max:255'],

            'ad_group' => ['nullable', 'array'],
            'ad_group.name' => ['nullable', 'string', 'max:255'],
            'ad_group.keywords' => [$search ? 'required' : 'nullable', 'array', 'max:2000'],
            'ad_group.keywords.*.text' => ['required', 'string', 'max:80'],
            'ad_group.keywords.*.match_type' => ['required', Rule::in(B::MATCH_TYPES)],

            'ad' => ['required', 'array'],
            'ad.final_url' => ['required', 'url', 'starts_with:http://,https://', 'max:2048'],
            'ad.path1' => [$search ? 'nullable' : 'prohibited', 'string', 'max:' . B::LIMITS['rsa']['path'], 'not_regex:/[\s\/]/'],
            'ad.path2' => [$search ? 'nullable' : 'prohibited', 'string', 'max:' . B::LIMITS['rsa']['path'], 'not_regex:/[\s\/]/'],
            'ad.headlines' => ['required', 'array', "min:{$hMin}", "max:{$hMax}"],
            'ad.headlines.*' => ['required', 'string', 'distinct:ignore_case', "max:{$hLen}"],
            'ad.descriptions' => ['required', 'array', "min:{$dMin}", "max:{$dMax}"],
            'ad.descriptions.*' => ['required', 'string', 'distinct:ignore_case', "max:{$dLen}"],
            'ad.long_headlines' => $search ? ['prohibited'] : ['required', 'array', 'min:' . $limits['long_headlines'][0], 'max:' . $limits['long_headlines'][1]],
            'ad.long_headlines.*' => ['required', 'string', 'max:90'],
            'ad.business_name' => $search ? ['prohibited'] : ['required', 'string', 'max:25'],
            'ad.images' => $search ? ['prohibited'] : ['required', 'array'],
            'ad.images.marketing' => ['nullable', 'array'],
            'ad.images.marketing.*' => ['integer'],
            'ad.images.square' => ['nullable', 'array'],
            'ad.images.square.*' => ['integer'],
            'ad.images.logo' => ['nullable', 'array'],
            'ad.images.logo.*' => ['integer'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            foreach ((array) $this->input('targeting.devices', []) as $device => $value) {
                if ($value !== null && $value !== '' && (float) $value < -90 && (float) $value !== -100.0) {
                    $v->errors()->add("targeting.devices.{$device}", __('google_builder.server.device_range'));
                }
            }
            if ($this->input('campaign_type') === 'PERFORMANCE_MAX') {
                // At least one description must fit in 60 characters.
                $short = collect((array) $this->input('ad.descriptions', []))->contains(fn ($d) => mb_strlen((string) $d) <= 60);
                if (! $short) {
                    $v->errors()->add('ad.descriptions', __('google_builder.server.pmax_short_description'));
                }
            }
            $start = $this->input('campaign.start_date');
            $end = $this->input('campaign.end_date');
            if ($start && $end && $end < $start) {
                $v->errors()->add('campaign.end_date', __('google_builder.server.end_before_start'));
            }
            foreach ((array) $this->input('targeting.ip_exclusions', []) as $i => $ip) {
                if (! filter_var(explode('/', (string) $ip)[0], FILTER_VALIDATE_IP)) {
                    $v->errors()->add("targeting.ip_exclusions.{$i}", __('google_builder.server.ip_invalid', ['ip' => $ip]));
                }
            }
            if (empty($this->input('targeting.locations')) && empty($this->input('targeting.radius'))) {
                $v->errors()->add('targeting.locations', __('google_builder.server.locations_required'));
            }
        });
    }

    /** The validated input the builder consumes. */
    public function builderInput(): array
    {
        $data = $this->validated();
        unset($data['idempotency_key']);
        $data['targeting'] ??= [];
        $data['ad_group'] ??= [];
        // Text lists come in trimmed and non-empty.
        foreach (['headlines', 'descriptions', 'long_headlines'] as $k) {
            if (isset($data['ad'][$k])) {
                $data['ad'][$k] = array_values(array_filter(array_map('trim', $data['ad'][$k]), 'strlen'));
            }
        }

        return $data;
    }
}
