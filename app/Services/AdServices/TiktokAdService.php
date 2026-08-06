<?php

namespace App\Services\AdServices;

use App\Models\Admin\AdAccount;
use App\Models\Admin\AdCampaign;
use App\Models\Admin\AdAdGroup;
use App\Models\Admin\AdMedia;
use App\Models\Admin\Ad;
use App\Models\Admin\AdCreative;
use App\Models\Admin\AdCreativeMedia;
use App\Services\ApiService;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use App\Models\Country;

/**
 * TikTok Business API (v1.3) integration.
 *
 * Unlike Facebook's Graph API, TikTok answers almost every request with
 * HTTP 200 even when the operation failed - the real result lives in the
 * JSON envelope's `code` (0 = success) and `message` fields. Every call in
 * this class goes through callTikTok()/callTikTokMultipart(), which check
 * that envelope instead of the HTTP status.
 *
 * Endpoint paths and request-body field names below were verified against
 * TikTok's official business-api-sdk docs (github.com/tiktok/tiktok-business-api-sdk).
 * A handful of details TikTok doesn't document field-for-field in those
 * schemas - the exact response envelope key names for the region/identity
 * lookups, and a couple of sane operational defaults (pacing, schedule type)
 * - are implemented defensively and called out in code comments; verify
 * those against a real TikTok sandbox account before relying on them for
 * live ad spend.
 */
class TiktokAdService
{
    protected $account, $config, $apiService, $header;

    public function __construct(AdAccount $account, ApiService $apiService)
    {
        $this->apiService = $apiService;
        $this->account = $account->wherePlatform('tiktok')->whereUserId(Auth::user()->id)->first();

        // adminSetting('ads.tiktok.base_url') is already correctly configured
        // as https://business-api.tiktok.com/open_api/v1.3/ - endpoints below
        // are built by appending the resource path to it directly (TikTok
        // carries the advertiser id as a body/query field, never in the URL
        // path, unlike Facebook's /act_{accountId}/... convention).
        $this->config = adminSetting('ads.tiktok.base_url');

        if ($this->account) {
            $this->header = $this->getHeaders();
        }
    }

    public function redirect($platform, $state)
    {
        $clientId = adminSetting('ads.tiktok.client_id');

        $tiktokAuthUrl = "https://business-api.tiktok.com/portal/auth?" . http_build_query([
            'app_id' => $clientId,
            'state' => $state,
            'redirect_uri' => $this->getCallbackUrl(),
        ]);

        return redirect()->away($tiktokAuthUrl);
    }

    private function getCallbackUrl()
    {
        return route('admin.ads.platform.callback', 'tiktok');
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

        // TikTok's ad/create/ builds the creative and the ad in one call -
        // there's no separate "create a creative" resource like Facebook's
        // /adcreatives. storeAd() makes that one real API call and persists
        // both the local AdCreative and Ad rows from its single response.
        return $this->storeAd($platform, $request);
    }

    private function storeCampaign($platform, $request)
    {
        $endpoint = $this->config . 'campaign/create/';

        $payload = [
            'advertiser_id'      => $this->account->ad_account_id,
            'campaign_name'      => $request['name'],
            'objective_type'     => $request['objective'],
            'budget_mode'        => $request['budget_mode'],
            'budget'             => (float) $request['budget'],
            'operation_status'   => 'DISABLE',
            // Budget lives at the ad group level (see storeAdGroup) rather
            // than being campaign-optimized.
            'budget_optimize_on' => false,
        ];

        if ($request['objective'] === 'APP_PROMOTION' && !empty($request['app_promotion_type'])) {
            $payload['app_promotion_type'] = $request['app_promotion_type'];
        }

        $result = $this->callTikTok('post', $endpoint, $payload);

        if (!$result['success']) {
            return $result;
        }

        $id = $result['data']['campaign_id'] ?? null;

        if (!$id) {
            return $this->errorResponse('TikTok did not return a campaign_id.');
        }

        $dataToInsert = [
            'ad_campaign_id'      => $id,
            'user_id'             => Auth::user()->id,
            'ad_account_id'       => $this->account->id,
            'name'                => $request['name'],
            'objective'           => $request['objective'],
            'platform'            => $platform,
            'start_time'          => $request['start_time'],
            'end_time'            => $request['end_time'],
            'budget_mode'         => $request['budget_mode'],
            'budget'              => $request['budget'],
            'app_promotion_type'  => $request['app_promotion_type'] ?? null,
            'status'              => false,
        ];

        return $this->apiService->success(
            $dataToInsert,
            ['ad_campaign_id' => $id],
            new AdCampaign
        );
    }

