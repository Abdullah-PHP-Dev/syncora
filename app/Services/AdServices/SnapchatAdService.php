<?php

namespace App\Services\AdServices;

use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Auth;
use App\Models\Admin\AdAccount;
use App\Models\Admin\AdCampaign;
use App\Models\Admin\AdAdGroup;
use App\Models\Admin\AdMedia;
use Illuminate\Support\Facades\Session;
use App\Models\Admin\Ad;
use App\Models\Admin\AdCreative;
use App\Models\Admin\AdCreativeMedia;
use App\Models\Country;
use App\Services\ApiService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;


class SnapchatAdService
{

    protected $platform, $account, $mediaAccountModel, $config, $httpClient, $apiService, $header, $state, $codeVerifier;

    public function __construct(AdAccount $account, ApiService $apiService)
    {
        $this->apiService = $apiService;
        $this->account = $account->wherePlatform('snapchat')->whereUserId(Auth::user()->id)->first();
        $this->config = adminSetting('ads.snapchat.base_url'); //config("services.ads.snapchat");
        if ($this->account) {
            $this->header = $this->getHeaders();

            if (!$this->header['success']) {
                return $this->header;
            }
        }

        $this->httpClient =  Http::class;
        $this->state = Session::get('ad_state');
        $this->platform = Session::get('ad_platform');
        $this->codeVerifier = Session::get('ad_codeverifier');
    }


