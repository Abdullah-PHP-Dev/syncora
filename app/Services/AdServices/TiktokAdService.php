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
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use App\Models\Country;

class TiktokAdService
{

    protected $platform, $account, $mediaAccountModel, $config, $httpClient, $apiService, $header, $state, $codeVerifier;

    public function __construct(AdAccount $account, ApiService $apiService)
    {
        $this->apiService = $apiService;
        $this->account = $account->wherePlatform('tiktok')->whereUserId(Auth::user()->id)->first();
   
        $this->config = adminSetting('ads.tiktok.base_url'); //config("services.ads.facebook");
        if ($this->account) {
            $this->header = $this->getHeaders();
        }

        $this->httpClient =  Http::class;
        $this->state = Session::get('ad_state');
        $this->platform = Session::get('ad_platform');
        $this->codeVerifier = Session::get('ad_codeverifier');
    }

    public function redirect($state)
    {
        $clientId = adminSetting('ads.tiktok.client_id');

        $tiktokAuthUrl = "https://business-api.tiktok.com/portal/auth?" . http_build_query([
            'app_id' => $clientId,
            'state' => $state,
            'grant_type' => 'authorization_code',
            'scope' => 'refresh_token',
            'redirect_uri' => $this->getCallbackUrl(),
        ]);

        return redirect()->away($tiktokAuthUrl);
    }

    private function getCallbackUrl()
    {
        return config('services.app_url') . '/admin/ads/tiktok/callback';
    }

    public function callback($state)
    {
        $code = request()->input('auth_code') ?? request()->input('code');

        $response = $this->apiService->post(
            'https://business-api.tiktok.com/open_api/v1.3/oauth2/access_token/',
            ['Content-Type' => 'application/json'],
            [
                'app_id'    => adminSetting('ads.tiktok.client_id'),
                'secret'    => adminSetting('ads.tiktok.client_secret'),
                'auth_code' => $code,
            ]
        );

        $data = $response['data'];

        if (!$this->tiktokSucceeded($response)) {
            return $this->oauthFailureRedirect($this->tiktokError($response));
        }

        $accessToken = data_get($data, 'data.access_token');
        $advertiserIds = data_get($data, 'data.advertiser_ids', []);

        if (empty($advertiserIds)) {
            return $this->oauthFailureRedirect('No TikTok advertiser account is authorized for this app.');
        }

        $advertiserId = $advertiserIds[0];

        $infoResponse = $this->apiService->get(
            'https://business-api.tiktok.com/open_api/v1.3/advertiser/info/',
            ['Access-Token' => $accessToken, 'Content-Type' => 'application/json'],
            ['advertiser_ids' => json_encode([$advertiserId])]
        );

        $advertiserInfo = $this->tiktokSucceeded($infoResponse)
            ? data_get($infoResponse, 'data.data.list.0', [])
            : [];

        AdAccount::updateOrCreate(
            ['platform' => 'tiktok', 'user_id' => Auth::id(), 'ad_account_id' => $advertiserId],
            [
                'name'         => $advertiserInfo['name'] ?? 'TikTok Ad Account',
                'currency'     => $advertiserInfo['currency'] ?? 'USD',
                'access_token' => $accessToken,
                'status'       => 'active',
                // TikTok access tokens issued via this flow don't expire on a fixed
                // schedule the way Facebook's do and have no refresh_token grant in
                // the standard self-serve API - set a generous horizon so tokenIsValid()
                // doesn't force refreshToken() (which can't actually refresh anything).
                'expires_at'   => Carbon::now()->addYear(),
            ]
        );

        return redirect(Session::pull('previous_url', route('admin.ads.dashboard')))
            ->with('success', 'TikTok ad account connected successfully.');
    }

    private function oauthFailureRedirect($message)
    {
        return redirect(route('admin.ads.dashboard'))
            ->with('error', is_array($message) ? json_encode($message) : $message);
    }

    /**
     * TikTok almost always returns HTTP 200 even on API-level errors, encoding the
     * real result in a `code` field inside the JSON body (0 = success).
     */
    private function tiktokSucceeded(array $response): bool
    {
        return ($response['success'] ?? false) && (($response['data']['code'] ?? null) === 0);
    }