    private function storeAdGroup($platform, $request)
    {
        $endpoint = $this->config . 'adgroup/create/';

        $locationIds = $this->resolveLocationIds($request['countries'], $request['objective']);

        // Failing to resolve at least one location would otherwise send an
        // adgroup with no location_ids - TikTok can treat that as "no
        // restriction" and target far wider than the countries the user
        // actually picked, so refuse rather than silently going broad.
        if (empty($locationIds)) {
            return $this->errorResponse('Could not resolve the selected countries to TikTok location IDs. Please double-check the Countries selection.');
        }

        $payload = [
            'advertiser_id'      => $this->account->ad_account_id,
            'campaign_id'        => $request['campaign_id'],
            'adgroup_name'       => $request['name'],
            'promotion_type'     => $request['promotion_type'] ?? null,
            'placement_type'     => 'PLACEMENT_TYPE_AUTOMATIC',
            'budget_mode'        => $request['budget_mode'],
            'budget'             => (float) $request['budget'],
            'schedule_type'      => 'SCHEDULE_START_END',
            'schedule_start_time' => Carbon::parse($request['start_time'])->format('Y-m-d H:i:s'),
            'schedule_end_time'  => Carbon::parse($request['end_time'])->endOfDay()->format('Y-m-d H:i:s'),
            'optimization_goal'  => $request['optimization_goal'],
            'billing_event'      => $request['billing_event'],
            'pacing'             => 'PACING_MODE_SMOOTH',
            'location_ids'       => $locationIds,
            'gender'             => $request['gender'] ?: 'GENDER_UNLIMITED',
            'age_groups'         => array_values($request['age_range'] ?? []),
            'languages'          => array_values($request['languages'] ?? []),
        ];

        if (!empty($request['bid_amount'])) {
            $payload['bid_price'] = (float) $request['bid_amount'];
        }

        if (!empty($request['promotion_target_type'])) {
            $payload['promotion_target_type'] = $request['promotion_target_type'];
        }

        if ($request['objective'] === 'APP_PROMOTION' && !empty($request['app_id'])) {
            $payload['app_id'] = $request['app_id'];
        }

        if (in_array($request['optimization_goal'], ['CONVERT', 'VALUE'], true) && !empty($request['pixel_id'])) {
            $payload['pixel_id'] = $request['pixel_id'];
        }

        if (!empty($request['optimization_event'])) {
            $payload['optimization_event'] = $request['optimization_event'];
        }

        $result = $this->callTikTok('post', $endpoint, $payload);

        if (!$result['success']) {
            return $result;
        }

        $id = $result['data']['adgroup_id'] ?? null;

        if (!$id) {
            return $this->errorResponse('TikTok did not return an adgroup_id.');
        }

        $dataToInsert = [
            'ad_campaign_id'        => $request['ad_campaign_id'],
            'user_id'               => Auth::user()->id,
            'ad_adgroup_id'         => $id,
            'ad_account_id'         => $this->account->id,
            'name'                  => $request['name'],
            'promotion_type'        => $request['promotion_type'] ?? null,
            'promotion_target_type' => $request['promotion_target_type'] ?? null,
            'placement_type'        => 'PLACEMENT_TYPE_AUTOMATIC',
            'location_ids'          => json_encode($locationIds),
            'platform'              => $platform,
            'gender'                => $payload['gender'],
            'languages'             => json_encode($payload['languages']),
            'age_groups'            => json_encode($payload['age_groups']),
            'budget_mode'           => $request['budget_mode'],
            'budget'                => $request['budget'],
            'schedule_type'         => $payload['schedule_type'],
            'schedule_start_time'   => $payload['schedule_start_time'],
            'schedule_end_time'     => $payload['schedule_end_time'],
            'optimization_goal'     => $request['optimization_goal'],
            'billing_event'         => $request['billing_event'],
            'bid_price'             => $request['bid_amount'] ?? null,
            'pacing'                => $payload['pacing'],
            'objective'             => $request['objective'],
            'status'                => false,
        ];

        return $this->apiService->success(
            $dataToInsert,
            ['ad_adgroup_id' => $id],
            new AdAdGroup
        );
    }

