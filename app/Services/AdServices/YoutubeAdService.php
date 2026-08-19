<?php

namespace App\Services\AdServices;

use App\Models\Admin\AdAccount;
use App\Models\Admin\AdCampaign;
use App\Models\Admin\AdAdGroup;
use App\Models\Admin\AdCreative;
use App\Models\Admin\AdMedia;
use App\Models\Admin\Ad;
use App\Services\ApiService;
use App\Services\AdServices\Concerns\GoogleAdsApiTrait;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

/**
 * Google Ads API (REST, v24) integration - YouTube ads via Demand Gen
 * campaigns.
 *
 * Classic AdvertisingChannelType.VIDEO campaigns are read-only through the
 * API as of the 2025 Video-to-Demand-Gen upgrade - Demand Gen
 * (advertisingChannelType: DEMAND_GEN) is the only API-creatable path left
 * to YouTube video ads, so that's what this builds: a
 * DemandGenVideoResponsiveAd scoped to YouTube surfaces only (in-feed,
 * in-stream, Shorts - Gmail/Discover/Display are left off since this is
 * specifically the "YouTube ads" module, not general Demand Gen).
 *
 * There's no separate "YouTube Ads account" in Google's model - Demand Gen
 * campaigns run on the same Google Ads customer as Search - so this reuses
 * the AdAccount row connected under the 'google' platform rather than
 * expecting its own OAuth-linked account.
 *
 * IMPORTANT: like GoogleAdService, this has no developer_token/test account
 * to verify against; endpoint paths and field names (including the
 * DemandGenVideoResponsiveAd shape and demandGenAdGroupSettings.
 * channelControls) are sourced from Google's current docs
 * (developers.google.com/google-ads/api/docs/demand-gen/create-campaign)
 * but unexercised against a live account.
 */
class YoutubeAdService
{
    use GoogleAdsApiTrait;

    public function __construct(AdAccount $account, ApiService $apiService)
    {
        $this->apiService = $apiService;
        $this->account = $account->wherePlatform('google')->whereUserId(Auth::user()->id)->first();

        $this->config = adminSetting('ads.google.base_url') ?: 'https://googleads.googleapis.com/v24/';

        if ($this->account) {
            $this->header = $this->getHeaders();
        }
    }

    public function redirect($platform, $state)
    {
        return $this->buildAuthRedirect($platform, $state);
    }

    public function store($platform, $request)
    {
        $response = $this->storeBudget($request);
  
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

        $response = $this->storeVideoAsset($request);

        if (!$response['success']) {
            return $response;
        }

        $request['video_asset_resource'] = $response['data'];

        $response = $this->storeLogoAsset($platform, $request);

        if (!$response['success']) {
            return $response;
        }

        $request['logo_asset_resource'] = $response['data'];

        $response = $this->storeAdGroup($platform, $request);

        if (!$response['success']) {
            return $response;
        }

        $request['adgroup_resource'] = $response['data']['resource'];
        $request['ad_adgroup_id'] = $response['data']['id'];

        $response = $this->storeTargeting($platform, $request);

        if (!$response['success']) {
            return $response;
        }

        return $this->storeAd($platform, $request);
    }