    private function tiktokError(array $response): string
    {
        return $response['data']['message'] ?? ($response['error'] ?? 'Unknown TikTok API error');
    }

    public function store($platform, $request)
    {
       
        // Step 2: create campaign
        $response = $this->storeCampaign($platform, $request);

        if (!$response['success']) {
            return $response;
        }

        $request['campaign_id'] = $response['data']['ad_campaign_id'];
        $request['ad_campaign_id'] = $response['data']['id'];
        // Step 2: create Ad Group
        $response = $this->storeAdGroup($platform, $request);

        if (!$response['success']) {
            return $response;
        }

        $request['adgroup_id'] = $response['data']['ad_adgroup_id'];
        $request['ad_adgroup_id'] = $response['data']['id'];

        // Step 3: create Media
        $response = $this->storeMedia($platform, $request);

        if (!$response['success']) {
            return $response;
        }

        $request['media'] = $response['data'];

        // Step 4: create creative
        $response = $this->storeCreative($platform, $request);

        if (!$response['success']) {
            return $response;
        }

        $request['creative_id'] = $response['data']['ad_creative_id'];
        $request['ad_creative_id'] = $response['data']['id'];

        // Step 4: create Ad
        return $this->storeAd($platform, $request);
    }

    private function storeCampaign($platform, $request)
    {
        $endpoint = $this->config . 'campaign/create/';

        $payload = [
            'advertiser_id'    => $this->account->ad_account_id,
            'campaign_name'    => $request['name'],
            'objective_type'   => $request['objective'],
            'budget_mode'      => $request['budget_mode'],
            'operation_status' => 'DISABLE',
        ];

        if ($request['budget_mode'] !== 'BUDGET_MODE_INFINITE') {
            $payload['budget'] = (float) $request['budget'];
        }

        if ($request['objective'] === 'APP_PROMOTION' && !empty($request['app_promotion_type'])) {
            $payload['app_promotion_type'] = $request['app_promotion_type'];
        }

        $response = $this->apiService->post($endpoint, $this->header['data'], $payload);

        if (!$this->tiktokSucceeded($response)) {
            return $this->errorResponse($this->tiktokError($response));
        }

        $id = $response['data']['data']['campaign_id'];

        $dataToInsert = [
            'ad_campaign_id'     => $id,
            'user_id'            => Auth::id(),
            'ad_account_id'      => $this->account->id,
            'name'               => $request['name'],
            'objective'          => $request['objective'],
            'app_promotion_type' => $request['app_promotion_type'] ?? null,
            'budget_mode'        => $request['budget_mode'],
            'budget'             => $request['budget'],
            'platform'           => $platform,
            'start_time'         => $request['start_time'],
            'end_time'           => $request['end_time'],
            'status'             => false,
        ];

        return $this->apiService->success(
            $dataToInsert,
            ['ad_campaign_id' => $id],
            new AdCampaign
        );
    }

    /**
     * TikTok's adgroup.location_ids expects TikTok's own numeric region ids, not
     * ISO country codes - our countries table only stores ISO codes. Resolve by
     * matching country names against TikTok's live region tree.
     */
    private function resolveLocationIds(array $countryIds): array
    {
        $names = Country::whereIn('id', $countryIds)
            ->pluck('name')
            ->map(fn ($name) => strtolower(trim($name)))
            ->toArray();

        if (empty($names)) {
            return [];
        }

        $response = $this->apiService->get($this->config . 'tool/region/', $this->header['data'], [
            'advertiser_id' => $this->account->ad_account_id,
            'placements'    => json_encode(['PLACEMENT_TIKTOK']),
        ]);

        if (!$this->tiktokSucceeded($response)) {
            return [];
        }

        $found = [];
        $this->collectCountryLocationIds($response['data']['data']['region_info'] ?? [], $names, $found);

        return $found;
    }

    private function collectCountryLocationIds(array $nodes, array $names, array &$found): void
    {
        foreach ($nodes as $node) {
            if (($node['type'] ?? null) === 'COUNTRY' && in_array(strtolower(trim($node['name'] ?? '')), $names, true)) {
                $found[] = $node['location_id'];
            }

            if (!empty($node['children'])) {
                $this->collectCountryLocationIds($node['children'], $names, $found);
            }
        }
    }