    private function storeMedia($platform, $request)
    {
        $mediaIds = [];

        // Standard TikTok Carousel Ads share one caption/CTA/link across all
        // cards (unlike Facebook's per-card child_attachments), so there's
        // nothing per-card to decode here - carousel_cards, if present, only
        // carries an optional per-image title/description for our own
        // records (ad_media.title/description), not anything TikTok's API
        // itself accepts per image.
        $cards = $request['media_type'] === 'CAROUSEL'
            ? (json_decode($request['carousel_cards'] ?? '[]', true) ?: [])
            : [];

        foreach ($request['media'] as $index => $media) {
            $extension = strtolower($media->getClientOriginalExtension());
            $mediaType = $this->getMediaType($extension);
            $fileName = time() . '_' . uniqid() . '.' . $extension;
            $s3Path = "uploads/{$platform}/{$mediaType}/{$fileName}";

            Storage::disk('r2')->put(
                $s3Path,
                file_get_contents($media->getRealPath()),
                ['visibility' => 'public']
            );

            $filePath = Storage::disk('r2')->url($s3Path);

            $result = $mediaType === 'VIDEO'
                ? $this->uploadVideo($media, $fileName)
                : $this->uploadImage($media, $fileName);

            if (!$result['success']) {
                return $result;
            }

            $card = $cards[$index] ?? [];

            $dataToInsert = [
                'ad_media_id'       => $result['data']['id'],
                'ad_account_id'     => $this->account->id,
                'ad_campaign_id'    => $request['ad_campaign_id'],
                'platform'          => $platform,
                'name'              => $fileName,
                'url'               => $result['data']['url'] ?? $filePath,
                'download_link'     => $result['data']['url'] ?? $filePath,
                'type'              => $mediaType,
                'status'            => false,
                'file_name'         => $fileName,
                'image_category'    => $mediaType,
                'signature'         => $result['data']['id'],
                'upload_by_type'    => 'UPLOAD_BY_FILE',
                'file_id'           => $result['data']['id'],
                'user_id'           => Auth::user()->id,
                'ad_format'         => $request['media_type'],
                'title'             => $card['title'] ?? null,
                'description'       => $card['description'] ?? null,
            ];

            $mediaRecord = $this->apiService->success(
                $dataToInsert,
                ['ad_media_id' => $result['data']['id']],
                new AdMedia
            );

            $mediaIds[] = [
                'ad_media_id' => $mediaRecord['data']['id'],
                'media_id'    => $result['data']['id'],
            ];
        }

        return ['success' => true, 'data' => $mediaIds];
    }

    private function uploadImage($media, $fileName)
    {
        $endpoint = $this->config . 'file/image/ad/upload/';

        $result = $this->callTikTokMultipart($endpoint, [
            'advertiser_id'   => $this->account->ad_account_id,
            'upload_type'     => 'UPLOAD_BY_FILE',
            'file_name'       => $fileName,
            'image_signature' => md5_file($media->getRealPath()),
        ], [[
            'name'       => 'image_file',
            'media_file' => $media->getRealPath(),
            'file_name'  => $fileName,
        ]]);

        if (!$result['success']) {
            return $result;
        }

        return $this->successResponse([
            'id'  => $result['data']['image_id'] ?? null,
            'url' => $result['data']['image_url'] ?? null,
        ]);
    }

    private function uploadVideo($media, $fileName)
    {
        $endpoint = $this->config . 'file/video/ad/upload/';

        $result = $this->callTikTokMultipart($endpoint, [
            'advertiser_id'   => $this->account->ad_account_id,
            'upload_type'     => 'UPLOAD_BY_FILE',
            'file_name'       => $fileName,
            'video_signature' => md5_file($media->getRealPath()),
        ], [[
            'name'       => 'video_file',
            'media_file' => $media->getRealPath(),
            'file_name'  => $fileName,
        ]]);

        if (!$result['success']) {
            return $result;
        }

        return $this->successResponse([
            'id'  => $result['data']['video_id'] ?? null,
            'url' => $result['data']['video_cover_url'] ?? null,
        ]);
    }

    /**
     * TikTok's ad/create/ produces the creative and the ad together, so
     * there's no local-only "build a creative" step worth its own remote
     * call. This just carries the assembled creative payload forward to
     * storeAd(), which makes the real call and persists both DB rows.
     */
    private function storeCreative($platform, $request)
    {
        return $this->successResponse($this->buildCreative($request));
    }

