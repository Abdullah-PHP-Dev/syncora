<?php

namespace App\Services\AdServices;

use App\Models\Admin\AdAccount;
use App\Models\Admin\AdCampaign;
use App\Models\Admin\AdAdGroup;
use App\Models\Admin\AdCreative;
use App\Models\Admin\Ad;
use App\Services\ApiService;
use App\Services\AdServices\Concerns\GoogleAdsApiTrait;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

/**
 * Google Ads API (REST, v24) integration - Search campaigns.
 *
 * Endpoint paths and payload shapes verified against Google's official docs
 * (developers.google.com/google-ads/api/rest/examples and
 * .../docs/campaigns/budgets/create-budgets): every write goes through a
 * `{resource}:mutate` endpoint with an `operations` array of
 * {create|update|remove} objects, and resources reference each other by
 * full resource name string (`customers/{id}/campaigns/{id}`) rather than
 * bare IDs - so this stores full resource names in ad_campaign_id/
 * ad_adgroup_id/ad_creative_id/ad_id, not numeric IDs like the other
 * platforms.
 *
 * IMPORTANT: this account has no developer_token configured (a Google
 * review/approval requirement, not something obtainable by writing code),
 * so none of this has been exercised against a live account. Endpoint
 * paths, field names, and enum values are all sourced from Google's current
 * docs; verify against a real test account before relying on it for live
 * spend.
 */
class GoogleAdService
{
    use GoogleAdsApiTrait;

    public function __construct(AdAccount $account, ApiService $apiService)
    {
        $this->apiService = $apiService;
        $this->account = $account->wherePlatform('google')->whereUserId(Auth::user()->id)->first();
        $this->config = adminSetting('ads.google.base_url');

        if ($this->account) {
            $this->header = $this->getHeaders();
        }
    }

    public function store($platform, $request)
    {
        $response = $this->storeBudget($platform, $request);

        if (!$response['success']) {
            return $response;
        }

        $request['budget_resource'] = $response['data']['resource'];

        $response = $this->storeCampaign($platform, $request);

        if (!$response['success']) {
            return $response;
        }

        $request['campaign_resource'] = $response['data']['resource'];
        $request['ad_campaign_id'] = $response['data']['id'];

        $response = $this->storeAdGroup($platform, $request);

        if (!$response['success']) {
            return $response;
        }

        $request['adgroup_resource'] = $response['data']['resource'];
        $request['ad_adgroup_id'] = $response['data']['id'];

        $response = $this->storeKeywords($request);

        if (!$response['success']) {
            return $response;
        }

        $response = $this->storeTargeting($request);

        if (!$response['success']) {
            return $response;
        }

        return $this->storeAd($platform, $request);
    }

    private function storeBudget($platform, $request)
    {
        $endpoint = $this->config . 'customers/' . $this->customerId() . '/campaignBudgets:mutate';

        $payload = [
            'operations' => [[
                'create' => [
                    'name'             => $request['name'] . ' Budget ' . time(),
                    'amountMicros'     => (int) ((float) $request['budget'] * 1000000),
                    'deliveryMethod'   => 'STANDARD',
                    'explicitlyShared' => false,
                ],
            ]],
        ];

        $result = $this->mutate($endpoint, $payload);

        if (!$result['success']) {
            return $result;
        }

        return $this->successResponse(['resource' => $result['data']['resourceName']]);
    }

    private function storeCampaign($platform, $request)
    {
        $endpoint = $this->config . 'customers/' . $this->customerId() . '/campaigns:mutate';

        $campaign = [
            'name'                    => $request['name'],
            'status'                  => 'PAUSED',
            'advertisingChannelType'  => 'SEARCH',
            'campaignBudget'          => $request['budget_resource'],
            'startDate'               => Carbon::parse($request['start_time'])->format('Y-m-d'),
            'endDate'                 => Carbon::parse($request['end_time'])->format('Y-m-d'),
            'networkSettings'         => [
                'targetGoogleSearch'    => true,
                'targetSearchNetwork'   => true,
                'targetContentNetwork'  => false,
            ],
            'geoTargetTypeSetting' => [
                'positiveGeoTargetType' => 'PRESENCE_OR_INTEREST',
                'negativeGeoTargetType' => 'PRESENCE_OR_INTEREST',
            ],
            'containsEuPoliticalAdvertising' => 'DOES_NOT_CONTAIN_EU_POLITICAL_ADVERTISING',
        ];

        $campaign = array_merge($campaign, $this->biddingStrategyPayload($request));

        $result = $this->mutate($endpoint, ['operations' => [['create' => $campaign]]]);

        if (!$result['success']) {
            return $result;
        }

        $resourceName = $result['data']['resourceName'];

        $dataToInsert = [
            'ad_campaign_id'           => $resourceName,
            'user_id'                  => Auth::id(),
            'ad_account_id'            => $this->account->id,
            'name'                     => $request['name'],
            'platform'                 => $platform,
            'advertising_channel_type' => 'SEARCH',
            'budget_mode'              => $request['budget_mode'],
            'budget'                   => $request['budget'],
            'budget_resource_id'       => $request['budget_resource'],
            'bidding_strategy'         => $request['bid_strategy'],
            'bidding_amount'           => $request['bid_amount'] ?? null,
            'start_time'               => $request['start_time'],
            'end_time'                 => $request['end_time'],
            'status'                   => false,
        ];

        $campaignRecord = $this->apiService->success($dataToInsert, ['ad_campaign_id' => $resourceName], new AdCampaign);

        return $this->successResponse(['resource' => $resourceName, 'id' => $campaignRecord['data']->id]);
    }