    private function storeBudget($request)
    {
        $endpoint = $this->config . 'customers/' . $this->customerId() . '/campaignBudgets:mutate';
     
        $payload = [
            'operations' => [[
                'create' => $this->buildBudgetPayload($request),
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
            'advertisingChannelType'  => 'DEMAND_GEN',
            'campaignBudget'          => $request['budget_resource'],
            // v23 replaced the plain start_date/end_date (YYYY-MM-DD) fields
            // with start_date_time/end_date_time ("yyyy-MM-dd HH:mm:ss"),
            // adding time-of-day granularity - 00:00:00/23:59:59 reproduces
            // the old whole-day behavior.
            'startDateTime'           => Carbon::parse($request['start_time'])->format('Y-m-d 00:00:00'),
            'endDateTime'             => Carbon::parse($request['end_time'])->format('Y-m-d 23:59:59'),
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
            'advertising_channel_type' => 'DEMAND_GEN',
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

    /**
     * Standalone campaign.targetCpa was rejected live on this account for
     * Search - see GoogleAdService::biddingStrategyPayload()'s docblock.
     * That's an account/customer-level bidding-strategy rollout state
     * (campaign_bidding_strategy is the same oneof regardless of campaign
     * type), so Demand Gen gets the same bundled maximizeConversions form
     * pre-emptively rather than waiting to hit the identical error here.
     */
    private function biddingStrategyPayload($request)
    {
        return match ($request['bid_strategy']) {
            'MAXIMIZE_CONVERSIONS'      => ['maximizeConversions' => (object) []],
            'TARGET_CPA'                => ['maximizeConversions' => ['targetCpaMicros' => (int) ((float) $request['bid_amount'] * 1000000)]],
            'MAXIMIZE_CONVERSION_VALUE' => ['maximizeConversionValue' => (object) []],
            default                     => ['maximizeConversions' => (object) []],
        };
    }

    /**
     * Accepts either a bare 11-char YouTube video ID or a full watch/share
     * URL (youtube.com/watch?v=..., youtu.be/...), since that's what a user
     * pasting a video link into the form will actually submit.
     */
    private function extractYoutubeVideoId(string $input): string
    {
        $input = trim($input);

        if (preg_match('/^[a-zA-Z0-9_-]{11}$/', $input)) {
            return $input;
        }

        if (preg_match('/(?:v=|youtu\.be\/|shorts\/)([a-zA-Z0-9_-]{11})/', $input, $matches)) {
            return $matches[1];
        }

        return $input;
    }

    private function storeVideoAsset($request)
    {
        $videoId = $this->extractYoutubeVideoId($request['video_id']);

        $endpoint = $this->config . 'customers/' . $this->customerId() . '/assets:mutate';

        $payload = [
            'operations' => [[
                'create' => [
                    'name'             => $request['name'] . ' Video',
                    'type'             => 'YOUTUBE_VIDEO',
                    'youtubeVideoAsset' => ['youtubeVideoId' => $videoId],
                ],
            ]],
        ];

        $result = $this->mutate($endpoint, $payload);

        if (!$result['success']) {
            return $result;
        }

        return $this->successResponse($result['data']['resourceName']);
    }

    /**
     * Demand Gen video ads require a companion logo/square image asset -
     * uploaded to S3 (for local admin display, matching how every other
     * platform's media works in this app) and, in parallel, base64-encoded
     * for Google's assets:mutate IMAGE payload.
     */
    private function storeLogoAsset($platform, $request)
    {
        $media = $request['media'][0] ?? null;

        if (!$media) {
            return $this->errorResponse('A logo/companion image is required for YouTube Demand Gen ads.');
        }

        $extension = strtolower($media->getClientOriginalExtension());
        $fileName = time() . '_' . uniqid() . '.' . $extension;
        $s3Path = "uploads/{$platform}/logo/{$fileName}";

        $binary = file_get_contents($media->getRealPath());
        Storage::disk('r2')->put($s3Path, $binary, ['visibility' => 'public']);
        $fileUrl = Storage::disk('r2')->url($s3Path);

        $endpoint = $this->config . 'customers/' . $this->customerId() . '/assets:mutate';

        $payload = [
            'operations' => [[
                'create' => [
                    'name'       => $request['name'] . ' Logo',
                    'type'       => 'IMAGE',
                    'imageAsset' => ['data' => base64_encode($binary)],
                ],
            ]],
        ];

        $result = $this->mutate($endpoint, $payload);

        if (!$result['success']) {
            return $result;
        }

        $this->apiService->success(
            [
                'user_id'        => Auth::id(),
                'platform'       => $platform,
                'ad_account_id'  => $this->account->id,
                'ad_campaign_id' => $request['ad_campaign_id'],
                'name'           => $fileName,
                'file_name'      => $fileName,
                'type'           => 'IMAGE',
                'url'            => $fileUrl,
                'file_id'        => $result['data']['resourceName'],
            ],
            [],
            new AdMedia
        );

        return $this->successResponse($result['data']['resourceName']);
    }

    private function storeAdGroup($platform, $request)
    {
        $endpoint = $this->config . 'customers/' . $this->customerId() . '/adGroups:mutate';

        $adGroup = [
            'campaign' => $request['campaign_resource'],
            'name'     => $request['name'] . ' Ad Group',
            'status'   => 'ENABLED',
            'type'     => 'DEMAND_GEN_STANDARD',
            'demandGenAdGroupSettings' => [
                'channelControls' => [
                    'selectedChannels' => [
                        'youtubeInFeed'   => true,
                        'youtubeInStream' => true,
                        'youtubeShorts'   => true,
                        'discover'        => false,
                        'gmail'           => false,
                        'display'         => false,
                    ],
                ],
            ],
        ];

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
            'bid_strategy'   => $request['bid_strategy'],
            'bid_price'      => $request['bid_amount'] ?? null,
        ];

        $adGroupRecord = $this->apiService->success($dataToInsert, ['ad_adgroup_id' => $resourceName], new AdAdGroup);

        return $this->successResponse(['resource' => $resourceName, 'id' => $adGroupRecord['data']->id]);
    }

    private function storeAd($platform, $request)
    {
        $endpoint = $this->config . 'customers/' . $this->customerId() . '/adGroupAds:mutate';

        $headlines = $this->parseTextList($request['headlines'] ?? '', 40);
        $descriptions = $this->parseTextList($request['descriptions'] ?? '', 90);

        if (empty($headlines)) {
            return $this->errorResponse('At least one headline is required.');
        }

        if (empty($descriptions)) {
            return $this->errorResponse('At least one description is required.');
        }

        $payload = [
            'adGroup' => $request['adgroup_resource'],
            'status'  => 'PAUSED',
            'ad'      => [
                'name'      => $request['name'] . ' Ad',
                'finalUrls' => [$request['target_link']],
                'demandGenVideoResponsiveAd' => [
                    'businessName' => ['text' => $request['business_name']],
                    'videos'       => [['asset' => $request['video_asset_resource']]],
                    'logoImages'   => [['asset' => $request['logo_asset_resource']]],
                    'headlines'    => array_map(fn($t) => ['text' => $t], $headlines),
                    'descriptions' => array_map(fn($t) => ['text' => $t], $descriptions),
                ],
            ],
        ];

        $result = $this->mutate($endpoint, ['operations' => [['create' => $payload]]]);

        if (!$result['success']) {
            return $result;
        }

        $resourceName = $result['data']['resourceName'];

        $creativeRecord = $this->apiService->success(
            [
                'user_id'         => Auth::id(),
                'ad_adgroup_id'   => $request['ad_adgroup_id'],
                'ad_creative_id'  => $resourceName,
                'platform'        => $platform,
                'ad_account_id'   => $this->account->id,
                'ad_campaign_id'  => $request['ad_campaign_id'],
                'name'            => $request['name'],
                'type'            => 'DEMAND_GEN_VIDEO_RESPONSIVE_AD',
                'headlines'       => json_encode($headlines),
                'descriptions'    => json_encode($descriptions),
                'business_name'   => $request['business_name'],
                'video_asset_resource' => $request['video_asset_resource'],
                'logo_asset_resource'  => $request['logo_asset_resource'],
                'call_to_action'  => $request['call_to_action'] ?? null,
                'final_urls'      => json_encode([$request['target_link']]),
                'url'             => $request['target_link'],
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
                'type'           => 'DEMAND_GEN_VIDEO_RESPONSIVE_AD',
                'final_urls'     => json_encode([$request['target_link']]),
                'headlines'      => json_encode($headlines),
                'call_to_action' => $request['call_to_action'] ?? null,
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
            $headlines = $this->parseTextList($request['headlines'] ?? '', 40);
            $descriptions = $this->parseTextList($request['descriptions'] ?? '', 90);

            if (!empty($headlines) && !empty($descriptions)) {
                // Ad creative content is immutable through AdGroupAdService
                // (adGroupAds:mutate) - must go through AdService against
                // the bare Ad resource instead. See
                // adResourceNameFromAdGroupAd()'s docblock.
                $adUpdate = [
                    'resourceName' => $this->adResourceNameFromAdGroupAd($ad->ad_id),
                    'demandGenVideoResponsiveAd' => [
                        'businessName' => ['text' => $request['business_name']],
                        'headlines'    => array_map(fn($t) => ['text' => $t], $headlines),
                        'descriptions' => array_map(fn($t) => ['text' => $t], $descriptions),
                    ],
                    'finalUrls' => [$request['target_link']],
                ];

                $adResult = $this->mutate(
                    $this->config . 'customers/' . $this->customerId() . '/ads:mutate',
                    ['operations' => [[
                        'update'     => $adUpdate,
                        'updateMask' => 'demandGenVideoResponsiveAd.businessName,demandGenVideoResponsiveAd.headlines,demandGenVideoResponsiveAd.descriptions,finalUrls',
                    ]]]
                );

                if (!$adResult['success']) {
                    return $adResult;
                }

                $creative->update([
                    'headlines'     => json_encode($headlines),
                    'descriptions'  => json_encode($descriptions),
                    'business_name' => $request['business_name'],
                    'final_urls'    => json_encode([$request['target_link']]),
                    'url'           => $request['target_link'],
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