    private function buildCreative($request)
    {
        $creative = [
            'ad_name'          => $request['name'],
            'ad_text'          => $request['description'] ?? '',
            'call_to_action'   => $request['call_to_action'],
            'landing_page_url' => $request['target_link'],
            // TikTok Identity is mandatory on every ad. This app has no
            // identity-discovery UI, so it reuses the "Page Id" field (the
            // TikTok blade relabels it "TikTok Identity ID") as a manually
            // entered identity_id, defaulting to identity_type TT_USER (a
            // verified TikTok profile) rather than CUSTOMIZED_USER, which
            // TikTok has been phasing out.
            'identity_id'      => $request['page_id'],
            'identity_type'    => 'TT_USER',
        ];

        if ($request['media_type'] === 'VIDEO') {
            $creative['ad_format'] = 'SINGLE_VIDEO';
            $creative['video_id'] = $request['media'][0]['media_id'];
        } elseif ($request['media_type'] === 'CAROUSEL') {
            $creative['ad_format'] = 'CAROUSEL_ADS';
            $creative['image_ids'] = array_column($request['media'], 'media_id');
        } else {
            $creative['ad_format'] = 'SINGLE_IMAGE';
            $creative['image_ids'] = [$request['media'][0]['media_id']];
        }

        return $creative;
    }

    private function storeAd($platform, $request)
    {
        $endpoint = $this->config . 'ad/create/';

        $creativeResponse = $this->storeCreative($platform, $request);
        $creative = $creativeResponse['data'];

        $result = $this->callTikTok('post', $endpoint, [
            'advertiser_id' => $this->account->ad_account_id,
            'adgroup_id'    => $request['adgroup_id'],
            'creatives'     => [$creative],
        ]);

        if (!$result['success']) {
            return $result;
        }

        // ad/create/ typically returns ad_ids as an array (creatives[] can
        // create more than one ad in a single call); we only ever send one.
        $adId = $result['data']['ad_ids'][0]
            ?? $result['data']['ad_id']
            ?? null;

        if (!$adId) {
            return $this->errorResponse('TikTok did not return an ad_id.');
        }

        // TikTok has no separate remote "creative" resource - the AdCreative
        // row is a local-only record of what was submitted, keyed off the
        // same ad_id, so the existing creative->media pivot / destroy()
        // traversal keeps working the same way it does for other platforms.
        $creativeRecord = $this->apiService->success(
            [
                'user_id'         => Auth::user()->id,
                'ad_adgroup_id'   => $request['ad_adgroup_id'],
                'ad_creative_id'  => $adId,
                'platform'        => $platform,
                'ad_account_id'   => $this->account->id,
                'ad_campaign_id'  => $request['ad_campaign_id'],
                'name'            => $request['name'],
                'ad_format'       => $creative['ad_format'],
                'message'         => $request['description'] ?? null,
                'page_id'         => $request['page_id'] ?? null,
                'call_to_action'  => $request['call_to_action'] ?? null,
                'url'             => $request['target_link'] ?? null,
                'type'            => $request['media_type'],
            ],
            ['ad_creative_id' => $adId],
            new AdCreative
        );

        foreach ($request['media'] as $media) {
            $this->apiService->success(
                ['ad_media_id' => $media['ad_media_id'], 'ad_creative_id' => $creativeRecord['data']['id']],
                ['ad_media_id' => $media['ad_media_id'], 'ad_creative_id' => $creativeRecord['data']['id']],
                new AdCreativeMedia
            );
        }

        return $this->apiService->success(
            [
                'user_id'         => Auth::user()->id,
                'ad_adgroup_id'   => $request['ad_adgroup_id'],
                'ad_creative_id'  => $creativeRecord['data']['id'],
                'ad_id'           => $adId,
                'status'          => false,
                'platform'        => $platform,
                'ad_account_id'   => $this->account->id,
                'ad_campaign_id'  => $request['ad_campaign_id'],
                'name'            => $request['name'],
                'call_to_action'  => $request['call_to_action'],
                'ad_format'       => $creative['ad_format'],
            ],
            ['ad_id' => $adId],
            new Ad
        );
    }