    public function redirect($state)
    {
        $clientId = adminSetting('ads.snapchat.client_id');

        $url = 'https://accounts.snapchat.com/login/oauth2/authorize?' . http_build_query([
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
        return config('services.app_url') . '/admin/ads/snapchat/callback';
    }

    public function callback($state)
    {
        $code = request()->input('code');
        $endpoint = adminSetting('ads.snapchat.access_token');

        $response = $this->apiService->post($endpoint, ['Content-Type' => 'application/x-www-form-urlencoded'], [
            'client_id'     => adminSetting('ads.snapchat.client_id'),
            'client_secret' => adminSetting('ads.snapchat.client_secret'),
            'code'          => $code,
            'grant_type'    => 'authorization_code',
            'redirect_uri'  => $this->getCallbackUrl(),
        ], 'form');

        $data = $response['data'];

        if (!$response['success']) {
            return $this->oauthFailureRedirect($data['error_description'] ?? 'Failed to authorize the Snapchat account.');
        }

        $accessToken = data_get($data, 'access_token');
        $refreshToken = data_get($data, 'refresh_token');
        $expiresIn = data_get($data, 'expires_in', 1800);

        $accountResponse = $this->getSnapchatAdAccount($accessToken);

        if (!($accountResponse['success'] ?? false)) {
            return $this->oauthFailureRedirect($accountResponse['error'] ?? 'No Snapchat ad account was found.');
        }

        AdAccount::updateOrCreate(
            ['platform' => 'snapchat', 'user_id' => Auth::id(), 'ad_account_id' => $accountResponse['ad_account_id']],
            [
                'name'          => $accountResponse['name'] ?? 'Snapchat Ad Account',
                'currency'      => $accountResponse['currency'] ?? 'USD',
                'access_token'  => $accessToken,
                'refresh_token' => $refreshToken,
                'profile_id'    => $accountResponse['profile_id'] ?? null,
                'status'        => 'active',
                'expires_at'    => Carbon::now()->addSeconds($expiresIn),
            ]
        );

        return redirect(Session::pull('previous_url', route('admin.ads.dashboard')))
            ->with('success', 'Snapchat ad account connected successfully.');
    }

    private function oauthFailureRedirect($message)
    {
        return redirect(route('admin.ads.dashboard'))
            ->with('error', is_array($message) ? json_encode($message) : $message);
    }

    /**
     * Resolves the ad account, org-level currency/name, and the Public Profile id
     * Snapchat's creative API requires (profile_properties.profile_id). Field names
     * here follow Snapchat Marketing API v1 - worth a live-account sanity check.
     */
    private function getSnapchatAdAccount($accessToken)
    {
        $headers = ['Authorization' => "Bearer {$accessToken}"];

        $orgResponse = $this->apiService->get($this->config . 'me/organizations', $headers);

        if (!$orgResponse['success'] || empty($orgResponse['data']['organizations'])) {
            return $this->errorResponse('No Snapchat organization is linked to this account.');
        }

        $organizationId = $orgResponse['data']['organizations'][0]['organization']['id'];

        $accountsResponse = $this->apiService->get($this->config . "organizations/{$organizationId}/adaccounts", $headers);

        if (!$accountsResponse['success'] || empty($accountsResponse['data']['adaccounts'])) {
            return $this->errorResponse('No Snapchat ad account is linked to this organization.');
        }

        $adAccount = $accountsResponse['data']['adaccounts'][0]['adaccount'];
        $adAccountId = $adAccount['id'];

        $profileId = null;
        $profilesResponse = $this->apiService->get($this->config . "adaccounts/{$adAccountId}/profiles", $headers);

        if ($profilesResponse['success'] && !empty($profilesResponse['data']['results'])) {
            $profile = $profilesResponse['data']['results'][0];
            $profileId = $profile['public_profile']['id'] ?? $profile['organic_profile']['id'] ?? null;
        }

        return [
            'success'       => true,
            'ad_account_id' => $adAccountId,
            'name'          => $adAccount['name'] ?? null,
            'currency'      => $adAccount['currency'] ?? null,
            'profile_id'    => $profileId,
        ];
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
        $endpoint = $this->config . 'adaccounts/' . $this->account->ad_account_id . '/campaigns';

        $payload = [
            'campaigns' => [
                [
                    'ad_account_id'          => $this->account->ad_account_id, 
                    'name'                   => $request['name'],
                    'status'                 => 'PAUSED',
                    'start_time'             => Carbon::parse($request['start_time'])->utc()->format('Y-m-d\TH:i:s.v\Z'),
                    'end_time'               => Carbon::parse($request['end_time'])->utc()->format('Y-m-d\TH:i:s.v\Z'),
                    'objective_v2_properties' => [
                        'objective_v2_type'  => $request['objective'],
                        'promotion_type' => $request['promotion_type']
                    ],
                    'buy_model'              => 'AUCTION',
                    'creation_state'         => 'PUBLISHED',
                ]
            ]
        ];
        
        // Send as standard application/json payload
        $response = $this->apiService->post(
            $endpoint,
            $this->header['data'],
            $payload
        );
      
        if (!$response['success']) {
            return $this->errorResponse($response['data']['display_message'] ?? $response['data']['debug_message']);
        } else if ($response['data']['request_status'] == 'ERROR') {
            return $this->errorResponse($response['data']['campaigns'][0]['sub_request_error_reason']);
        }

        $campaign = $response['data']['campaigns'][0]['campaign'];
        $id = $campaign['id'];

        $dataToInsert = [
            'ad_campaign_id'     => $id,
            'user_id'         => Auth::user()->id,
            'ad_account_id'   => $this->account->id,
            'name' => $request['name'],
            'objective' => $request['objective'],
            'budget_mode' => $request['budget_mode'],
            'budget' => $request['final_budget'],
            'platform' => $platform,
            'start_time' => $request['start_time'],
            'bidding_strategy'        => $request['bid_strategy'],
            'end_time' => $request['end_time'],
            'app_promotion_type' => $request['promotion_type'],
            'status' => false,
        ];

        return $this->apiService->success(
            $dataToInsert,
            ['ad_campaign_id' => $id],
            new AdCampaign
        );
    }

    /**
     * Shared audience targeting builder used by both storeAdGroup and updateAdGroup,
     * since Snapchat's demographic/geo targeting shape must match on create and update.
     */
    private function buildAudienceTargeting(array $request): array
    {
        $genderMap = [
            'male'   => 'MALE',
            'female' => 'FEMALE',
        ];
        $languageMap = [
            'English' => 'en',
            'Arabic'  => 'ar',
            'Spanish' => 'es',
            'French'  => 'fr',
        ];

        $gender = isset($request['gender'], $genderMap[$request['gender']])
            ? $genderMap[$request['gender']]
            : null;

        $ageGroups = [];

        if (!empty($request['age_range']) && is_array($request['age_range'])) {
            foreach ($request['age_range'] as $range) {
                // transforms "AGE_18_24" -> "18-24"
                $ageGroups[] = str_replace(['AGE_', '_'], ['', '-'], $range);
            }
        }

        $languages = [];

        if (!empty($request['languages']) && is_array($request['languages'])) {
            foreach ($request['languages'] as $lang) {
                $languages[] = $languageMap[$lang] ?? strtolower($lang);
            }
        }

        $demographicSpec = [];

        if (!empty($languages)) {
            $demographicSpec['languages'] = array_values(array_unique($languages));
        }

        if ($gender) {
            $demographicSpec['gender'] = $gender;
        }

        if (!empty($ageGroups)) {
            $demographicSpec['age_groups'] = array_values(array_unique($ageGroups));
        }

        // Country targeting - pass country code as lowercase ISO-2 string (e.g., "sa", "us").
        $countries = Country::whereIn('id', $request['countries'])
            ->pluck('code')
            ->toArray();

        $geos = array_map(function ($code) {
            return [
                'country_code' => strtolower($code)
            ];
        }, $countries);

        return [$demographicSpec, $geos, $countries];
    }

    private function storeAdGroup($platform, $request)
    {
        $endpoint = $this->config . 'campaigns/' . $request['campaign_id'] . '/adsquads';

        [$demographicSpec, $geos, $countries] = $this->buildAudienceTargeting($request);

        // 5. Build Final AdSquad Payload
        $adSquad = [
            'name'                => $request['name'],
            'status'              => 'PAUSED',
            'campaign_id'         => $request['campaign_id'],
            'type'                => 'SNAP_ADS',
            'targeting'           => [
                'demographics' => [$demographicSpec],
                'geos'         => $geos,
            ],
            'placement_v2'        => ['config' => 'AUTOMATIC'],
            'billing_event'       => 'IMPRESSION',
            'bid_strategy'        => $request['bid_strategy'],
            'start_time'          => Carbon::parse($request['start_time'])->utc()->format("Y-m-d\TH:i:s.v\Z"),
            'end_time'            => Carbon::parse($request['end_time'])->utc()->format("Y-m-d\TH:i:s.v\Z"),
            'optimization_goal'   => $request['optimization_goal'],
            'pacing_type'         => 'STANDARD',
            'brand_safety_config' => [
                'inventory_option' => 'LIMITED_INVENTORY'
            ],
        ];

        // 6. Set Budget (Uses net budget $20 in micro-units)
        $budgetInMicros = (int) $request['budget'] * 1000000;

        if (($request['budget_mode'] ?? 'daily') === 'daily') {
            $adSquad['daily_budget_micro'] = $budgetInMicros;
        } else {
            $adSquad['lifetime_budget_micro'] = $budgetInMicros;
        }
        if ($request['bid_strategy'] == 'TARGET_COST') {
            $adSquad['target_bid'] = true;
            $adSquad['auto_bid'] = false;
            $adSquad['bid_micro'] = (int) bcmul((string) $request['bid_amount'], '1000000', 0);
        } else if ($request['bid_strategy'] == 'LOWEST_COST_WITH_MAX_BID') {
            $adSquad['target_bid'] = false;
            $adSquad['auto_bid'] = false;
            $adSquad['bid_micro'] = (int) bcmul((string) $request['bid_amount'], '1000000', 0);
        } else if ($request['bid_strategy'] == 'AUTO_BID') {
            $adSquad['target_bid'] = false;
            $adSquad['auto_bid'] = true;
        }
        // Optional pixel and event sources
        if (!empty($request['pixel_id'])) {
            $adSquad['pixel_id'] = $request['pixel_id'];
        }

        $payload = ['adsquads' => [$adSquad]];
       
        $response = $this->apiService->post($endpoint, $this->header['data'], $payload);

        if (!$response['success']) {
            return $this->errorResponse($response['data']['debug_message'] ?? $response['data']['display_message']);
        } else if ($response['data']['request_status'] == 'ERROR') {
            return $this->errorResponse($response['data']['adsquads'][0]['sub_request_error_reason']);
        }

        $adSquad = $response['data']['adsquads'][0]['adsquad'];
      
        $id = $adSquad['id'];

        $dataToInsert = [
            'ad_campaign_id'     => $request['ad_campaign_id'],
            'user_id'         => Auth::user()->id,
            'ad_adgroup_id'         => $id,
            'ad_account_id'   => $this->account->id,
            'name' => $request['name'],
            'location_ids' => json_encode($countries),
            'platform' => $platform,
            'gender' => $request['gender'],
            'languages' => json_encode($request['languages']),
            'budget_mode' => $request['budget_mode'],
            'pixel_id' => $request['pixel_id'] ?? '',
            'bid_price'        => $request['bid_amount'],
            'billing_event'     => $request['billing_event'] ?? null,
            'optimization_goal' => $request['optimization_goal'],
            'schedule_start_time' => $request['start_time'],
            'schedule_end_time' => $request['end_time'],
            'budget' => $request['final_budget'],
            'age_groups' => json_encode($request['age_range']),
            'status' => false
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
            $mediaType = $this->getMediaType($extension); // 'image' or 'video'
            $fileName = time() . '_' . uniqid() . '.' . $extension; // Ensure unique name
        
            // 1. Save file to S3
            $s3Path = "uploads/{$platform}/{$mediaType}/{$fileName}";
            Storage::disk('s3')->put(
                $s3Path,
                file_get_contents($media->getRealPath()),
                ['visibility' => 'public']
            );
        
            $filePath = Storage::disk('s3')->url($s3Path);
        
            // 2. Create the Media Container in Snapchat
            $createContainerPayload = [
                'media' => [[
                    'ad_account_id' => $this->account->ad_account_id,
                    'type'          => strtoupper($mediaType), // IMAGE or VIDEO
                    'name'          => $fileName,
                ]]
            ];
            $containerEndpoint = $this->config . "adaccounts/{$this->account->ad_account_id}/media";
        
            $response = $this->apiService->post($containerEndpoint, $this->header['data'], $createContainerPayload);
        
            if (!$response['success']) {
                return $this->errorResponse($response['data']['debug_message'] ?? $response['data']['display_message']);
            } else if ($response['data']['request_status'] == 'ERROR') {
                return $this->errorResponse($response['data']['media'][0]['sub_request_error_reason']);
            }
        
            $snapMedia = $response['data']['media'][0]['media'];
            $snapMediaId = $snapMedia['id'];
        
            // 3. Upload the actual binary file for THIS specific media_id
            $uploadEndpoint = $this->config . "media/{$snapMediaId}/upload";
            
            $filePayload = [
                [
                    'name'       => 'file', // Snapchat expects the form field to be named "file"
                    'file_name'  => $fileName,
                    'media_file' => $media->getRealPath(), // Send local temporary path directly
                ]
            ];
            $headers = [
                'Authorization' => $this->header['data']['Authorization']
            ];

            $uploadResponse = $this->apiService->post($uploadEndpoint, $headers, [], 'multipart', $filePayload);
        
            if (!$uploadResponse['success']) {
                return $this->errorResponse("Failed to upload file binary for media ID: {$snapMediaId}");
            } else if ($response['data']['request_status'] == 'ERROR') {
                return $this->errorResponse($response['data']['result'][0]['sub_request_error_reason']);
            }

            $media = $uploadResponse['data']['result'];
            $id = $media['id'];
            // 4. Save to Local Database
            $dataToInsert = [
                'ad_media_id'    => $id,
                'ad_account_id'  => $this->account->id,
                'ad_campaign_id' => $request['ad_campaign_id'],
                'platform'       => 'snapchat',
                'name'           => $fileName,
                'url'            => $filePath,
                'download_link'  => $filePath,
                'type'           => $mediaType,
                'status'         => true,
                'file_name'      => $fileName,
                'image_category' => $mediaType,
                'user_id'        => Auth::user()->id,
            ];
        
            $localMedia = $this->apiService->success(
                $dataToInsert,
                ['ad_media_id' => $snapMediaId],
                new AdMedia
            );
        
            $mediaIds[] = [
                'ad_media_id' => $localMedia['data']['id'],
                'media_id'    => $snapMediaId
            ];
        }
        
        return ['success' => true, 'data' => $mediaIds];
    }

    private function storeCreative($platform, $request)
    {
        // 1. Extract media IDs array from the request
        $mediaList = $request['media'] ?? [];
        $mediaIds = array_column($mediaList, 'media_id');
    
        if (empty($mediaIds)) {
            return $this->errorResponse('At least one media_id is required to create a creative.');
        }
    
        // 2. Determine Creative Type based on media count & request
        $isMultipleMedia = count($mediaIds) > 1;
        foreach ($mediaIds as $mediaId) {
            if (!$this->ensureMediaIsReady($mediaId)) {
                return $this->errorResponse("Media ID {$mediaId} is not ready yet. Please try again.");
            }
        }
        // Snapchat base type: CAROUSEL_AD for multiple media, SNAP_AD for single media
        $creativeType = $isMultipleMedia ? 'CAROUSEL_AD' : 'SNAP_AD';
    
        // 3. Build Base Payload
        $payload = [
            'name'             => $request['name'],
            'type'             => $creativeType,
            'ad_account_id'    => $this->account->ad_account_id,
            'headline'         => $request['description'],
            'brand_name'       => $request['name'],
            'profile_properties' => [
                'profile_id'   => $this->account->profile_id
            ]
        ];
    
        // Attach media ID(s) dynamically based on single vs multiple
        if ($isMultipleMedia) {
            $payload['top_snap_media_ids'] = $mediaIds; // Array of media_ids for Carousel
        } else {
            $payload['top_snap_media_id']  = $mediaIds[0]; // Single string media_id
        }
    
        // 4. Merge action properties (WEB_VIEW, APP_INSTALL, DEEP_LINK, etc.)
        $payload = array_merge($payload, $this->creativeProperties($request));
     
        // Wrap payload in outer 'creatives' array required by Snapchat API
        $requestBody = [
            'creatives' => [
                $payload
            ]
        ];
    
        $endpoint = $this->config . "adaccounts/{$this->account->ad_account_id}/creatives";
        $response = $this->apiService->post($endpoint, $this->header['data'], $requestBody);
      
        if (!$response['success']) {
            return $this->errorResponse($response['data']['debug_message'] ?? $response['data']['display_message']);
        } else if ($response['data']['request_status'] == 'ERROR') {
            return $this->errorResponse($response['data']['creatives'][0]['sub_request_error_reason']);
        }
        // Snapchat wraps response in an array of created creatives
        $createdCreative = $response['data']['creatives'][0]['creative'] ?? $response['data'];
        $id = $createdCreative['id'];
    
        // Save to Database
        $dataToInsert = [
            'user_id'         => Auth::id(),
            'ad_adgroup_id'   => $request['ad_adgroup_id'],
            'ad_creative_id'  => $id,
            'platform'        => 'snapchat',
            'ad_account_id'   => $this->account->id,
            'ad_campaign_id'  => $request['ad_campaign_id'],
            'name'            => $request['name'],
            'ad_format'       => $request['ad_format'] ?? null,
            'message'         => $request['description'] ?? null,
            'page_id'         => $request['page_id'] ?? null,
            'call_to_action'  => $request['call_to_action'] ?? null,
            'url'             => $request['target_link'] ?? null,
            'type'            => $request['creative_type'],
            'headline'         => $request['description'],
            'brand_name'       => $request['name'],
        ];
    
        $creative = $this->apiService->success(
            $dataToInsert,
            ['ad_creative_id' => $id],
            new AdCreative
        );
    
        // Save media relationships in local DB
        foreach ($mediaList as $media) {
            $mediaToInsert = [
                'ad_media_id'    => $media['ad_media_id'], 
                'ad_creative_id' => $creative['data']['id']
            ];
            $this->apiService->success(
                $mediaToInsert,
                [
                    'ad_media_id'    => $media['ad_media_id'], 
                    'ad_creative_id' => $creative['data']['id']
                ],
                new AdCreativeMedia
            );
        }
    
        return $creative;
    }

    private function creativeProperties(array $request)
    {
        return match ($request['creative_type']) {
            'WEB_VIEW' => [
                'call_to_action' => $request['call_to_action'] ?? null,
                'web_view_properties' => [
                    'url' => $request['url'] ?? null,
                ],
            ],
            'APP_INSTALL' => [
                'call_to_action' => $request['call_to_action'] ?? null,
                'app_install_properties' => array_filter([
                    'app_name' => $request['app_name'] ?? null,
                    'icon_media_id' => $request['APP_ICON_SNAP_ADS'] ?? null,
                    'ios_app_id' => $request['ios_app_id'] ?? null,
                    'android_app_url' => $request['android_app_url'] ?? null,
                ]),
            ],
            'DEEP_LINK' => [
                'call_to_action' => $request['call_to_action'] ?? null,
                'deep_link_properties' => array_filter([
                    'app_name' => $request['app_name'] ?? null,
                    'deep_link_uri' => $request['deep_link_uri'] ?? null,
                    'fallback_type' => $request['fallback_type'] ?? null,
                    'web_view_fallback_url' => $request['web_view_fallback_url'] ?? null,
                    'ios_app_id' => $request['ios_app_id'] ?? null,
                    'android_app_url' => $request['android_app_url'] ?? null,
                    'icon_media_id' => $request['APP_ICON_SNAP_ADS'] ?? null,
                ]),
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
                // Media is transcoded and ready to be used in a creative
                if ($mediaStatus === 'READY') {
                    return true;
                }
            }


    
            // Wait 2 seconds before checking status again
            sleep(2);
            $attempt++;
        }
    
        return false;
    }
    private function storeAd($platform, $request)
    {
        $endpoint = $this->config . "adsquads/{$request['adgroup_id']}/ads";
        $payload = [
            'ads' => [
                [
                    'name' => $request['name'],
                    'ad_squad_id' => $request['adgroup_id'],
                    'type' => match ($request['creative_type']) {
                        'APP_INSTALL' => 'APP_INSTALL',
                        'SNAP_AD'     => 'SNAP_AD',
                        'WEB_VIEW'    => 'REMOTE_WEBPAGE',
                        'DEEP_LINK'   => 'DEEP_LINK',
                        default       => 'SNAP_AD',
                    },
                    'creative_id' => $request['creative_id'],
                    'status' => 'PAUSED',
                ]
            ]
        ];

        $response = $this->apiService->post($endpoint, $this->header['data'], $payload);
     
        if (!$response['success']) {
            return $this->errorResponse($response['data']['debug_message'] ?? $response['data']['display_message']);
        } else if ($response['data']['request_status'] == 'ERROR') {
            return $this->errorResponse($response['data']['ads'][0]['sub_request_error_reason']);
        }
       
        $data = $response['data']['ads'][0]['ad'] ?? $response['data'];
        $id = $data['id'];
        $dataToInsert = [
            'user_id'                  => Auth::user()->id,
            'ad_adgroup_id'            => $request['ad_adgroup_id'],
            'ad_creative_id'           => $request['ad_creative_id'],
            'ad_id'                    => $id,
            'status'                   => false,
            'platform'                 => 'snapchat',
            'type'                     => $request['creative_type'],
            'ad_account_id'            => $this->account->id,
            'ad_campaign_id'           => $request['ad_campaign_id'],
            'name'                     => $request['name'],
            'call_to_action'           => $request['call_to_action'],
        ];

        return $this->apiService->success(
            $dataToInsert,
            ['ad_id' => $id],
            new Ad
        );
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

        if (!$response['success']) {
            return $this->errorResponse($data['error_description'] ?? 'Refresh Token Error');
        }
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

    public function update($platform, $id, $request) {
        // Step 2: create campaign
        $response = $this->updateCampaign($platform, $id, $request);

        if (!$response['success']) {
            return $response;
        }
        $campaign = $response['data'];
        $request['campaign_id'] = $campaign['ad_campaign_id'];
        $request['ad_campaign_id'] = $campaign['id'];

        // Step 2: update Ad Group
        $adGroupResponse = $this->updateAdGroup($platform, $campaign['id'], $request);
      
        if (!$adGroupResponse['success']) {
            return $adGroupResponse;
        }

        $request['adgroup_id'] = $adGroupResponse['data']['ad_adgroup_id'];
        $request['ad_adgroup_id'] = $adGroupResponse['data']['id'];

        // Step 3: update Media
        if (!empty($request['media'])) {
            $response = $this->storeMedia($platform, $request);
            
            if (!$response['success']) {
                return $response;
            }
    
            $request['media'] = $response['data'];
        }
        
        // Step 4: update creative
        $response = $this->updateCreative($platform, $request, $adGroupResponse['data']);

        if (!$response['success']) {
            return $response;
        }

        $request['creative_id'] = $response['data']['ad_creative_id'];
        $request['ad_creative_id'] = $response['data']['id'];

        // Step 4: update Ad
        return $this->updateAd($platform, $request, $campaign);
        
    }

    private function updateCampaign($platform, $id, $request)
    {
        $campaign = AdCampaign::findOrFail($id);

        $endpoint = $this->config . 'campaigns/' . $campaign->ad_campaign_id;

        $payload = [
            'campaigns' => [
                [
                    'id'     => $campaign->ad_campaign_id,
                    'name'   => $request['name'],
                    'status' => 'PAUSED',
                ]
            ]
        ];

        $response = $this->apiService->put($endpoint, $this->header['data'], $payload);

        if (!$response['success']) {
            return $this->errorResponse($response['data']['display_message'] ?? $response['data']['debug_message'] ?? 'Failed to update campaign');
        } else if (($response['data']['request_status'] ?? null) === 'ERROR') {
            return $this->errorResponse($response['data']['campaigns'][0]['sub_request_error_reason'] ?? 'Failed to update campaign');
        }

        $dataToInsert = [
            'name' => $request['name'],
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

        $endpoint = $this->config . 'adsquads/' . $adGroup->ad_adgroup_id;

        [$demographicSpec, $geos, $countries] = $this->buildAudienceTargeting($request);

        $adSquad = [
            'id'                  => $adGroup->ad_adgroup_id,
            'name'                => $request['name'],
            'status'              => 'PAUSED',
            'targeting'           => [
                'demographics' => [$demographicSpec],
                'geos'         => $geos,
            ],
            'billing_event'       => 'IMPRESSION',
            'bid_strategy'        => $request['bid_strategy'],
            'start_time'          => Carbon::parse($request['start_time'])->utc()->format("Y-m-d\TH:i:s.v\Z"),
            'end_time'            => Carbon::parse($request['end_time'])->utc()->format("Y-m-d\TH:i:s.v\Z"),
            'optimization_goal'   => $request['optimization_goal'],
            'pacing_type'         => 'STANDARD',
        ];

        $budgetInMicros = (int) $request['budget'] * 1000000;

        if (($request['budget_mode'] ?? 'daily') === 'daily') {
            $adSquad['daily_budget_micro'] = $budgetInMicros;
        } else {
            $adSquad['lifetime_budget_micro'] = $budgetInMicros;
        }

        if ($request['bid_strategy'] == 'TARGET_COST') {
            $adSquad['target_bid'] = true;
            $adSquad['auto_bid'] = false;
            $adSquad['bid_micro'] = (int) bcmul((string) $request['bid_amount'], '1000000', 0);
        } else if ($request['bid_strategy'] == 'LOWEST_COST_WITH_MAX_BID') {
            $adSquad['target_bid'] = false;
            $adSquad['auto_bid'] = false;
            $adSquad['bid_micro'] = (int) bcmul((string) $request['bid_amount'], '1000000', 0);
        } else if ($request['bid_strategy'] == 'AUTO_BID') {
            $adSquad['target_bid'] = false;
            $adSquad['auto_bid'] = true;
        }

        if (!empty($request['pixel_id'])) {
            $adSquad['pixel_id'] = $request['pixel_id'];
        }

        $payload = ['adsquads' => [$adSquad]];

        $response = $this->apiService->put($endpoint, $this->header['data'], $payload);

        if (!$response['success']) {
            return $this->errorResponse($response['data']['debug_message'] ?? $response['data']['display_message'] ?? 'Failed to update ad squad');
        } else if (($response['data']['request_status'] ?? null) === 'ERROR') {
            return $this->errorResponse($response['data']['adsquads'][0]['sub_request_error_reason'] ?? 'Failed to update ad squad');
        }

        $dataToInsert = [
            'ad_campaign_id'      => $campaignId,
            'user_id'             => Auth::id(),
            'ad_adgroup_id'       => $adGroup->ad_adgroup_id,
            'ad_account_id'       => $this->account->id,
            'name'                => $request['name'],
            'location_ids'        => json_encode($countries),
            'platform'            => $platform,
            'gender'              => $request['gender'],
            'languages'           => json_encode($request['languages']),
            'budget_mode'         => $request['budget_mode'],
            'pixel_id'            => $request['pixel_id'] ?? '',
            'bid_price'           => $request['bid_amount'],
            'optimization_goal'   => $request['optimization_goal'],
            'schedule_start_time' => $request['start_time'],
            'schedule_end_time'   => $request['end_time'],
            'budget'              => $request['final_budget'],
            'age_groups'          => json_encode($request['age_range']),
            'status'              => false
        ];

        return $this->apiService->success(
            $dataToInsert,
            ['ad_adgroup_id' => $adGroup->ad_adgroup_id],
            new AdAdGroup
        );
    }

    private function updateCreative($platform, $request, $adGroup)
    {
        $creative = $adGroup->creatives->first();
        $creativeId = $creative->ad_creative_id;

        $mediaList = $request['media'] ?? [];
        $mediaIds = array_column($mediaList, 'media_id');

        foreach ($mediaIds as $mediaId) {
            if (!$this->ensureMediaIsReady($mediaId)) {
                return $this->errorResponse("Media ID {$mediaId} is not ready yet. Please try again.");
            }
        }

        $payload = [
            'id'         => $creativeId,
            'name'       => $request['name'],
            'headline'   => $request['description'],
            'brand_name' => $request['name'],
        ];

        if (!empty($mediaIds)) {
            if (count($mediaIds) > 1) {
                $payload['top_snap_media_ids'] = $mediaIds;
            } else {
                $payload['top_snap_media_id'] = $mediaIds[0];
            }
        }

        $payload = array_merge($payload, $this->creativeProperties($request));

        $endpoint = $this->config . "creatives/{$creativeId}";
        $response = $this->apiService->put($endpoint, $this->header['data'], ['creatives' => [$payload]]);

        if (!$response['success']) {
            return $this->errorResponse($response['data']['debug_message'] ?? $response['data']['display_message'] ?? 'Failed to update creative');
        } else if (($response['data']['request_status'] ?? null) === 'ERROR') {
            return $this->errorResponse($response['data']['creatives'][0]['sub_request_error_reason'] ?? 'Failed to update creative');
        }

        $dataToInsert = [
            'user_id'         => Auth::id(),
            'ad_adgroup_id'   => $request['ad_adgroup_id'],
            'ad_creative_id'  => $creativeId,
            'platform'        => 'snapchat',
            'ad_account_id'   => $this->account->id,
            'ad_campaign_id'  => $request['ad_campaign_id'],
            'name'            => $request['name'],
            'message'         => $request['description'] ?? null,
            'call_to_action'  => $request['call_to_action'] ?? null,
            'url'             => $request['target_link'] ?? null,
            'type'            => $request['creative_type'] ?? $creative->type,
            'headline'        => $request['description'],
            'brand_name'      => $request['name'],
        ];

        $updated = $this->apiService->success(
            $dataToInsert,
            ['ad_creative_id' => $creativeId],
            new AdCreative
        );

        foreach ($mediaList as $media) {
            $mediaToInsert = ['ad_media_id' => $media['ad_media_id'], 'ad_creative_id' => $updated['data']['id']];
            $this->apiService->success(
                $mediaToInsert,
                $mediaToInsert,
                new AdCreativeMedia
            );
        }

        return $updated;
    }

    private function updateAd($platform, $request, $campaign)
    {
        $ad = $campaign->ads->first();

        $endpoint = $this->config . "ads/{$ad->ad_id}";

        $payload = [
            'ads' => [
                [
                    'id'          => $ad->ad_id,
                    'name'        => $request['name'],
                    'ad_squad_id' => $request['adgroup_id'],
                    'creative_id' => $request['creative_id'],
                    'status'      => 'PAUSED',
                ]
            ]
        ];

        $response = $this->apiService->put($endpoint, $this->header['data'], $payload);

        if (!$response['success']) {
            return $this->errorResponse($response['data']['debug_message'] ?? $response['data']['display_message'] ?? 'Failed to update ad');
        } else if (($response['data']['request_status'] ?? null) === 'ERROR') {
            return $this->errorResponse($response['data']['ads'][0]['sub_request_error_reason'] ?? 'Failed to update ad');
        }

        $dataToInsert = [
            'user_id'                  => Auth::id(),
            'ad_adgroup_id'            => $request['ad_adgroup_id'],
            'ad_creative_id'           => $request['ad_creative_id'],
            'ad_id'                    => $ad->ad_id,
            'status'                   => false,
            'platform'                 => 'snapchat',
            'ad_account_id'            => $this->account->id,
            'ad_campaign_id'           => $request['ad_campaign_id'],
            'name'                     => $request['name'],
            'call_to_action'           => $request['call_to_action'] ?? null,
        ];

        return $this->apiService->success(
            $dataToInsert,
            ['ad_id' => $ad->ad_id],
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
        $creative = $adGroup?->creatives->first();
        $media = $creative->media ?? collect();
        $ad = $campaign->ads->first();

        // Snapchat has no hard-delete for ads/ad squads/campaigns - the
        // equivalent is setting status to DELETED via PUT, in
        // ad -> ad squad -> campaign order. Creatives and media do support DELETE.
        if ($ad) {
            $response = $this->apiService->put($this->config . "ads/{$ad->ad_id}", $this->header['data'], [
                'ads' => [['id' => $ad->ad_id, 'status' => 'DELETED']]
            ]);

            if (!$response['success']) {
                return $this->errorResponse($response['data']['debug_message'] ?? $response['data']['display_message'] ?? 'Failed to delete ad');
            } else if (($response['data']['request_status'] ?? null) === 'ERROR') {
                return $this->errorResponse($response['data']['ads'][0]['sub_request_error_reason'] ?? 'Failed to delete ad');
            }

            $ad->delete();
        }

        if ($creative) {
            $response = $this->apiService->delete($this->config . "creatives/{$creative->ad_creative_id}", $this->header['data']);

            if (!$response['success']) {
                return $this->errorResponse($response['data']['debug_message'] ?? $response['data']['display_message'] ?? 'Failed to delete creative');
            }

            $creative->delete();
        }

        foreach ($media as $each) {
            $response = $this->apiService->delete($this->config . "media/{$each->ad_media_id}", $this->header['data']);

            if (!$response['success']) {
                return $this->errorResponse($response['data']['debug_message'] ?? $response['data']['display_message'] ?? 'Failed to delete media');
            }

            $each->delete();
        }

        if ($adGroup) {
            $response = $this->apiService->put($this->config . "adsquads/{$adGroup->ad_adgroup_id}", $this->header['data'], [
                'adsquads' => [['id' => $adGroup->ad_adgroup_id, 'status' => 'DELETED']]
            ]);

            if (!$response['success']) {
                return $this->errorResponse($response['data']['debug_message'] ?? $response['data']['display_message'] ?? 'Failed to delete ad squad');
            } else if (($response['data']['request_status'] ?? null) === 'ERROR') {
                return $this->errorResponse($response['data']['adsquads'][0]['sub_request_error_reason'] ?? 'Failed to delete ad squad');
            }

            $adGroup->delete();
        }

        if ($campaign->ad_campaign_id) {
            $response = $this->apiService->put($this->config . "campaigns/{$campaign->ad_campaign_id}", $this->header['data'], [
                'campaigns' => [['id' => $campaign->ad_campaign_id, 'status' => 'DELETED']]
            ]);

            if (!$response['success']) {
                return $this->errorResponse($response['data']['debug_message'] ?? $response['data']['display_message'] ?? 'Failed to delete campaign');
            } else if (($response['data']['request_status'] ?? null) === 'ERROR') {
                return $this->errorResponse($response['data']['campaigns'][0]['sub_request_error_reason'] ?? 'Failed to delete campaign');
            }
        }

        $campaign->delete();

        return ['success' => true, 'data' => null];
    }
}