    private function biddingStrategyPayload($request)
    {
        return match ($request['bid_strategy']) {
            'MAXIMIZE_CONVERSIONS' => ['maximizeConversions' => (object) []],
            'TARGET_CPA'           => ['targetCpa' => ['targetCpaMicros' => (int) ((float) $request['bid_amount'] * 1000000)]],
            'TARGET_ROAS'          => ['targetRoas' => ['targetRoas' => (float) $request['bid_amount']]],
            'MANUAL_CPC'           => ['manualCpc' => ['enhancedCpcEnabled' => false]],
            'TARGET_SPEND'         => ['targetSpend' => (object) []],
            default                => ['manualCpc' => ['enhancedCpcEnabled' => false]],
        };
    }

    private function storeAdGroup($platform, $request)
    {
        $endpoint = $this->config . 'customers/' . $this->customerId() . '/adGroups:mutate';

        $adGroup = [
            'campaign' => $request['campaign_resource'],
            'name'     => $request['name'] . ' Ad Group',
            'status'   => 'ENABLED',
            'type'     => 'SEARCH_STANDARD',
        ];

        if ($request['bid_strategy'] === 'MANUAL_CPC' && !empty($request['bid_amount'])) {
            $adGroup['cpcBidMicros'] = (int) ((float) $request['bid_amount'] * 1000000);
        }

        $result = $this->mutate($endpoint, ['operations' => [['create' => $adGroup]]]);

        if (!$result['success']) {
            return $result;
        }

        $resourceName = $result['data']['resourceName'];

        $dataToInsert = [
            'ad_campaign_id' => $request['ad_campaign_id'],
            'user_id'        => Auth::id(),
            'ad_adgroup_id'  => $resourceName,
            'ad_account_id'  => $this->account->id,
            'platform'       => $platform,
            'name'           => $adGroup['name'],
            'status'         => false,
            'gender'         => $request['gender'] ?? null,
            'age_groups'     => json_encode($request['age_range'] ?? []),
            'languages'      => json_encode($request['languages'] ?? []),
            'location_ids'   => json_encode($request['countries'] ?? []),
            'keywords'       => json_encode($this->parseKeywords($request)),
            'bid_strategy'   => $request['bid_strategy'],
            'bid_price'      => $request['bid_amount'] ?? null,
        ];

        $adGroupRecord = $this->apiService->success($dataToInsert, ['ad_adgroup_id' => $resourceName], new AdAdGroup);

        return $this->successResponse(['resource' => $resourceName, 'id' => $adGroupRecord['data']->id]);
    }

    /**
     * The blade collects keywords as newline-separated text with one
     * uniform match_type applied to all of them, kept simple to mirror the
     * rest of this app's targeting UI rather than a per-keyword match-type
     * picker.
     */
    private function parseKeywords($request): array
    {
        $matchType = $request['match_type'] ?? 'BROAD';

        return collect(preg_split('/\r\n|\r|\n/', $request['keywords'] ?? ''))
            ->map(fn($line) => trim($line))
            ->filter()
            ->map(fn($text) => ['text' => $text, 'match_type' => $matchType])
            ->values()
            ->all();
    }

    private function storeKeywords($request)
    {
        $keywords = $this->parseKeywords($request);

        if (empty($keywords)) {
            return $this->errorResponse('At least one keyword is required for a Search campaign.');
        }

        $endpoint = $this->config . 'customers/' . $this->customerId() . '/adGroupCriteria:mutate';

        $operations = array_map(fn($kw) => [
            'create' => [
                'adGroup' => $request['adgroup_resource'],
                'status'  => 'ENABLED',
                'keyword' => [
                    'text'      => $kw['text'],
                    'matchType' => $kw['match_type'],
                ],
            ],
        ], $keywords);

        return $this->mutateMultiple($endpoint, $operations);
    }