    private function getHeaders()
    {
        // TikTok's oauth2/access_token/ exchange (app_id + secret + auth_code)
        // returns a long-lived business API token with no documented refresh
        // flow, matching this account's stored refresh_token being null - so
        // unlike Facebook, there's no periodic-refresh step to attempt here.
        // An expired/invalid token surfaces as a normal TikTok API error
        // (non-zero `code`) from whatever call uses it, which the caller
        // already handles.
        return [
            'success' => true,
            'data' => [
                'Access-Token' => $this->account->access_token,
                'Content-Type' => 'application/json',
            ]
        ];
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
     * Every non-file TikTok call goes through here so the envelope check
     * (code === 0) happens in exactly one place.
     */
    private function callTikTok(string $method, string $endpoint, array $payload = [])
    {
        $response = $this->apiService->{$method}($endpoint, $this->header['data'], $payload, 'json');

        return $this->parseTikTokResponse($response);
    }

    private function callTikTokMultipart(string $endpoint, array $payload, array $files)
    {
        // Multipart uploads must not carry the JSON content-type header used
        // for every other call.
        $authHeader = ['Access-Token' => $this->header['data']['Access-Token']];

        $response = $this->apiService->post($endpoint, $authHeader, $payload, 'multipart', $files);

        return $this->parseTikTokResponse($response);
    }

    private function parseTikTokResponse($response)
    {
        if (!$response['success']) {
            return $this->errorResponse($response['error'] ?? 'Request to TikTok failed.');
        }

        $body = $response['data'];

        if (!isset($body['code']) || (int) $body['code'] !== 0) {
            return $this->errorResponse($body['message'] ?? 'TikTok API returned an error.');
        }

        return $this->successResponse($body['data'] ?? []);
    }

    /**
     * TikTok targets countries via its own numeric location_ids (resolved
     * through tool/region/), not ISO codes like Facebook - this app's
     * countries table only stores ISO codes, so they're resolved dynamically
     * by matching country name against the region list rather than via a
     * hardcoded ISO->location_id table that would silently go stale.
     */
    private function resolveLocationIds(array $countryIds, string $objective): array
    {
        $countryNames = Country::whereIn('id', $countryIds)
            ->pluck('name')
            ->map(fn($name) => strtolower($name))
            ->toArray();

        if (empty($countryNames)) {
            return [];
        }

        $result = $this->callTikTok('get', $this->config . 'tool/region/', [
            'advertiser_id'  => $this->account->ad_account_id,
            'placements'     => json_encode(['PLACEMENT_TIKTOK']),
            'objective_type' => $objective,
            'level_range'    => 'TO_COUNTRY',
        ]);

        if (!$result['success']) {
            return [];
        }

        $regions = $result['data']['region_info']
            ?? $result['data']['list']
            ?? $result['data']['regions']
            ?? [];

        return collect($regions)
            ->filter(fn($region) => in_array(strtolower($region['name'] ?? ''), $countryNames, true))
            ->pluck('location_id')
            ->filter()
            ->values()
            ->toArray();
    }

    private function getMediaType($fileExtension)
    {
        $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp'];
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

        return $this->updateAd($platform, $request, $campaign, $adGroupResponse['data']);
    }

    private function updateCampaign($platform, $id, $request)
    {
        $campaign = AdCampaign::findOrFail($id);

        $endpoint = $this->config . 'campaign/update/';

        $result = $this->callTikTok('post', $endpoint, [
            'advertiser_id' => $this->account->ad_account_id,
            'campaign_id'   => $campaign->ad_campaign_id,
            'campaign_name' => $request['name'],
        ]);

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

        $locationIds = $this->resolveLocationIds($request['countries'], $request['objective']);

        if (empty($locationIds)) {
            return $this->errorResponse('Could not resolve the selected countries to TikTok location IDs. Please double-check the Countries selection.');
        }

        $endpoint = $this->config . 'adgroup/update/';

        $payload = [
            'advertiser_id'      => $this->account->ad_account_id,
            'adgroup_id'         => $adGroup->ad_adgroup_id,
            'adgroup_name'       => $request['name'],
            'schedule_start_time' => Carbon::parse($request['start_time'])->format('Y-m-d H:i:s'),
            'schedule_end_time'  => Carbon::parse($request['end_time'])->endOfDay()->format('Y-m-d H:i:s'),
            'budget'             => (float) $request['budget'],
            'location_ids'       => $locationIds,
            'gender'             => $request['gender'] ?: 'GENDER_UNLIMITED',
            'age_groups'         => array_values($request['age_range'] ?? []),
            'languages'          => array_values($request['languages'] ?? []),
        ];

        if (!empty($request['bid_amount'])) {
            $payload['bid_price'] = (float) $request['bid_amount'];
        }

        $result = $this->callTikTok('post', $endpoint, $payload);

        if (!$result['success']) {
            return $result;
        }

        $dataToInsert = [
            'ad_campaign_id'      => $campaignId,
            'user_id'             => Auth::user()->id,
            'ad_adgroup_id'       => $adGroup->ad_adgroup_id,
            'ad_account_id'       => $this->account->id,
            'name'                => $request['name'],
            'location_ids'        => json_encode($locationIds),
            'platform'            => $platform,
            'gender'              => $payload['gender'],
            'languages'           => json_encode($payload['languages']),
            'age_groups'          => json_encode($payload['age_groups']),
            'budget'              => $request['budget'],
            'schedule_start_time' => $payload['schedule_start_time'],
            'schedule_end_time'   => $payload['schedule_end_time'],
            'bid_price'           => $request['bid_amount'] ?? null,
            'status'              => false,
        ];

        return $this->apiService->success(
            $dataToInsert,
            ['ad_adgroup_id' => $adGroup->ad_adgroup_id],
            new AdAdGroup
        );
    }

    private function updateAd($platform, $request, $campaign, $adGroup)
    {
        $existingAd = Ad::whereAdCampaignId($campaign['id'])->firstOrFail();
        $creative = $this->buildCreative($request);

        $result = $this->callTikTok('post', $this->config . 'ad/update/', [
            'advertiser_id' => $this->account->ad_account_id,
            'ad_id'         => $existingAd->ad_id,
            'creative'      => $creative,
        ]);

        if (!$result['success']) {
            return $result;
        }

        $existingCreative = AdCreative::whereAdCreativeId($existingAd->ad_creative_id)->first();

        if ($existingCreative) {
            $existingCreative->update([
                'name'           => $request['name'],
                'ad_format'      => $creative['ad_format'],
                'message'        => $request['description'] ?? null,
                'page_id'        => $request['page_id'] ?? null,
                'call_to_action' => $request['call_to_action'] ?? null,
                'url'            => $request['target_link'] ?? null,
                'type'           => $request['media_type'],
            ]);

            foreach ($request['media'] as $media) {
                $this->apiService->success(
                    ['ad_media_id' => $media['ad_media_id'], 'ad_creative_id' => $existingCreative->id],
                    ['ad_media_id' => $media['ad_media_id'], 'ad_creative_id' => $existingCreative->id],
                    new AdCreativeMedia
                );
            }
        }

        return $this->apiService->success(
            [
                'name'           => $request['name'],
                'call_to_action' => $request['call_to_action'],
                'ad_format'      => $creative['ad_format'],
            ],
            ['ad_id' => $existingAd->ad_id],
            new Ad
        );
    }

    public function destroy($platform, $id)
    {
        $campaign = AdCampaign::with([
            'adGroups.creatives.media',
            'ads'
        ])->findOrFail($id);

        $adGroup = $campaign->adGroups->first();
        $ad = $campaign->ads->first();

        // TikTok has no hard-delete endpoint for campaign/adgroup/ad - the
        // real mechanism is a status update to DELETE. There's also no
        // documented endpoint to delete an uploaded image/video from the
        // asset library, so uploaded media is only removed locally below.
        if ($ad) {
            $result = $this->callTikTok('post', $this->config . 'ad/status/update/', [
                'advertiser_id'    => $this->account->ad_account_id,
                'adgroup_id'       => $adGroup->ad_adgroup_id ?? null,
                'ad_ids'           => [$ad->ad_id],
                'operation_status' => 'DELETE',
            ]);

            if (!$result['success']) {
                return $result;
            }

            $ad->delete();
        }

        if ($adGroup) {
            foreach ($adGroup->creatives as $creative) {
                $creative->media()->detach();
                $creative->delete();
            }

            $result = $this->callTikTok('post', $this->config . 'adgroup/status/update/', [
                'advertiser_id'    => $this->account->ad_account_id,
                'adgroup_ids'      => [$adGroup->ad_adgroup_id],
                'operation_status' => 'DELETE',
            ]);

            if (!$result['success']) {
                return $result;
            }

            $adGroup->delete();
        }

        if ($campaign->ad_campaign_id) {
            $result = $this->callTikTok('post', $this->config . 'campaign/status/update/', [
                'advertiser_id'    => $this->account->ad_account_id,
                'campaign_ids'     => [$campaign->ad_campaign_id],
                'operation_status' => 'DELETE',
            ]);

            if (!$result['success']) {
                return $result;
            }
        }

        $campaign->delete();

        return $this->successResponse(null);
    }
}
