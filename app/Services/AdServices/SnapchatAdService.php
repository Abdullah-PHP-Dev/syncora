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
     //   return config('app.url') . '/admin/ads/snapchat/callback';
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

    private function storeAdGroup($platform, $request)
    {
        // dd($request);
        $endpoint = $this->config . 'campaigns/' . $request['campaign_id'] . '/adsquads';
       // 1. Map Gender (Snapchat accepts 'MALE', 'FEMALE', or omit for ALL)
        $genderMap = [
            'male'   => 'MALE',
            'female' => 'FEMALE',
        ];
        $languageMap = [
            'English' => 'en',
            'Arabic'  => 'ar',
            'Spanish' => 'es',
            'French'  => 'fr',
            // add additional languages as needed
        ];
        $gender = isset($request['gender'], $genderMap[$request['gender']]) 
            ? $genderMap[$request['gender']] 
            : null;

        $ageGroups = [];

        if (!empty($request['age_range']) && is_array($request['age_range'])) {
            foreach ($request['age_range'] as $range) {
                if (isset($ageGroupMap[$range])) {
                    $ageGroups[] = $ageGroupMap[$range];
                } else {
                    // Dynamic fallback: transforms "AGE_18_24" -> "18-24"
                    $ageGroups[] = str_replace(['AGE_', '_'], ['', '-'], $range);
                }
            }
        }

        $languages = [];

        if (!empty($request['languages']) && is_array($request['languages'])) {
            foreach ($request['languages'] as $lang) {
                // Checks if input is key ('English') or already ISO code ('en')
                $code = $languageMap[$lang] ?? strtolower($lang);
                $languages[] = $code; // Store plain string instead of ['code' => $code]
            }
        }
        
        // Ensure values are unique and reset array keys
        if (!empty($languages)) {
            $demographicSpec['languages'] = array_values(array_unique($languages));
        }

        if ($gender) {
            $demographicSpec['gender'] = $gender;
        }

        if (!empty($ageGroups)) {
            $demographicSpec['age_groups'] = array_values(array_unique($ageGroups));
        }
    
        // 4. Construct Geos block (Country targeting)
        // Note: Pass country code as ISO-2 string (e.g., "SA", "US"). Ensure country lookup maps '187' to ISO code if needed.
        $countCodes = [];
      //  foreach ($request['countries'] as $country) {
        $countries = Country::whereIn('id', $request['countries'])
            ->pluck('code')
            ->toArray();
        
        // Map each code into its own ['country_code' => 'xx'] array
        $geos = array_map(function ($code) {
            return [
                'country_code' => strtolower($code)
            ];
        }, $countries);

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

    private function getPromotedObject($request)
    {
        $promotedObjectRules = [
            'OUTCOME_AWARENESS' => [
                'default' => ['page_id'],
                'IMPRESSIONS' => ['page_id'],
                'REACH' => ['page_id'],
                'VIDEO_VIEWS' => ['page_id'],
                'THRUPLAY' => ['page_id'],
                'TWO_SECOND_CONTINUOUS_VIDEO_VIEWS' => ['page_id'],
            ],
            'OUTCOME_TRAFFIC' => [
                'LINK_CLICKS' => ['application_id', 'object_store_url'],
                'REACH' => ['application_id', 'object_store_url'],
            ],
            'OUTCOME_ENGAGEMENT' => [
                'PAGE_LIKES' => ['page_id'],
                'QUALITY_LEAD' => ['page_id'],
                'LINK_CLICKS' => ['page_id'],
                'CONVERSIONS' => ['page_id'],
                'LEAD_GENERATION' => ['page_id'],
                'OFFSITE_CONVERSIONS' => ['pixel_id', 'custom_event_type', 'application_id', 'object_store_url'],
            ],
            'OUTCOME_APP_PROMOTION' => [
                'LINK_CLICKS' => ['application_id', 'object_store_url'],
                'APP_INSTALLS' => ['application_id', 'object_store_url'],
                'OFFSITE_CONVERSIONS' => ['application_id', 'object_store_url'],
            ],
            'OUTCOME_LEADS' => [
                'LEAD_GENERATION' => ['page_id'],
                'QUALITY_LEAD' => ['page_id'],
                'OFFSITE_CONVERSIONS' => ['pixel_id', 'custom_event_type', 'application_id', 'object_store_url'],
            ],
            'OUTCOME_SALES' => [
                'OFFSITE_CONVERSIONS' => ['pixel_id', 'application_id', 'object_store_url'],
                'CONVERSIONS' => ['page_id', 'pixel_id', 'custom_event_type'],
                'LINK_CLICKS' => ['product_catalog_id', 'product_set_id', 'custom_event_type'],
            ],
        ];

        $promotedObject = [];

        $objective = 'OUTCOME_TRAFFIC';
        $goal = $request['optimization_goal'];

        $fields = $promotedObjectRules[$objective][$goal]
            ?? ($promotedObjectRules[$objective]['default'] ?? []);

        foreach ($fields as $field) {
            if (!empty($request[$field])) {
                $promotedObject[$field] = $request[$field];
            }
        }

        $goal = $request['optimization_goal'] ?? null;

        $outcomeLeadsGoals = ['OFFSITE_CONVERSIONS', 'LINK_CLICKS', 'REACH', 'LANDING_PAGE_VIEWS', 'IMPRESSIONS'];
        $outcomeEngagementGoals = ['OFFSITE_CONVERSIONS', 'LINK_CLICKS', 'REACH', 'LANDING_PAGE_VIEWS', 'IMPRESSIONS'];
        $outcomeTrafficGoals = ['LINK_CLICKS', 'LANDING_PAGE_VIEWS', 'REACH', 'IMPRESSIONS'];


        $shouldUnsetDestinationType =
            $objective === 'OUTCOME_AWARENESS' ||
            ($objective === 'OUTCOME_SALES' && $goal === 'OFFSITE_CONVERSIONS') ||
            ($objective === 'OUTCOME_LEADS' && in_array($goal, $outcomeLeadsGoals, true)) ||
            ($objective === 'OUTCOME_ENGAGEMENT' && in_array($goal, $outcomeEngagementGoals, true)) ||
            ($objective === 'OUTCOME_TRAFFIC' && in_array($goal, $outcomeTrafficGoals, true));

        return [
            'promoted_objects' => $promotedObject,
            'shouldUnsetDestinationType' => $shouldUnsetDestinationType
        ];
    }

    private function getLocale($languages)
    {
        $endpoint = 'https://graph.snapchat.com/v22.0/search?type=adlocale&q=';
        $locals = [];
        foreach ($languages as $language) {
            $response = $this->apiService->get($endpoint, $this->header['data'], [
                'type' => 'adlocale',
                'q' => $language == 'english' ? 'en' : 'ar',
                'limit' => 2,
            ]);

            $locals[] = $response['data']['data'][0]['key'];
        }

        return $locals;
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
        dd($response);
        $request['creative_id'] = $response['data']['ad_creative_id'];
        $request['ad_creative_id'] = $response['data']['id'];

        // Step 4: update Ad
        return $this->updateAd($platform, $request, $campaign);
        
    }

    private function updateCampaign($platform, $id, $request)
    {
        $campaign = AdCampaign::find($id);

        $endpoint = "https://graph.snapchat.com/v25.0/{$campaign->ad_campaign_id}";
      
        $payload = [
            'name'                => $request['name'],
            'status'              => 'PAUSED',
        ];

        $response = $this->apiService->post($endpoint, $this->header['data'], $payload);

        if (!$response['success']) {
            return $this->errorResponse($response['data']['error']['error_user_msg'] ?? $response['data']['error']['message']);
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
        $adGroup = AdAdGroup::whereAdCampaignId($campaignId)->first();
       
        $endpoint = "https://graph.snapchat.com/v25.0/{$adGroup->ad_adgroup_id}";
        $adSetObjects = $this->getPromotedObject($request);
       
        $publisherPlatforms = [];

        if (isset($request['snapchat'])) {
            $publisherPlatforms[] = 'snapchat';
        }

        if (isset($request['instagram'])) {
            $publisherPlatforms[] = 'instagram';
        }
        $countries = Country::whereIn('id', $request['countries'])->pluck('code')->toArray();
       
        $genders = $request['gender'] == 'male' ? [1] : ($request['gender'] == 'female' ? [2] : [1, 2]);
        $locales = $this->getLocale($request['languages']);
        // $locales = collect($request['langauges'] ?? [])->map(fn($lang) => $localeMap[$lang] ?? null)->filter()->values()->toArray();

        $payload = [
           // 'campaign_id'       => $request['campaign_id'],
            'name'              => $request['name'],
            'bid_amount'        => $request['bid_amount'],
            'billing_event'     => $request['billing_event'] ?? null,
            'optimization_goal' => $request['optimization_goal'],
            'status'            => 'PAUSED',
            'start_time'          => Carbon::parse($request['start_time'])->utc()->format('Y-m-d\TH:i:s.v\Z'),
            'end_time'           => Carbon::parse($request['end_time'])->utc()->format('Y-m-d\TH:i:s.v\Z'),
            'destination_type'  => $request['destination_type'],
            'targeting'         => [
                'geo_locations' => [
                    'countries' => $countries,
                ],
                'genders' => $genders,
                'locales' => $locales,
                "age_range" => [
                    $request['age_from'],
                    $request['age_to']
                ],
                "device_platforms" => ["mobile", "desktop"],
                "targeting_automation" => [
                    "advantage_audience" => 1,
                    "individual_setting" => [
                        "age" => 1,
                        "gender" => 1
                    ]
                ],
                'publisher_platforms' => $publisherPlatforms,
            ],
            'promoted_object'   => $adSetObjects['promoted_objects'],
            'is_adset_budget_sharing_enabled' => false,
        ];

        if ($request['budget_mode'] == 'daily_budget') {
            $payload['daily_budget'] = $request['budget'] * 1000;
        } else {
            $payload['lifetime_budget'] = $request['budget'] * 1000;
        }

        if ($adSetObjects['shouldUnsetDestinationType']) {
            unset($payload['destination_type']);
        }

        $response = $this->apiService->post($endpoint, $this->header['data'], $payload);
       
        if (!$response['success']) {
            return $this->errorResponse($response['data']['error']['error_user_msg'] ?? $response['data']['error']['message']);
        }

        $dataToInsert = [
            'ad_campaign_id'     => $campaignId,
            'user_id'         => Auth::user()->id,
            'ad_adgroup_id'         => $adGroup->ad_adgroup_id,
            'ad_account_id'   => $this->account->id,
            'name' => $request['name'],
            'location_ids' => json_encode($countries),
            'promotion_target_type' => json_encode($adSetObjects['promoted_objects']),
            'platform' => $platform,
            'gender' => $request['gender'],
            'languages' => json_encode($request['languages']),
            'budget_mode' => $request['budget_mode'],
            'bid_price'        => $request['bid_amount'],
            'destination_type' => $request['destination_type'],
            'billing_event'     => $request['billing_event'] ?? null,
            'optimization_goal' => $request['optimization_goal'],
            'schedule_start_time' => $request['start_time'],
            'schedule_end_time' => $request['end_time'],
            'budget' => $request['final_budget'],
            'age_groups' => json_encode([
                'age_from' => $request['age_from'],
                'age_to' => $request['age_to'],
            ]),
            'publisher_platforms' => json_encode($publisherPlatforms),
            'status' => false
        ];

        return $this->apiService->success(
            $dataToInsert,
            ['ad_adgroup_id' => $adGroup->ad_adgroup_id],
            new AdAdGroup
        );
    }

    private function updateCreative($platform, $request, $adGroup)
    {
        $creativeId = $adGroup->creatives->first()->ad_creative_id;
        $endpoint = "https://graph.snapchat.com/v25.0/{$creativeId}";
        $payload = [
            'name' => $request['name'],
            'object_story_spec' => [
                'page_id' => $request['page_id'],
            ],
        ];

        if (isset($request['instagram'])) {
            $loginUser = AdAccount::where('user_id', Auth::user()->id)->where('platform', 'instagram')->first();
            if ($loginUser) {
                $payload['object_story_spec']['instagram_user_id'] = $loginUser->account_id;
            }
        }

        if ($request['media_type'] === 'CAROUSEL') {
            $linkData = [
                'message' => $request['description'],
                'description' => $request['description'],
                'link' => $request['target_link'],
            ];

            $linkData['child_attachments'] = [['image_hash' => $request['admedia_id'], 'link' => $request['target_link']]];
            $linkData['call_to_action'] =     $request['call_to_action'];

            $newPayload['object_story_spec']['link_data'] = $linkData;
        } else if ($request['media_type'] === 'IMAGE') {
            $linkData = [
                'link' => $request['target_link'],
                "description" => $request['description'],
                "message" => $request['description'],
                'image_hash' => $request['media'][0]['media_id']
            ];

            $linkData['call_to_action'] = $this->buildsnapchatCTAPayload($request['call_to_action'], $request['target_link']);
            $payload['object_story_spec']['link_data'] = $linkData;
        } else if ($request['media_type'] === 'VIDEO') {

            $linkData = [
                'image_url' => $request['image_url'],
                'video_id' => $request['video_id'],
                'description' => $request['description'],
                //"link_description" => "Come check out our new store in Menlo Park!", 

            ];
            $linkData['call_to_action'] = $this->buildsnapchatCTAPayload($request['call_to_action'], $request['target_link']);


            $payload['object_story_spec']['video_data'] = $linkData;
        }

        $response = $this->apiService->post($endpoint, $this->header['data'], $payload);
       
        if (!$response['success']) {
            return $this->errorResponse($response['data']['error']['error_user_msg'] ?? $response['data']['error']['message']);
        }

        $dataToInsert = [
            'user_id'                  => Auth::user()->id,
            'ad_adgroup_id'            => $request['ad_adgroup_id'],
            'ad_creative_id'              => $creativeId,
            'platform'                 => 'snapchat',
            'ad_account_id'            => $this->account->id,
            'ad_campaign_id'           => $request['ad_campaign_id'],
            'name'                     => $request['name'],
            'ad_format'                => $request['ad_format'] ?? null,
            'message'                  => $request['description'] ?? null,
            'page_id'                  => $request['page_id'] ?? null,
            'call_to_action'           => $request['call_to_action'] ?? null,
            'url'                      => $request['target_link'] ?? null
        ];



        $creative  = $this->apiService->success(
            $dataToInsert,
            ['ad_creative_id' => $creativeId],
            new AdCreative
        );

        foreach ($request['media'] as $media) {
            $mediaToInsert = ['ad_media_id' => $media['ad_media_id'], 'ad_creative_id' => $creative['data']['id']];
            $this->apiService->success(
                $mediaToInsert,
                ['ad_media_id' => $media['ad_media_id']],
                new AdCreativeMedia
            );
        }

        return $creative;
    }

    private function updateAd($platform, $request, $campaign)
    {
        $ad = $campaign->ads->first();

        $endpoint = "https://graph.snapchat.com/v25.0/{$ad->ad_id}";


        $payload = [
            'name' => $request['name'],
            'adset_id' => $request['adgroup_id'],
            'status' => 'PAUSED',
            'creative' => [
                'creative_id' => $request['creative_id'],
            ],
        ];

        $response = $this->apiService->post($endpoint, $this->header['data'], $payload);

        if (!$response['success']) {
            return $this->errorResponse($response['data']['error']['error_user_msg'] ?? $response['data']['error']['message']);
        }

        $dataToInsert = [
            'user_id'                  => Auth::user()->id,
            'ad_adgroup_id'            => $request['ad_adgroup_id'],
            'ad_creative_id'           => $request['ad_creative_id'],
            'ad_id'                    => $ad->ad_id,
            'status'                   => false,
            'platform'                 => 'snapchat',
            'ad_account_id'            => $this->account->id,
            'ad_campaign_id'           => $request['ad_campaign_id'],
            'name'                     => $request['name'],
            'call_to_action'           => $request['call_to_action'],
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
        $media =  $creative->media;
   
        $ad = $campaign->ads->first();
       
        // Delete Ad
        if ($ad) {

            $endpoint = "https://graph.snapchat.com/v25.0/{$ad->ad_id}";
    
            $response = $this->apiService->delete(
                $endpoint,
                $this->header['data']
            );
    
            if (!$response['success']) {
                dd($response['data']);
                return $this->errorResponse($response['data']['error']['error_user_msg'] ?? $response['data']['error']['message']);
            }

            $ad->delete();
        }
       
        // Delete Creative
        if ($creative) {

            $endpoint = "https://graph.snapchat.com/v25.0/{$creative->ad_creative_id}";
         
            $response = $this->apiService->delete(
                $endpoint,
                $this->header['data']
            );

            if (!$response['success']) {
                dd($response['data']);
                return $this->errorResponse($response['data']['error']['error_user_msg'] ?? $response['data']['error']['message']);
            }

            $creative->delete();
        }

        // Delete Creative
        if (count($media)) {
            foreach ($media as $each) {
                if ($creative->type === 'IMAGE') {
                    $endpoint = str_replace('{accountId}', $this->account->ad_account_id, $this->config) . '/adimages';
                } else if ($creative->type === 'VIDEO') {
                    $endpoint = str_replace('{accountId}', $this->account->ad_account_id, $this->config) . '/advideos';
                } else if ($creative->type === 'CAROUSEL') {
                }
    
                $response = $this->apiService->delete(
                    $endpoint,
                    $this->header['data'],
                    ['hash' => $each->ad_media_id]
                );
               
                if (!$response['success']) {
                    dd($endpoint, $response);
                    return $this->errorResponse($response['data']['error']['error_user_msg'] ?? $response['data']['error']['message']);
                }

                $each->delete();
            }
        }
      




        // Delete Ad Group
        if ($adGroup) {
    
            $endpoint = "https://graph.snapchat.com/v25.0/{$adGroup->ad_adgroup_id}";
    
            $response = $this->apiService->delete(
                $endpoint,
                $this->header['data']
            );
    
            if (!$response['success']) {
                return $this->errorResponse($response['data']['error']['error_user_msg'] ?? $response['data']['error']['message']);
            }

            $adGroup->delete();
        }
    
    
        // Delete Campaign
        if ($campaign->ad_campaign_id) {
    
            $endpoint = "https://graph.snapchat.com/v25.0/{$campaign->ad_campaign_id}";
    
            $response = $this->apiService->delete(
                $endpoint,
                $this->header['data']
            );

            if (!$response['success']) {
                return $this->errorResponse($response['data']['error']['error_user_msg'] ?? $response['data']['error']['message']);
            }
        }
    
    
        $campaign->delete();
    
    
        return $response;
    }
}