    private function storeAdGroup($platform, $request)
    {
        $endpoint = $this->config . 'adgroup/create/';

        $locationIds = $this->resolveLocationIds($request['countries']);
        $ageGroups = $request['age_range'] ?? [];
        $languages = $request['languages'] ?? [];

        $payload = [
            'advertiser_id'       => $this->account->ad_account_id,
            'campaign_id'         => $request['campaign_id'],
            'adgroup_name'        => $request['name'],
            'promotion_type'      => $request['promotion_type'],
            'placement_type'      => 'PLACEMENT_TYPE_AUTOMATIC',
            'location_ids'        => $locationIds,
            'gender'              => $request['gender'] ?? 'GENDER_UNLIMITED',
            'age_groups'          => $ageGroups,
            'languages'           => $languages,
            'budget_mode'         => $request['budget_mode'],
            'schedule_type'       => 'SCHEDULE_START_END',
            'schedule_start_time' => Carbon::parse($request['start_time'])->format('Y-m-d 00:00:00'),
            'schedule_end_time'   => Carbon::parse($request['end_time'])->format('Y-m-d 23:59:59'),
            'optimization_goal'   => $request['optimization_goal'],
            'billing_event'       => $request['billing_event'],
            'pacing'              => 'PACING_MODE_SMOOTH',
            'operation_status'    => 'DISABLE',
        ];

        if ($request['budget_mode'] !== 'BUDGET_MODE_INFINITE') {
            $payload['budget'] = (float) $request['budget'];
        }

        if (!empty($request['bid_amount'])) {
            $payload['bid_type'] = 'BID_TYPE_CUSTOM';
            $payload['bid_price'] = (float) $request['bid_amount'];
        } else {
            $payload['bid_type'] = 'BID_TYPE_NO_BID';
        }

        if (!empty($request['app_id'])) {
            $payload['app_id'] = $request['app_id'];
        }

        if (!empty($request['pixel_id'])) {
            $payload['pixel_id'] = $request['pixel_id'];
        }

        if (!empty($request['promotion_target_type'])) {
            $payload['promotion_target_type'] = $request['promotion_target_type'];
        }

        if (!empty($request['messaging_app_type'])) {
            $payload['messaging_app_type'] = $request['messaging_app_type'];
            $payload['messaging_app_account_id'] = $request['messaging_app_account_id'] ?? null;
            $payload['phone_region_code'] = $request['phone_region_code'] ?? null;
            $payload['phone_number'] = $request['phone_number'] ?? null;
        }

        $response = $this->apiService->post($endpoint, $this->header['data'], $payload);

        if (!$this->tiktokSucceeded($response)) {
            return $this->errorResponse($this->tiktokError($response));
        }

        $id = $response['data']['data']['adgroup_id'];

        $dataToInsert = [
            'ad_campaign_id'        => $request['ad_campaign_id'],
            'user_id'               => Auth::id(),
            'ad_adgroup_id'         => $id,
            'ad_account_id'         => $this->account->id,
            'name'                  => $request['name'],
            'promotion_type'        => $request['promotion_type'],
            'promotion_target_type' => $request['promotion_target_type'] ?? null,
            'placement_type'        => 'PLACEMENT_TYPE_AUTOMATIC',
            'location_ids'          => json_encode($request['countries']),
            'gender'                => $request['gender'] ?? null,
            'budget_mode'           => $request['budget_mode'],
            'budget'                => $request['final_budget'] ?? $request['budget'],
            'schedule_type'         => 'SCHEDULE_START_END',
            'schedule_start_time'   => $request['start_time'],
            'schedule_end_time'     => $request['end_time'],
            'optimization_goal'     => $request['optimization_goal'],
            'bid_type'              => $payload['bid_type'],
            'bid_price'             => $request['bid_amount'] ?? null,
            'billing_event'         => $request['billing_event'],
            'pacing'                => 'PACING_MODE_SMOOTH',
            'age_groups'            => json_encode($ageGroups),
            'languages'             => json_encode($languages),
            'objective'             => $request['objective'] ?? null,
            'platform'              => $platform,
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

        foreach ($request['media'] as $media) {
            $extension = strtolower($media->getClientOriginalExtension());
            $mediaType = $this->getMediaType($extension); // IMAGE | VIDEO
            $fileName = time() . '_' . uniqid() . '.' . $extension;

            $s3Path = "uploads/{$platform}/{$mediaType}/{$fileName}";
            Storage::disk('s3')->put(
                $s3Path,
                file_get_contents($media->getRealPath()),
                ['visibility' => 'public']
            );

            $filePath = Storage::disk('s3')->url($s3Path);

            $isVideo = $mediaType === 'VIDEO';
            $endpoint = $this->config . ($isVideo ? 'file/video/ad/upload/' : 'file/image/ad/upload/');

            $formFields = [
                'advertiser_id' => $this->account->ad_account_id,
                'upload_type'   => 'UPLOAD_BY_FILE',
            ];

            $filePayload = [[
                'name'       => $isVideo ? 'video_file' : 'image_file',
                'file_name'  => $fileName,
                'media_file' => $media->getRealPath(),
            ]];

            $response = $this->apiService->post($endpoint, $this->header['data'], $formFields, 'multipart', $filePayload);

            if (!$this->tiktokSucceeded($response)) {
                return $this->errorResponse($this->tiktokError($response));
            }

            $result = $response['data']['data'];
            $result = $isVideo && isset($result[0]) ? $result[0] : $result;
            $tiktokMediaId = $isVideo ? $result['video_id'] : $result['image_id'];

            $dataToInsert = [
                'ad_media_id'    => $tiktokMediaId,
                'ad_account_id'  => $this->account->id,
                'ad_campaign_id' => $request['ad_campaign_id'],
                'platform'       => 'tiktok',
                'name'           => $fileName,
                'url'            => $filePath,
                'download_link'  => $filePath,
                'type'           => $mediaType,
                'status'         => true,
                'file_name'      => $fileName,
                'image_category' => $mediaType,
                'upload_by_type' => 'UPLOAD_BY_FILE',
                'user_id'        => Auth::id(),
            ];

            $localMedia = $this->apiService->success(
                $dataToInsert,
                ['ad_media_id' => $tiktokMediaId],
                new AdMedia
            );

            $mediaIds[] = [
                'ad_media_id' => $localMedia['data']['id'],
                'media_id'    => $tiktokMediaId,
            ];
        }

        return ['success' => true, 'data' => $mediaIds];
    }

    private function storeCreative($platform, $request)
    {
        $mediaList = $request['media'] ?? [];

        if (empty($mediaList)) {
            return $this->errorResponse('At least one media file is required to create a creative.');
        }

        $dataToInsert = [
            'user_id'        => Auth::id(),
            'platform'       => 'tiktok',
            'ad_account_id'  => $this->account->id,
            'ad_campaign_id' => $request['ad_campaign_id'],
            'ad_adgroup_id'  => $request['ad_adgroup_id'],
            'name'           => $request['name'],
            'ad_format'      => $request['media_type'],
            'message'        => $request['description'],
            'call_to_action' => $request['call_to_action'],
            'url'            => $request['target_link'],
            'type'           => $request['media_type'],
        ];

        // TikTok has no standalone creative-create endpoint - the creative is
        // submitted together with the ad via ad/create. This row is a local
        // placeholder that storeAd() fills in with the real TikTok ad id.
        $creative = $this->apiService->success($dataToInsert, [], new AdCreative);

        foreach ($mediaList as $media) {
            $mediaToInsert = ['ad_media_id' => $media['ad_media_id'], 'ad_creative_id' => $creative['data']['id']];
            $this->apiService->success($mediaToInsert, $mediaToInsert, new AdCreativeMedia);
        }

        return $creative;
    }

    private function storeAd($platform, $request)
    {
        $mediaList = $request['media'] ?? [];
        $mediaIds = array_column($mediaList, 'media_id');
        $adFormat = ($request['media_type'] ?? 'IMAGE') === 'VIDEO' ? 'SINGLE_VIDEO' : 'SINGLE_IMAGE';

        $creative = [
            'ad_name'          => $request['name'],
            'ad_format'        => $adFormat,
            'ad_text'          => $request['description'],
            'call_to_action'   => $request['call_to_action'],
            'landing_page_url' => $request['target_link'],
        ];

        if ($adFormat === 'SINGLE_VIDEO') {
            $creative['video_id'] = $mediaIds[0] ?? null;
        } else {
            $creative['image_ids'] = $mediaIds;
        }

        $endpoint = $this->config . 'ad/create/';

        $payload = [
            'advertiser_id' => $this->account->ad_account_id,
            'adgroup_id'    => $request['adgroup_id'],
            'creatives'     => [$creative],
        ];

        $response = $this->apiService->post($endpoint, $this->header['data'], $payload);

        if (!$this->tiktokSucceeded($response)) {
            return $this->errorResponse($this->tiktokError($response));
        }

        $adId = $response['data']['data']['ad_ids'][0] ?? null;

        if (!$adId) {
            return $this->errorResponse('TikTok did not return an ad id.');
        }

        AdCreative::where('id', $request['ad_creative_id'])->update(['ad_creative_id' => $adId]);

        $dataToInsert = [
            'user_id'        => Auth::id(),
            'ad_adgroup_id'  => $request['ad_adgroup_id'],
            'ad_creative_id' => $request['ad_creative_id'],
            'ad_id'          => $adId,
            'status'         => false,
            'platform'       => 'tiktok',
            'ad_account_id'  => $this->account->id,
            'ad_campaign_id' => $request['ad_campaign_id'],
            'name'           => $request['name'],
            'call_to_action' => $request['call_to_action'],
        ];

        return $this->apiService->success(
            $dataToInsert,
            ['ad_id' => $adId],
            new Ad
        );
    }

    private function getHeaders()
    {
        if ($this->tokenIsValid($this->account->expires_at)) {
            $accessToken = $this->account->access_token;
        } else {
            $response = $this->refreshToken($this->account->access_token);

            if (!$response['success']) {
                return $response;
            }

            $accessToken = $response['data'];
        }


        return [
            'success' => true,
            'data' => [
                'Access-Token' => $accessToken,
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

    public function refreshToken($accessToken)
    {
        // TikTok's Business API does not support refreshing access tokens issued
        // through the standard OAuth authorize flow - they're valid until revoked.
        // If tokenIsValid() ever lapses, the user needs to reconnect the account.
        return $this->errorResponse('TikTok access token has expired. Please reconnect the TikTok ad account.');
    }

    private function errorResponse($error)
    {
        return ['success' => false, 'error' => $error];
    }

    private function successResponse($data)
    {
        return ['success' => true, 'data' => $data];
    }

    private function getMediaType($fileExtension)
    {
        // Define media types based on file extensions
        $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp'];
        $videoExtensions = ['mp4', 'avi', 'mov', 'mkv', 'flv'];
        $audioExtensions = ['mp3'];

        // Check the extension and return the corresponding media type
        if (in_array(strtolower($fileExtension), $imageExtensions)) {
            return 'IMAGE';
        } elseif (in_array(strtolower($fileExtension), $videoExtensions)) {
            return 'VIDEO';
        } elseif (in_array(strtolower($fileExtension), $audioExtensions)) {
            return 'MUSIC';
        }

        // Default to 'unknown' if the extension is neither an image nor a video
        return 'IMAGE';
    }

    public function update($platform, $id, $request)
    {
        $response = $this->updateCampaign($platform, $id, $request);

        if (!$response['success']) {
            return $response;
        }

        $campaign = $response['data'];
        $request['ad_campaign_id'] = $campaign['id'];

        $adGroupResponse = $this->updateAdGroup($platform, $campaign['id'], $request);

        if (!$adGroupResponse['success']) {
            return $adGroupResponse;
        }

        if (!empty($request['media'])) {
            $mediaResponse = $this->storeMedia($platform, $request);

            if (!$mediaResponse['success']) {
                return $mediaResponse;
            }

            $request['media'] = $mediaResponse['data'];
        } else {
            $request['media'] = [];
        }

        return $this->updateAd($platform, $request, $adGroupResponse['data']);
    }

    private function updateCampaign($platform, $id, $request)
    {
        $campaign = AdCampaign::findOrFail($id);

        $endpoint = $this->config . 'campaign/update/';

        $payload = [
            'advertiser_id' => $this->account->ad_account_id,
            'campaign_id'   => $campaign->ad_campaign_id,
            'campaign_name' => $request['name'],
        ];

        $response = $this->apiService->post($endpoint, $this->header['data'], $payload);

        if (!$this->tiktokSucceeded($response)) {
            return $this->errorResponse($this->tiktokError($response));
        }

        $dataToInsert = [
            'name'   => $request['name'],
            'status' => false,
        ];

        return $this->apiService->success(
            $dataToInsert,
            ['ad_campaign_id' => $campaign->ad_campaign_id],
            new AdCampaign
        );
    }

    private function updateAdGroup($platform, $campaignId, $request)
    {
        $adGroup = AdAdGroup::where('ad_campaign_id', $campaignId)->firstOrFail();

        $endpoint = $this->config . 'adgroup/update/';

        $locationIds = $this->resolveLocationIds($request['countries']);
        $ageGroups = $request['age_range'] ?? [];
        $languages = $request['languages'] ?? [];

        $payload = [
            'advertiser_id'       => $this->account->ad_account_id,
            'adgroup_id'          => $adGroup->ad_adgroup_id,
            'adgroup_name'        => $request['name'],
            'location_ids'        => $locationIds,
            'gender'              => $request['gender'] ?? 'GENDER_UNLIMITED',
            'age_groups'          => $ageGroups,
            'languages'           => $languages,
            'schedule_start_time' => Carbon::parse($request['start_time'])->format('Y-m-d 00:00:00'),
            'schedule_end_time'   => Carbon::parse($request['end_time'])->format('Y-m-d 23:59:59'),
            'optimization_goal'   => $request['optimization_goal'],
            'billing_event'       => $request['billing_event'],
        ];

        if (($request['budget_mode'] ?? null) !== 'BUDGET_MODE_INFINITE' && isset($request['budget'])) {
            $payload['budget'] = (float) $request['budget'];
        }

        if (!empty($request['bid_amount'])) {
            $payload['bid_type'] = 'BID_TYPE_CUSTOM';
            $payload['bid_price'] = (float) $request['bid_amount'];
        } else {
            $payload['bid_type'] = 'BID_TYPE_NO_BID';
        }

        if (!empty($request['pixel_id'])) {
            $payload['pixel_id'] = $request['pixel_id'];
        }

        $response = $this->apiService->post($endpoint, $this->header['data'], $payload);

        if (!$this->tiktokSucceeded($response)) {
            return $this->errorResponse($this->tiktokError($response));
        }

        $dataToInsert = [
            'ad_campaign_id'      => $campaignId,
            'user_id'             => Auth::id(),
            'ad_adgroup_id'       => $adGroup->ad_adgroup_id,
            'ad_account_id'       => $this->account->id,
            'name'                => $request['name'],
            'promotion_type'      => $request['promotion_type'] ?? $adGroup->promotion_type,
            'location_ids'        => json_encode($request['countries']),
            'gender'              => $request['gender'] ?? null,
            'budget_mode'         => $request['budget_mode'] ?? $adGroup->budget_mode,
            'budget'              => $request['final_budget'] ?? $request['budget'] ?? $adGroup->budget,
            'schedule_start_time' => $request['start_time'],
            'schedule_end_time'   => $request['end_time'],
            'optimization_goal'   => $request['optimization_goal'],
            'bid_type'            => $payload['bid_type'],
            'bid_price'           => $request['bid_amount'] ?? null,
            'billing_event'       => $request['billing_event'],
            'age_groups'          => json_encode($ageGroups),
            'languages'           => json_encode($languages),
            'platform'            => $platform,
            'status'              => false,
        ];

        return $this->apiService->success(
            $dataToInsert,
            ['ad_adgroup_id' => $adGroup->ad_adgroup_id],
            new AdAdGroup
        );
    }

    private function updateAd($platform, $request, $adGroup)
    {
        $creative = $adGroup->creatives->first();
        $ad = $adGroup->ads->first();

        if (!$creative || !$ad) {
            return $this->errorResponse('Existing creative or ad record not found for this campaign.');
        }

        $mediaList = $request['media'] ?? [];
        $mediaIds = array_column($mediaList, 'media_id');
        $adFormat = ($request['media_type'] ?? $creative->type) === 'VIDEO' ? 'SINGLE_VIDEO' : 'SINGLE_IMAGE';

        $creativePayload = [
            'ad_id'            => $ad->ad_id,
            'ad_name'          => $request['name'],
            'ad_text'          => $request['description'],
            'call_to_action'   => $request['call_to_action'],
            'landing_page_url' => $request['target_link'],
        ];

        if (!empty($mediaIds)) {
            if ($adFormat === 'SINGLE_VIDEO') {
                $creativePayload['video_id'] = $mediaIds[0];
            } else {
                $creativePayload['image_ids'] = $mediaIds;
            }
        }

        $endpoint = $this->config . 'ad/update/';

        $payload = [
            'advertiser_id' => $this->account->ad_account_id,
            'adgroup_id'    => $adGroup->ad_adgroup_id,
            'creatives'     => [$creativePayload],
        ];

        $response = $this->apiService->post($endpoint, $this->header['data'], $payload);

        if (!$this->tiktokSucceeded($response)) {
            return $this->errorResponse($this->tiktokError($response));
        }

        $creative->update([
            'name'           => $request['name'],
            'message'        => $request['description'],
            'call_to_action' => $request['call_to_action'],
            'url'            => $request['target_link'],
            'type'           => $request['media_type'] ?? $creative->type,
            'ad_format'      => $adFormat,
        ]);

        foreach ($mediaList as $media) {
            $mediaToInsert = ['ad_media_id' => $media['ad_media_id'], 'ad_creative_id' => $creative->id];
            $this->apiService->success($mediaToInsert, $mediaToInsert, new AdCreativeMedia);
        }

        $dataToInsert = [
            'user_id'        => Auth::id(),
            'ad_adgroup_id'  => $adGroup->id,
            'ad_creative_id' => $creative->id,
            'ad_id'          => $ad->ad_id,
            'status'         => false,
            'platform'       => 'tiktok',
            'ad_account_id'  => $this->account->id,
            'ad_campaign_id' => $request['ad_campaign_id'],
            'name'           => $request['name'],
            'call_to_action' => $request['call_to_action'],
        ];

        return $this->apiService->success(
            $dataToInsert,
            ['ad_id' => $ad->ad_id],
            new Ad
        );
    }

    public function destroy($platform, $id)
    {
        $campaign = AdCampaign::with(['adGroups', 'ads'])->findOrFail($id);
        $adGroup = $campaign->adGroups->first();
        $ad = $campaign->ads->first();

        // TikTok has no hard-delete endpoint for campaigns/adgroups/ads - the
        // equivalent is setting operation_status to DELETE via the status/update
        // endpoints, in ad -> adgroup -> campaign order.
        if ($ad) {
            $response = $this->apiService->post($this->config . 'ad/status/update/', $this->header['data'], [
                'advertiser_id'     => $this->account->ad_account_id,
                'adgroup_id'        => $adGroup->ad_adgroup_id ?? null,
                'ad_ids'            => [$ad->ad_id],
                'operation_status'  => 'DELETE',
            ]);

            if (!$this->tiktokSucceeded($response)) {
                return $this->errorResponse($this->tiktokError($response));
            }
        }

        if ($adGroup) {
            $response = $this->apiService->post($this->config . 'adgroup/status/update/', $this->header['data'], [
                'advertiser_id'    => $this->account->ad_account_id,
                'adgroup_ids'      => [$adGroup->ad_adgroup_id],
                'operation_status' => 'DELETE',
            ]);

            if (!$this->tiktokSucceeded($response)) {
                return $this->errorResponse($this->tiktokError($response));
            }
        }

        if ($campaign->ad_campaign_id) {
            $response = $this->apiService->post($this->config . 'campaign/status/update/', $this->header['data'], [
                'advertiser_id'    => $this->account->ad_account_id,
                'campaign_ids'     => [$campaign->ad_campaign_id],
                'operation_status' => 'DELETE',
            ]);

            if (!$this->tiktokSucceeded($response)) {
                return $this->errorResponse($this->tiktokError($response));
            }
        }

        $campaign->delete();

        return ['success' => true, 'data' => null];
    }
}