    private function storeAd($platform, $request)
    {
        $endpoint = $this->config . 'customers/' . $this->customerId() . '/adGroupAds:mutate';

        $headlines = $this->parseTextList($request['headlines'] ?? '', 30);
        $descriptions = $this->parseTextList($request['descriptions'] ?? '', 90);

        if (count($headlines) < 3) {
            return $this->errorResponse('Responsive Search Ads need at least 3 headlines.');
        }

        if (count($descriptions) < 2) {
            return $this->errorResponse('Responsive Search Ads need at least 2 descriptions.');
        }

        $payload = [
            'adGroup' => $request['adgroup_resource'],
            'status'  => 'PAUSED',
            'ad'      => [
                'responsiveSearchAd' => [
                    'headlines'    => array_map(fn($t) => ['text' => $t], $headlines),
                    'descriptions' => array_map(fn($t) => ['text' => $t], $descriptions),
                ],
                'finalUrls' => [$request['target_link']],
            ],
        ];

        $result = $this->mutate($endpoint, ['operations' => [['create' => $payload]]]);

        if (!$result['success']) {
            return $result;
        }

        $resourceName = $result['data']['resourceName'];

        $creativeRecord = $this->apiService->success(
            [
                'user_id'        => Auth::id(),
                'ad_adgroup_id'  => $request['ad_adgroup_id'],
                'ad_creative_id' => $resourceName,
                'platform'       => $platform,
                'ad_account_id'  => $this->account->id,
                'ad_campaign_id' => $request['ad_campaign_id'],
                'name'           => $request['name'],
                'type'           => 'RESPONSIVE_SEARCH_AD',
                'headlines'      => json_encode($headlines),
                'descriptions'   => json_encode($descriptions),
                'final_urls'     => json_encode([$request['target_link']]),
                'url'            => $request['target_link'],
            ],
            ['ad_creative_id' => $resourceName],
            new AdCreative
        );

        return $this->apiService->success(
            [
                'user_id'        => Auth::id(),
                'ad_adgroup_id'  => $request['ad_adgroup_id'],
                'ad_creative_id' => $creativeRecord['data']->id,
                'ad_id'          => $resourceName,
                'status'         => false,
                'platform'       => $platform,
                'ad_account_id'  => $this->account->id,
                'ad_campaign_id' => $request['ad_campaign_id'],
                'name'           => $request['name'],
                'type'           => 'RESPONSIVE_SEARCH_AD',
                'final_urls'     => json_encode([$request['target_link']]),
                'headlines'      => json_encode($headlines),
            ],
            ['ad_id' => $resourceName],
            new Ad
        );
    }

    public function update($platform, $id, $request)
    {
        $campaign = AdCampaign::findOrFail($id);
        $adGroup = AdAdGroup::whereAdCampaignId($id)->firstOrFail();
        $creative = $adGroup->creatives->first();
        $ad = Ad::whereAdCampaignId($id)->first();

        $result = $this->updateCampaignNameAndDates($campaign, $request);

        if (!$result['success']) {
            return $result;
        }

        if ($ad && $creative) {
            $headlines = $this->parseTextList($request['headlines'] ?? '', 30);
            $descriptions = $this->parseTextList($request['descriptions'] ?? '', 90);

            if (count($headlines) >= 3 && count($descriptions) >= 2) {
                $adUpdate = [
                    'ad' => [
                        'responsiveSearchAd' => [
                            'headlines'    => array_map(fn($t) => ['text' => $t], $headlines),
                            'descriptions' => array_map(fn($t) => ['text' => $t], $descriptions),
                        ],
                        'finalUrls' => [$request['target_link']],
                    ],
                ];

                $adResult = $this->mutate(
                    $this->config . 'customers/' . $this->customerId() . '/adGroupAds:mutate',
                    ['operations' => [[
                        'update'     => array_merge(['resourceName' => $ad->ad_id], $adUpdate),
                        'updateMask' => 'ad.responsive_search_ad.headlines,ad.responsive_search_ad.descriptions,ad.final_urls',
                    ]]]
                );

                if (!$adResult['success']) {
                    return $adResult;
                }

                $creative->update([
                    'headlines'    => json_encode($headlines),
                    'descriptions' => json_encode($descriptions),
                    'final_urls'   => json_encode([$request['target_link']]),
                    'url'          => $request['target_link'],
                ]);

                $ad->update([
                    'final_urls' => json_encode([$request['target_link']]),
                    'headlines'  => json_encode($headlines),
                ]);
            }
        }

        return $this->successResponse(['ad_campaign_id' => $campaign->id]);
    }
}
