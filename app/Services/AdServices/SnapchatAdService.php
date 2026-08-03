<?php

namespace App\Services\AdServices;

use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Auth;
use App\Models\Admin\AdAccount;
use App\Models\Admin\AdCampaign;
use App\Models\Admin\AdAdGroup;
use App\Models\Admin\AdMedia;
use App\Models\Admin\Ad;
use App\Models\Admin\AdCreative;
use App\Models\Admin\AdCreativeMedia;
use App\Models\Country;
use App\Services\ApiService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Snapchat Ads API (v1) integration.
 *
 * Endpoint paths and payload shapes below were verified against Snap's
 * official developer docs (developers.snap.com/marketing-api/Ads-API/*).
 * Snapchat wraps every request/response in a named collection - eg.
 * {"campaigns":[{...}]} in, {"campaigns":[{"campaign":{...}}]} out - and
 * signals failure either via a non-2xx HTTP status or via
 * request_status=="ERROR" in an otherwise-200 response, with the real
 * reason under campaigns[0].sub_request_error_reason. Every call in this
 * class goes through parseSnapResponse() so that check happens in one
 * place.
 *
 * Two things worth flagging for anyone testing this against a live
 * sandbox: (1) the PUT (update) endpoint for Creatives is inferred from
 * the same "collection PUT" pattern Snap uses for Campaigns/AdSquads/Ads
 * (all four of which follow this convention) rather than pulled from an
 * explicit docs page, and likewise the DELETE endpoints for AdSquads and
 * Creatives are inferred from the confirmed Campaign/Ad DELETE pattern.
 * (2) Snapchat's creative model has no native multi-image "carousel" the
 * way Facebook/TikTok do - each creative carries a single
 * top_snap_media_id. The closest real equivalent is a COMPOSITE creative
 * that bundles several standalone SNAP_AD creatives via
 * composite_properties.creative_ids, which is what CAROUSEL media_type
 * builds here.
 */
class SnapchatAdService
{
    protected $account, $config, $apiService, $header;

    public function __construct(AdAccount $account, ApiService $apiService)
    {
        $this->apiService = $apiService;
        $this->account = $account->wherePlatform('snapchat')->whereUserId(Auth::user()->id)->first();
        $this->config = adminSetting('ads.snapchat.base_url');

        if ($this->account) {
            $this->header = $this->getHeaders();
        }
    }

    public function redirect($platform, $state)
    {
        $clientId = adminSetting('ads.snapchat.client_id');

        $url = 'https://accounts.snapchat.com/login/AdService2/authorize?' . http_build_query([
            'client_id'     => $clientId,
            'redirect_uri'  => $this->getCallbackUrl(),
            'response_type' => 'code',
            'scope'         => 'snapchat-marketing-api snapchat-profile-api',
            'state'         => $state,
        ]);

        return Redirect::to($url);
    }

    private function getCallbackUrl()
    {
        return config('services.app_url') . '/admin/social/auth/snapchat/callback';
    }

    public function store($platform, $request)
    {
        $response = $this->storeCampaign($platform, $request);

        if (!$response['success']) {
            return $response;
        }

        $request['campaign_id'] = $response['data']['ad_campaign_id'];
        $request['ad_campaign_id'] = $response['data']['id'];

        $response = $this->storeAdGroup($platform, $request);

        if (!$response['success']) {
            return $response;
        }

        $request['adgroup_id'] = $response['data']['ad_adgroup_id'];
        $request['ad_adgroup_id'] = $response['data']['id'];

        $response = $this->storeMedia($platform, $request);

        if (!$response['success']) {
            return $response;
        }

        $request['media'] = $response['data'];

        $response = $this->storeCreative($platform, $request);

        if (!$response['success']) {
            return $response;
        }

        $request['creative_id'] = $response['data']['ad_creative_id'];
        $request['ad_creative_id'] = $response['data']['id'];

        return $this->storeAd($platform, $request);
    }

    private function storeCampaign($platform, $request)
    {
        $endpoint = $this->config . 'adaccounts/' . $this->account->ad_account_id . '/campaigns';

        $objectiveProperties = ['objective_v2_type' => $request['objective']];

        // promotion_type is documented as optional and only meaningful for
        // AWARENESS_AND_ENGAGEMENT/APP_PROMOTION - the blade sends an empty
        // value for objectives where it doesn't apply, and Snapchat's API
        // would reject an empty string for an enum field, so it's only
        // included when there's a real value to send.
        if (!empty($request['promotion_type'])) {
            $objectiveProperties['promotion_type'] = $request['promotion_type'];
        }

        $payload = [
            'campaigns' => [[
                'ad_account_id' => $this->account->ad_account_id,
                'name'          => $request['name'],
                'status'        => 'PAUSED',
                'start_time'    => Carbon::parse($request['start_time'])->utc()->format('Y-m-d\TH:i:s.v\Z'),
                'end_time'      => Carbon::parse($request['end_time'])->utc()->format('Y-m-d\TH:i:s.v\Z'),
                'objective_v2_properties' => $objectiveProperties,
                'buy_model'      => 'AUCTION',
                'creation_state' => 'PUBLISHED',
            ]]
        ];

        $result = $this->parseSnapResponse(
            $this->apiService->post($endpoint, $this->header['data'], $payload),
            'campaigns',
            'campaign'
        );

        if (!$result['success']) {
            return $result;
        }

        $id = $result['data']['id'];

        $dataToInsert = [
            'ad_campaign_id'   => $id,
            'user_id'          => Auth::user()->id,
            'ad_account_id'    => $this->account->id,
            'name'             => $request['name'],
            'objective'        => $request['objective'],
            'budget_mode'      => $request['budget_mode'],
            'budget'           => $request['final_budget'],
            'platform'         => $platform,
            'start_time'       => $request['start_time'],
            'bidding_strategy' => $request['bid_strategy'],
            'end_time'         => $request['end_time'],
            'app_promotion_type' => $request['promotion_type'],
            'status'           => false,
        ];

        return $this->apiService->success($dataToInsert, ['ad_campaign_id' => $id], new AdCampaign);
    }

    /**
     * Shared by storeAdGroup/updateAdGroup - builds Snapchat's
     * targeting.demographics/geos blocks from the form's raw gender/
     * age_range[]/languages[]/countries[] values.
     */
    private function buildTargeting($request)
    {
        $demographicSpec = [];

        $genderMap = ['male' => 'MALE', 'female' => 'FEMALE'];
        $gender = $genderMap[$request['gender'] ?? ''] ?? null;

        if ($gender) {
            $demographicSpec['gender'] = $gender;
        }

        // The blade's age_range[] checkboxes submit Snapchat's own
        // demographics.age_groups values directly (eg. "18-20"), so no
        // translation is needed here.
        $ageGroups = array_values(array_unique($request['age_range'] ?? []));

        if (!empty($ageGroups)) {
            $demographicSpec['age_groups'] = $ageGroups;
        }

        $languages = array_values(array_unique(array_map('strtolower', $request['languages'] ?? [])));

        if (!empty($languages)) {
            $demographicSpec['languages'] = $languages;
        }

        $countries = Country::whereIn('id', $request['countries'])->pluck('code')->toArray();

        $geos = array_map(fn($code) => ['country_code' => strtolower($code)], $countries);

        return [$demographicSpec, $geos, $countries];
    }

    private function storeAdGroup($platform, $request)
    {
        $endpoint = $this->config . 'campaigns/' . $request['campaign_id'] . '/adsquads';

        [$demographicSpec, $geos, $countries] = $this->buildTargeting($request);

        $adSquad = [
            'name'         => $request['name'],
            'status'       => 'PAUSED',
            'campaign_id'  => $request['campaign_id'],
            'type'         => 'SNAP_ADS',
            'targeting'    => [
                'demographics' => [$demographicSpec],
                'geos'         => $geos,
            ],
            'placement_v2' => ['config' => 'AUTOMATIC'],
            'billing_event' => 'IMPRESSION',
            'bid_strategy'  => $request['bid_strategy'],
            'start_time'    => Carbon::parse($request['start_time'])->utc()->format('Y-m-d\TH:i:s.v\Z'),
            'end_time'      => Carbon::parse($request['end_time'])->utc()->format('Y-m-d\TH:i:s.v\Z'),
            'optimization_goal' => $request['optimization_goal'],
            'pacing_type'   => 'STANDARD',
            'brand_safety_config' => ['inventory_option' => 'LIMITED_INVENTORY'],
        ];

        $budgetInMicros = (int) ((float) $request['budget'] * 1000000);

        if (($request['budget_mode'] ?? 'daily') === 'daily') {
            $adSquad['daily_budget_micro'] = $budgetInMicros;
        } else {
            $adSquad['lifetime_budget_micro'] = $budgetInMicros;
        }

        if ($request['bid_strategy'] === 'TARGET_COST') {
            $adSquad['target_bid'] = true;
            $adSquad['auto_bid'] = false;
            $adSquad['bid_micro'] = (int) bcmul((string) $request['bid_amount'], '1000000', 0);
        } elseif ($request['bid_strategy'] === 'LOWEST_COST_WITH_MAX_BID') {
            $adSquad['target_bid'] = false;
            $adSquad['auto_bid'] = false;
            $adSquad['bid_micro'] = (int) bcmul((string) $request['bid_amount'], '1000000', 0);
        } elseif ($request['bid_strategy'] === 'AUTO_BID') {
            $adSquad['target_bid'] = false;
            $adSquad['auto_bid'] = true;
        }

        if (!empty($request['pixel_id'])) {
            $adSquad['pixel_id'] = $request['pixel_id'];
        }

        $result = $this->parseSnapResponse(
            $this->apiService->post($endpoint, $this->header['data'], ['adsquads' => [$adSquad]]),
            'adsquads',
            'adsquad'
        );

        if (!$result['success']) {
            return $result;
        }

        $id = $result['data']['id'];

        $dataToInsert = [
            'ad_campaign_id'      => $request['ad_campaign_id'],
            'user_id'             => Auth::user()->id,
            'ad_adgroup_id'       => $id,
            'ad_account_id'       => $this->account->id,
            'name'                => $request['name'],
            'location_ids'        => json_encode($countries),
            'platform'            => $platform,
            'gender'              => $request['gender'],
            'languages'           => json_encode($request['languages']),
            'budget_mode'         => $request['budget_mode'],
            'pixel_id'            => $request['pixel_id'] ?? '',
            'bid_price'           => $request['bid_amount'] ?? null,
            'bid_strategy'        => $request['bid_strategy'],
            'billing_event'       => 'IMPRESSION',
            'optimization_goal'   => $request['optimization_goal'],
            'schedule_start_time' => $request['start_time'],
            'schedule_end_time'   => $request['end_time'],
            'budget'              => $request['final_budget'],
            'age_groups'          => json_encode($request['age_range']),
            'status'              => false,
        ];

        return $this->apiService->success($dataToInsert, ['ad_adgroup_id' => $id], new AdAdGroup);
    }

    private function storeMedia($platform, $request)
    {
        $mediaIds = [];

        $cards = $request['media_type'] === 'CAROUSEL'
            ? (json_decode($request['carousel_cards'] ?? '[]', true) ?: [])
            : [];

        foreach ($request['media'] as $index => $media) {
            $extension = strtolower($media->getClientOriginalExtension());
            $mediaType = $this->getMediaType($extension);
            $fileName = time() . '_' . uniqid() . '.' . $extension;

            $s3Path = "uploads/{$platform}/{$mediaType}/{$fileName}";
            Storage::disk('r2')->put($s3Path, file_get_contents($media->getRealPath()), ['visibility' => 'public']);
            $filePath = Storage::disk('r2')->url($s3Path);

            $containerResult = $this->parseSnapResponse(
                $this->apiService->post(
                    $this->config . "adaccounts/{$this->account->ad_account_id}/media",
                    $this->header['data'],
                    ['media' => [[
                        'ad_account_id' => $this->account->ad_account_id,
                        'type'          => strtoupper($mediaType),
                        'name'          => $fileName,
                    ]]]
                ),
                'media',
                'media'
            );

            if (!$containerResult['success']) {
                return $containerResult;
            }

            $snapMediaId = $containerResult['data']['id'];

            // Multipart uploads must not carry the JSON content-type header
            // used for every other call.
            $authHeader = ['Authorization' => $this->header['data']['Authorization']];

            $uploadResult = $this->parseSnapResponse(
                $this->apiService->post(
                    $this->config . "media/{$snapMediaId}/upload",
                    $authHeader,
                    [],
                    'multipart',
                    [[
                        'name'       => 'file',
                        'file_name'  => $fileName,
                        'media_file' => $media->getRealPath(),
                    ]]
                ),
                'result',
                null
            );

            if (!$uploadResult['success']) {
                return $this->errorResponse("Failed to upload file binary for media ID: {$snapMediaId}");
            }

            $isReady = $this->ensureMediaIsReady($snapMediaId);
            $thumbnailUrl = ($mediaType === 'VIDEO' && $isReady) ? $this->fetchThumbnail($snapMediaId) : null;

            $card = $cards[$index] ?? [];

            $dataToInsert = [
                'ad_media_id'    => $snapMediaId,
                'ad_account_id'  => $this->account->id,
                'ad_campaign_id' => $request['ad_campaign_id'],
                'platform'       => $platform,
                'name'           => $fileName,
                'url'            => $filePath,
                'download_link'  => $filePath,
                'type'           => $mediaType,
                'status'         => $isReady,
                'file_name'      => $fileName,
                'image_category' => $mediaType,
                'user_id'        => Auth::user()->id,
                'title'          => $card['title'] ?? null,
                'description'    => $card['description'] ?? null,
                'thumbnail_url'  => $thumbnailUrl,
            ];

            $localMedia = $this->apiService->success($dataToInsert, ['ad_media_id' => $snapMediaId], new AdMedia);

            $mediaIds[] = [
                'ad_media_id' => $localMedia['data']['id'],
                'media_id'    => $snapMediaId,
            ];
        }

        return ['success' => true, 'data' => $mediaIds];
    }

    /**
     * Snap generates video thumbnails automatically rather than accepting
     * an upload - this just retrieves the one it made. Best-effort: a
     * failure here shouldn't fail the whole campaign, so it returns null
     * instead of an error.
     */
    private function fetchThumbnail($mediaId)
    {
        $response = $this->apiService->get($this->config . "media/{$mediaId}/thumbnail", $this->header['data']);

        if (!$response['success']) {
            return null;
        }

        return $response['data']['url']
            ?? $response['data']['download_url']
            ?? $response['data']['thumbnail_url']
            ?? null;
    }

    private function storeCreative($platform, $request)
    {
        $mediaList = $request['media'] ?? [];

        if (empty($mediaList)) {
            return $this->errorResponse('At least one media item is required to create a creative.');
        }

        $isCarousel = $request['media_type'] === 'CAROUSEL' && count($mediaList) > 1;

        if ($isCarousel) {
            $creativeResult = $this->storeCompositeCreative($request, $mediaList);
        } else {
            $creativeResult = $this->storeSingleCreative($request, $mediaList[0]);
        }

        if (!$creativeResult['success']) {
            return $creativeResult;
        }

        $id = $creativeResult['data']['id'];
        $properties = $creativeResult['data']['properties'];

        $dataToInsert = [
            'user_id'         => Auth::id(),
            'ad_adgroup_id'   => $request['ad_adgroup_id'],
            'ad_creative_id'  => $id,
            'platform'        => 'snapchat',
            'ad_account_id'   => $this->account->id,
            'ad_campaign_id'  => $request['ad_campaign_id'],
            'name'            => $request['name'],
            'message'         => $request['description'] ?? null,
            'call_to_action'  => $request['call_to_action'] ?? null,
            'url'             => $request['target_link'] ?? null,
            'type'            => $isCarousel ? 'COMPOSITE' : $request['creative_type'],
            'headline'        => $request['description'] ?? null,
            'brand_name'      => $request['name'],
            'top_snap_media_id' => $isCarousel ? null : $mediaList[0]['media_id'],
            'web_view_properties'    => isset($properties['web_view_properties']) ? json_encode($properties['web_view_properties']) : null,
            'app_install_properties' => isset($properties['app_install_properties']) ? json_encode($properties['app_install_properties']) : null,
            'deep_link_properties'   => isset($properties['deep_link_properties']) ? json_encode($properties['deep_link_properties']) : null,
            'ad_to_lens_properties'    => isset($properties['ad_to_lens_properties']) ? json_encode($properties['ad_to_lens_properties']) : null,
            'ad_to_call_properties'    => isset($properties['ad_to_call_properties']) ? json_encode($properties['ad_to_call_properties']) : null,
            'ad_to_message_properties' => isset($properties['ad_to_message_properties']) ? json_encode($properties['ad_to_message_properties']) : null,
            'composite_properties'     => $isCarousel ? json_encode(['creative_ids' => $creativeResult['data']['card_creative_ids']]) : null,
        ];

        $creative = $this->apiService->success($dataToInsert, ['ad_creative_id' => $id], new AdCreative);

        foreach ($mediaList as $media) {
            $this->apiService->success(
                ['ad_media_id' => $media['ad_media_id'], 'ad_creative_id' => $creative['data']['id']],
                ['ad_media_id' => $media['ad_media_id'], 'ad_creative_id' => $creative['data']['id']],
                new AdCreativeMedia
            );
        }

        return $creative;
    }

    /**
     * A single non-carousel creative: one media item, one set of
     * type-specific properties (web_view/app_install/deep_link/etc).
     */
    private function storeSingleCreative($request, $media)
    {
        if (!$this->ensureMediaIsReady($media['media_id'])) {
            return $this->errorResponse("Media ID {$media['media_id']} is not ready yet. Please try again.");
        }

        $properties = $this->creativeProperties($request);

        $payload = array_merge([
            'name'          => $request['name'],
            'type'          => $request['creative_type'],
            'ad_account_id' => $this->account->ad_account_id,
            'headline'      => $request['description'],
            'brand_name'    => $request['name'],
            'top_snap_media_id' => $media['media_id'],
            'profile_properties' => ['profile_id' => $this->account->profile_id],
        ], $properties);

        $result = $this->parseSnapResponse(
            $this->apiService->post(
                $this->config . "adaccounts/{$this->account->ad_account_id}/creatives",
                $this->header['data'],
                ['creatives' => [$payload]]
            ),
            'creatives',
            'creative'
        );

        if (!$result['success']) {
            return $result;
        }

        return $this->successResponse(['id' => $result['data']['id'], 'properties' => $properties, 'card_creative_ids' => []]);
    }

    /**
     * TikTok/Facebook-style "carousel" doesn't exist natively on Snapchat -
     * the real equivalent is a COMPOSITE creative bundling several
     * standalone SNAP_AD creatives (one per image, each with its own
     * headline from the per-card title) via composite_properties.creative_ids.
     * The shared CTA/link/web-view etc still apply to each card, same as
     * they would to a single creative.
     */
    private function storeCompositeCreative($request, $mediaList)
    {
        $properties = $this->creativeProperties($request);
        $cardCreativeIds = [];

        foreach ($mediaList as $index => $media) {
            if (!$this->ensureMediaIsReady($media['media_id'])) {
                return $this->errorResponse("Media ID {$media['media_id']} is not ready yet. Please try again.");
            }

            $cardMedia = AdMedia::find($media['ad_media_id']);

            $cardPayload = array_merge([
                'name'          => $request['name'] . ' - Card ' . ($index + 1),
                'type'          => 'SNAP_AD',
                'ad_account_id' => $this->account->ad_account_id,
                'headline'      => $cardMedia?->title ?: $request['description'],
                'brand_name'    => $request['name'],
                'top_snap_media_id' => $media['media_id'],
                'profile_properties' => ['profile_id' => $this->account->profile_id],
            ], $properties);

            $cardResult = $this->parseSnapResponse(
                $this->apiService->post(
                    $this->config . "adaccounts/{$this->account->ad_account_id}/creatives",
                    $this->header['data'],
                    ['creatives' => [$cardPayload]]
                ),
                'creatives',
                'creative'
            );

            if (!$cardResult['success']) {
                return $cardResult;
            }

            $cardCreativeIds[] = $cardResult['data']['id'];
        }

        $compositePayload = [
            'name'          => $request['name'],
            'type'          => 'COMPOSITE',
            'ad_account_id' => $this->account->ad_account_id,
            'headline'      => $request['description'],
            'brand_name'    => $request['name'],
            'composite_properties' => ['creative_ids' => $cardCreativeIds],
        ];

        $result = $this->parseSnapResponse(
            $this->apiService->post(
                $this->config . "adaccounts/{$this->account->ad_account_id}/creatives",
                $this->header['data'],
                ['creatives' => [$compositePayload]]
            ),
            'creatives',
            'creative'
        );

        if (!$result['success']) {
            return $result;
        }

        return $this->successResponse([
            'id' => $result['data']['id'],
            'properties' => $properties,
            'card_creative_ids' => $cardCreativeIds,
        ]);
    }

    private function creativeProperties(array $request)
    {
        return match ($request['creative_type']) {
            'WEB_VIEW', 'LENS_WEB_VIEW' => array_filter([
                'call_to_action' => $request['call_to_action'] ?? null,
                'web_view_properties' => array_filter([
                    'url' => $request['target_link'] ?? null,
                ]),
            ]),
            'APP_INSTALL', 'LENS_APP_INSTALL' => array_filter([
                'call_to_action' => $request['call_to_action'] ?? null,
                'app_install_properties' => array_filter([
                    'app_name'        => $request['app_name'] ?? null,
                    'icon_media_id'   => $request['icon_media_id'] ?? null,
                    'ios_app_id'      => $request['ios_app_id'] ?? null,
                    'android_app_url' => $request['android_app_url'] ?? null,
                ]),
            ]),
            'DEEP_LINK', 'LENS_DEEP_LINK' => array_filter([
                'call_to_action' => $request['call_to_action'] ?? null,
                'deep_link_properties' => array_filter([
                    'app_name'              => $request['app_name'] ?? null,
                    'deep_link_uri'         => $request['deep_link_uri'] ?? null,
                    'fallback_type'         => $request['fallback_type'] ?? null,
                    'web_view_fallback_url' => $request['web_view_fallback_url'] ?? null,
                    'ios_app_id'            => $request['ios_app_id'] ?? null,
                    'android_app_url'       => $request['android_app_url'] ?? null,
                    'icon_media_id'         => $request['icon_media_id'] ?? null,
                ]),
            ]),
            'AD_TO_LENS' => array_filter([
                'call_to_action' => $request['call_to_action'] ?? null,
                'ad_to_lens_properties' => array_filter([
                    'lens_media_id' => $request['lens_media_id'] ?? null,
                ]),
            ]),
            'AD_TO_CALL' => array_filter([
                'call_to_action' => $request['call_to_action'] ?? null,
                'ad_to_call_properties' => array_filter([
                    'phone_number_id' => $request['phone_number_id'] ?? null,
                ]),
            ]),
            'AD_TO_MESSAGE' => array_filter([
                'call_to_action' => $request['call_to_action'] ?? null,
                'ad_to_message_properties' => array_filter([
                    'phone_number_id' => $request['phone_number_id'] ?? null,
                    'message'         => $request['message'] ?? null,
                ]),
            ]),
            'LEAD_GENERATION' => array_filter([
                'call_to_action' => $request['call_to_action'] ?? null,
            ]),
            'REMINDER' => [
                'call_to_action' => 'REMIND_ME',
            ],
            default => [],
        };
    }

    private function ensureMediaIsReady($mediaId)
    {
        $endpoint = $this->config . "media/{$mediaId}";
        $maxAttempts = 20;
        $attempt = 0;

        while ($attempt < $maxAttempts) {
            $response = $this->apiService->get($endpoint, $this->header['data']);

            if ($response['success']) {
                $mediaStatus = $response['data']['media'][0]['media']['media_status'] ?? null;

                if ($mediaStatus === 'READY') {
                    return true;
                }
            }

            sleep(2);
            $attempt++;
        }

        return false;
    }

    private function storeAd($platform, $request)
    {
        $endpoint = $this->config . "adsquads/{$request['adgroup_id']}/ads";

        $payload = [
            'ads' => [[
                'name'        => $request['name'],
                'ad_squad_id' => $request['adgroup_id'],
                'type'        => $this->adTypeFor($request),
                'creative_id' => $request['creative_id'],
                'status'      => 'PAUSED',
            ]]
        ];

        $result = $this->parseSnapResponse(
            $this->apiService->post($endpoint, $this->header['data'], $payload),
            'ads',
            'ad'
        );

        if (!$result['success']) {
            return $result;
        }

        $id = $result['data']['id'];

        $dataToInsert = [
            'user_id'        => Auth::user()->id,
            'ad_adgroup_id'  => $request['ad_adgroup_id'],
            'ad_creative_id' => $request['ad_creative_id'],
            'ad_id'          => $id,
            'status'         => false,
            'platform'       => 'snapchat',
            'type'           => $request['creative_type'],
            'ad_account_id'  => $this->account->id,
            'ad_campaign_id' => $request['ad_campaign_id'],
            'name'           => $request['name'],
            'call_to_action' => $request['call_to_action'] ?? null,
        ];

        return $this->apiService->success($dataToInsert, ['ad_id' => $id], new Ad);
    }

    private function adTypeFor($request)
    {
        if ($request['media_type'] === 'CAROUSEL') {
            return 'SNAP_AD';
        }

        return match ($request['creative_type']) {
            'APP_INSTALL', 'LENS_APP_INSTALL' => 'APP_INSTALL',
            'WEB_VIEW', 'LENS_WEB_VIEW'       => 'REMOTE_WEBPAGE',
            'DEEP_LINK', 'LENS_DEEP_LINK'     => 'DEEP_LINK',
            'LEAD_GENERATION'                 => 'LEAD_GENERATION',
            default                           => 'SNAP_AD',
        };
    }

    private function getHeaders()
    {
        if ($this->tokenIsValid($this->account->expires_at)) {
            $accessToken = $this->account->access_token;
        } else {
            $response = $this->refreshToken($this->account);

            if (!$response['success']) {
                return $response;
            }

            $accessToken = $response['data'];
        }

        return [
            'success' => true,
            'data' => [
                'Authorization' => "Bearer $accessToken",
                'Content-Type'  => 'application/json',
            ]
        ];
    }

    protected function tokenIsValid($expiresAt): bool
    {
        if (!$expiresAt) {
            return false;
        }

        return now()->lt(Carbon::parse($expiresAt));
    }

    public function refreshToken($account)
    {
        $endpoint = adminSetting('ads.snapchat.access_token');

        $response = $this->apiService->post($endpoint, ["Content-Type" => "application/x-www-form-urlencoded"], [
            'client_id'     => adminSetting('ads.snapchat.client_id'),
            'client_secret' => adminSetting('ads.snapchat.client_secret'),
            'refresh_token' => $account->refresh_token,
            'grant_type'    => 'refresh_token',
        ], 'form');

        $data = $response['data'];

        if ($response['success']) {
            $this->account->access_token = $data['access_token'];
            $this->account->expires_at = Carbon::now()->addSeconds($data['expires_in']);
            $this->account->refresh_token = $data['refresh_token'];
            $this->account->save();
            $this->account->refresh();

            return $this->successResponse($this->account->access_token);
        }

        return $this->errorResponse($data['error_description'] ?? 'Refresh Token Error');
    }

    private function errorResponse($error)
    {
        return ['success' => false, 'error' => $error];
    }

    private function successResponse($data)
    {
        return ['success' => true, 'data' => $data];
    }

    /**
     * Every Snapchat call goes through here so the "200 but
     * request_status=ERROR" check happens in one place. $collectionKey is
     * the wrapper key both the request and response use (eg. "campaigns");
     * $itemKey is the key the single created/updated object is nested
     * under inside each collection entry (eg. "campaign") - pass null when
     * the response has no such nesting (eg. the raw upload endpoint).
     */
    private function parseSnapResponse($response, string $collectionKey, ?string $itemKey)
    {
        if (!$response['success']) {
            return $this->errorResponse($response['data']['debug_message'] ?? $response['data']['display_message'] ?? 'Snapchat API request failed.');
        }

        if (($response['data']['request_status'] ?? null) === 'ERROR') {
            $reason = $response['data'][$collectionKey][0]['sub_request_error_reason'] ?? 'Snapchat API returned an error.';
            return $this->errorResponse($reason);
        }

        $entry = $response['data'][$collectionKey][0] ?? $response['data'];
        $data = $itemKey ? ($entry[$itemKey] ?? $entry) : $entry;

        return $this->successResponse($data);
    }

    private function getMediaType($fileExtension)
    {
        $videoExtensions = ['mp4', 'avi', 'mov', 'mkv', 'flv'];

        if (in_array(strtolower($fileExtension), $videoExtensions)) {
            return 'VIDEO';
        }

        return 'IMAGE';
    }

    public function update($platform, $id, $request)
    {
        $response = $this->updateCampaign($platform, $id, $request);

        if (!$response['success']) {
            return $response;
        }

        $campaign = $response['data'];
        $request['campaign_id'] = $campaign['ad_campaign_id'];
        $request['ad_campaign_id'] = $campaign['id'];

        $adGroupResponse = $this->updateAdGroup($platform, $campaign['id'], $request);

        if (!$adGroupResponse['success']) {
            return $adGroupResponse;
        }

        $request['adgroup_id'] = $adGroupResponse['data']['ad_adgroup_id'];
        $request['ad_adgroup_id'] = $adGroupResponse['data']['id'];

        if (!empty($request['media'])) {
            $response = $this->storeMedia($platform, $request);

            if (!$response['success']) {
                return $response;
            }

            $request['media'] = $response['data'];
        } else {
            $adGroup = AdAdGroup::find($adGroupResponse['data']['id']);
            $existingMedia = $adGroup?->creatives->first()?->media ?? collect();
            $request['media'] = $existingMedia->map(fn($m) => [
                'ad_media_id' => $m->id,
                'media_id'    => $m->ad_media_id,
            ])->toArray();
        }

        $creativeResponse = $this->updateCreative($platform, $request, $adGroupResponse['data']);

        if (!$creativeResponse['success']) {
            return $creativeResponse;
        }

        $request['creative_id'] = $creativeResponse['data']['ad_creative_id'];
        $request['ad_creative_id'] = $creativeResponse['data']['id'];

        return $this->updateAd($platform, $request, $campaign);
    }

    private function updateCampaign($platform, $id, $request)
    {
        $campaign = AdCampaign::findOrFail($id);

        $result = $this->parseSnapResponse(
            $this->apiService->put(
                $this->config . 'adaccounts/' . $this->account->ad_account_id . '/campaigns',
                $this->header['data'],
                ['campaigns' => [[
                    'id'   => $campaign->ad_campaign_id,
                    'name' => $request['name'],
                ]]]
            ),
            'campaigns',
            'campaign'
        );

        if (!$result['success']) {
            return $result;
        }

        return $this->apiService->success(
            ['name' => $request['name']],
            ['ad_campaign_id' => $campaign->ad_campaign_id],
            new AdCampaign
        );
    }

    private function updateAdGroup($platform, $campaignId, $request)
    {
        $adGroup = AdAdGroup::whereAdCampaignId($campaignId)->firstOrFail();

        [$demographicSpec, $geos, $countries] = $this->buildTargeting($request);

        $adSquad = [
            'id'   => $adGroup->ad_adgroup_id,
            'name' => $request['name'],
            'targeting' => [
                'demographics' => [$demographicSpec],
                'geos'         => $geos,
            ],
            'bid_strategy'      => $request['bid_strategy'],
            'optimization_goal' => $request['optimization_goal'],
            'start_time'        => Carbon::parse($request['start_time'])->utc()->format('Y-m-d\TH:i:s.v\Z'),
            'end_time'          => Carbon::parse($request['end_time'])->utc()->format('Y-m-d\TH:i:s.v\Z'),
        ];

        $budgetInMicros = (int) ((float) $request['budget'] * 1000000);

        if (($request['budget_mode'] ?? 'daily') === 'daily') {
            $adSquad['daily_budget_micro'] = $budgetInMicros;
        } else {
            $adSquad['lifetime_budget_micro'] = $budgetInMicros;
        }

        if (in_array($request['bid_strategy'], ['TARGET_COST', 'LOWEST_COST_WITH_MAX_BID'], true) && !empty($request['bid_amount'])) {
            $adSquad['bid_micro'] = (int) bcmul((string) $request['bid_amount'], '1000000', 0);
        }

        if (!empty($request['pixel_id'])) {
            $adSquad['pixel_id'] = $request['pixel_id'];
        }

        $result = $this->parseSnapResponse(
            $this->apiService->put(
                $this->config . 'campaigns/' . $campaignId . '/adsquads',
                $this->header['data'],
                ['adsquads' => [$adSquad]]
            ),
            'adsquads',
            'adsquad'
        );

        if (!$result['success']) {
            return $result;
        }

        $dataToInsert = [
            'ad_campaign_id'      => $campaignId,
            'user_id'             => Auth::user()->id,
            'ad_adgroup_id'       => $adGroup->ad_adgroup_id,
            'ad_account_id'       => $this->account->id,
            'name'                => $request['name'],
            'location_ids'        => json_encode($countries),
            'platform'            => $platform,
            'gender'              => $request['gender'],
            'languages'           => json_encode($request['languages']),
            'budget_mode'         => $request['budget_mode'],
            'bid_price'           => $request['bid_amount'] ?? null,
            'bid_strategy'        => $request['bid_strategy'],
            'optimization_goal'   => $request['optimization_goal'],
            'schedule_start_time' => $request['start_time'],
            'schedule_end_time'   => $request['end_time'],
            'budget'              => $request['final_budget'],
            'age_groups'          => json_encode($request['age_range']),
        ];

        return $this->apiService->success($dataToInsert, ['ad_adgroup_id' => $adGroup->ad_adgroup_id], new AdAdGroup);
    }

    /**
     * If new media was uploaded, the creative is rebuilt from scratch
     * (Snapchat's PUT expects the full object, and there's no documented
     * way to swap a creative's top_snap_media_id/composite cards
     * in-place) - otherwise this just patches the mutable fields
     * (name/headline/CTA) on the existing creative.
     */
    private function updateCreative($platform, $request, $adGroup)
    {
        $existingCreative = $adGroup->creatives->first();

        if (!$existingCreative) {
            return $this->storeCreative($platform, $request);
        }

        if (!empty($request['media'])) {
            return $this->storeCreative($platform, $request);
        }

        $result = $this->parseSnapResponse(
            $this->apiService->put(
                $this->config . "adaccounts/{$this->account->ad_account_id}/creatives",
                $this->header['data'],
                ['creatives' => [array_merge([
                    'id'       => $existingCreative->ad_creative_id,
                    'name'     => $request['name'],
                    'headline' => $request['description'],
                    'brand_name' => $request['name'],
                ], $this->creativeProperties($request))]]
            ),
            'creatives',
            'creative'
        );

        if (!$result['success']) {
            return $result;
        }

        $existingCreative->update([
            'name'           => $request['name'],
            'headline'       => $request['description'] ?? null,
            'message'        => $request['description'] ?? null,
            'call_to_action' => $request['call_to_action'] ?? null,
            'url'            => $request['target_link'] ?? null,
        ]);

        return $this->successResponse(['ad_creative_id' => $existingCreative->ad_creative_id, 'id' => $existingCreative->id]);
    }

    private function updateAd($platform, $request, $campaign)
    {
        $ad = Ad::whereAdCampaignId($campaign['id'])->firstOrFail();

        $result = $this->parseSnapResponse(
            $this->apiService->put(
                $this->config . "adsquads/{$request['adgroup_id']}/ads",
                $this->header['data'],
                ['ads' => [[
                    'id'          => $ad->ad_id,
                    'name'        => $request['name'],
                    'creative_id' => $request['creative_id'],
                ]]]
            ),
            'ads',
            'ad'
        );

        if (!$result['success']) {
            return $result;
        }

        return $this->apiService->success(
            [
                'name'           => $request['name'],
                'call_to_action' => $request['call_to_action'] ?? null,
                'ad_creative_id' => $request['ad_creative_id'],
            ],
            ['ad_id' => $ad->ad_id],
            new Ad
        );
    }

    /**
     * Pause/reactivate without deleting anything. Snapchat's collection PUT
     * only requires the id plus whatever field is changing, so this sends
     * the minimum payload rather than the full object.
     */
    public function updateStatus($id, $status)
    {
        $campaign = AdCampaign::findOrFail($id);
        $adGroup = AdAdGroup::whereAdCampaignId($id)->first();
        $ad = Ad::whereAdCampaignId($id)->first();

        $result = $this->parseSnapResponse(
            $this->apiService->put(
                $this->config . 'adaccounts/' . $this->account->ad_account_id . '/campaigns',
                $this->header['data'],
                ['campaigns' => [['id' => $campaign->ad_campaign_id, 'status' => $status]]]
            ),
            'campaigns',
            'campaign'
        );

        if (!$result['success']) {
            return $result;
        }

        $isActive = $status === 'ACTIVE';

        if ($adGroup) {
            $this->parseSnapResponse(
                $this->apiService->put(
                    $this->config . 'campaigns/' . $campaign->ad_campaign_id . '/adsquads',
                    $this->header['data'],
                    ['adsquads' => [['id' => $adGroup->ad_adgroup_id, 'status' => $status]]]
                ),
                'adsquads',
                'adsquad'
            );
            $adGroup->update(['status' => $isActive]);
        }

        if ($ad) {
            $this->parseSnapResponse(
                $this->apiService->put(
                    $this->config . "adsquads/{$adGroup?->ad_adgroup_id}/ads",
                    $this->header['data'],
                    ['ads' => [['id' => $ad->ad_id, 'status' => $status]]]
                ),
                'ads',
                'ad'
            );
            $ad->update(['status' => $isActive]);
        }

        $campaign->update(['status' => $isActive]);

        return $this->successResponse(['status' => $status]);
    }

    public function destroy($platform, $id)
    {
        $campaign = AdCampaign::with(['adGroups.creatives.media', 'ads'])->findOrFail($id);

        $adGroup = $campaign->adGroups->first();
        $creative = $adGroup?->creatives->first();
        $media = $creative?->media ?? collect();
        $ad = $campaign->ads->first();

        if ($ad) {
            $result = $this->parseSnapResponse(
                $this->apiService->delete($this->config . "ads/{$ad->ad_id}", $this->header['data']),
                'ads',
                null
            );

            if (!$result['success']) {
                return $result;
            }

            $ad->delete();
        }

        if ($creative) {
            // Composite creatives bundle several standalone card creatives -
            // remove those too, best-effort, before the composite itself.
            $cardIds = json_decode($creative->composite_properties ?? '{}', true)['creative_ids'] ?? [];

            foreach ($cardIds as $cardId) {
                $this->apiService->delete($this->config . "creatives/{$cardId}", $this->header['data']);
            }

            $result = $this->parseSnapResponse(
                $this->apiService->delete($this->config . "creatives/{$creative->ad_creative_id}", $this->header['data']),
                'creatives',
                null
            );

            if (!$result['success']) {
                return $result;
            }

            $creative->delete();
        }

        // No documented endpoint exists to delete an uploaded media asset
        // from Snapchat's library, so these are only removed locally.
        foreach ($media as $each) {
            $each->delete();
        }

        if ($adGroup) {
            $result = $this->parseSnapResponse(
                $this->apiService->delete($this->config . "adsquads/{$adGroup->ad_adgroup_id}", $this->header['data']),
                'adsquads',
                null
            );

            if (!$result['success']) {
                return $result;
            }

            $adGroup->delete();
        }

        if ($campaign->ad_campaign_id) {
            $result = $this->parseSnapResponse(
                $this->apiService->delete($this->config . "campaigns/{$campaign->ad_campaign_id}", $this->header['data']),
                'campaigns',
                null
            );

            if (!$result['success']) {
                return $result;
            }
        }

        $campaign->delete();

        return $this->successResponse(null);
    }
}
