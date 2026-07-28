<?php

namespace App\Services\AdServices;

use Illuminate\Support\Facades\Redirect;
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
use Illuminate\Support\Facades\Cache;
use App\Models\Country;

class FacebookAdService
{
    protected $platform, $account, $mediaAccountModel, $config, $httpClient, $apiService, $header, $state, $codeVerifier;

    public function __construct(AdAccount $account, ApiService $apiService)
    {
        $this->apiService = $apiService;
        $this->account = $account->wherePlatform('facebook')->whereUserId(Auth::user()->id)->first();
        $this->config = adminSetting('ads.facebook.base_url'); //config("services.ads.facebook");
        if ($this->account) {
            $this->header = $this->getHeaders();
        }

        $this->httpClient =  Http::class;
        $this->state = Session::get('ad_state');
        $this->platform = Session::get('ad_platform');
        $this->codeVerifier = Session::get('ad_codeverifier');
    }

    public function redirect()
    {
        $clientId = adminSetting('ads.facebook.client_id');

        return redirect("https://www.facebook.com/v25.0/dialog/oauth?client_id={$clientId}&redirect_uri={$this->getCallbackUrl()}&state={$this->state}&code_verifier={$this->codeVerifier}&scope=ads_management,ads_read");
    }

    private function getCallbackUrl()
    {
        return config('services.app_url') . '/admin/ads/facebook/callback';
    }

    public function callback($state)
    {
        $redirectUri = $this->getCallbackUrl();
        $code = request()->input('code');
        $endpoint = adminSetting('ads.facebook.endpoint.access_token');

        $response = $this->apiService->get($endpoint, [], [
            'client_id' => adminSetting('ads.facebook.client_id'),
            'client_secret' => adminSetting('ads.facebook.client_secret'),
            'grant_type' => 'authorization_code',
            'redirect_uri' => $redirectUri,
            'code' => $code, // this must be from the Facebook callback
        ]);

        $data = $response['data'];

        if (!$response['success']) {
            return $this->oauthFailureRedirect($data['error']['message'] ?? 'Failed to authorize the Facebook account.');
        }

        $accessToken = data_get($data, 'access_token');
        $expiresIn = data_get($data, 'expires_in', 3600); // Default to 3600 seconds if not found
        $accountResponse = $this->getFBAdAccount($accessToken);

        if (!($accountResponse['success'] ?? false)) {
            return $this->oauthFailureRedirect($accountResponse['error'] ?? 'No active Facebook ad account was found.');
        }

        $accountId = str_replace('act_', '', $accountResponse['facebook_account_id']);
        $instagramId = $accountResponse['instagram_account_id'] ?? null;
        $expiresAt = Carbon::now()->addSeconds($expiresIn);
        $refreshToken = data_get($data, 'refresh_token');

        AdAccount::updateOrCreate(
            ['platform' => 'facebook', 'user_id' => Auth::id(), 'ad_account_id' => $accountId],
            [
                'access_token'  => $accessToken,
                'refresh_token' => $refreshToken,
                'status'        => 'active',
                'expires_at'    => $expiresAt,
            ]
        );

        if ($instagramId) {
            AdAccount::updateOrCreate(
                ['platform' => 'instagram', 'user_id' => Auth::id()],
                [
                    'ad_account_id' => $instagramId,
                    'access_token'  => $accessToken,
                    'refresh_token' => $refreshToken,
                    'status'        => 'active',
                    'expires_at'    => $expiresAt,
                ]
            );
        }

        return redirect(Session::pull('previous_url', route('admin.ads.dashboard')))
            ->with('success', 'Facebook ad account connected successfully.');
    }

    private function oauthFailureRedirect($message)
    {
        return redirect(route('admin.ads.dashboard'))
            ->with('error', is_array($message) ? json_encode($message) : $message);
    }

    private function getFBAdAccount($accessToken)
    {
        $endpoint = adminSetting('ads.facebook.account.endpoint');

        // Get Account 
        $response = $this->httpClient::get(
            $endpoint,
            [
                'fields' => 'id,name,account_id,account_status,currency',
                'access_token' => $accessToken,
            ]
        );

        $account = $response->json();

        if (!$response->successful()) {
            return $this->errorResponse($account['error']['error_user_title'] ?? $account['error']['message']);
        }

        $accounts = [];
        foreach ($account['data'] as $account) {
            if ($account['account_status']) {
                $accountId = $account['id'];
                $instagramEndpoint = str_replace('{accountId}', $accountId, adminSetting('ads.instagram.account.endpoint'));

                $response = $this->httpClient::get(
                    $instagramEndpoint,
                    [
                        'access_token' => $accessToken,
                    ]
                );

                $instaRes = $response->json();

                if (!$response->successful()) {
                    return $this->errorResponse($instaRes['error']['error_user_title'] ?? $instaRes['error']['message']);
                }

                if (!empty($instaRes['data'])) {
                    $accounts = [
                        'facebook_account_id' => $accountId,
                        'instagram_account_id' => $instaRes['data'][0]['id'],
                    ];

                    break;
                }
            }
        }

        if (empty($accounts)) {
            return $this->errorResponse('No active Facebook ad account with a linked Instagram account was found.');
        }

        return ['success' => true, 'facebook_account_id' => $accounts['facebook_account_id'], 'instagram_account_id' => $accounts['instagram_account_id']];
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

        // Video creatives require a thumbnail image alongside the video_id.
        if ($request['media_type'] === 'VIDEO' && !empty($request['thumbnail'])) {
            $thumbnailResponse = $this->uploadImage($platform, $request, $request['thumbnail'], strtolower($request['thumbnail']->getClientOriginalExtension()));

            if (!$thumbnailResponse['success']) {
                return $thumbnailResponse;
            }

            $request['thumbnail_hash'] = $thumbnailResponse['data']['media_id'];
        }

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
        $endpoint = str_replace('{accountId}', $this->account->ad_account_id, $this->config) . '/campaigns';

        $payload = [
            'name'                => $request['name'],
            'status'              => 'PAUSED',
            'start_time'          => Carbon::parse($request['start_time'])->utc()->format('Y-m-d\TH:i:s.v\Z'),
            'stop_time'           => Carbon::parse($request['end_time'])->utc()->format('Y-m-d\TH:i:s.v\Z'),
            'special_ad_categories' => [],
            'is_adset_budget_sharing_enabled' => false,
            'objective' => $request['objective'],
        ];

        $response = $this->apiService->post($endpoint, $this->header['data'], $payload);

        if (!$response['success']) {
            return $this->errorResponse($response['data']['error']['error_user_msg'] ?? $response['data']['error']['message']);
        }

        $id = $response['data']['id'];

        $dataToInsert = [
            'ad_campaign_id'     => $id,
            'user_id'         => Auth::user()->id,
            'ad_account_id'   => $this->account->id,
            'name' => $request['name'],
            'objective' => $request['objective'],
            'platform' => $platform,
            'start_time' => $request['start_time'],
            'end_time' => $request['end_time'],
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
        $endpoint = str_replace('{accountId}', $this->account->ad_account_id, $this->config) . '/adsets';
        $adSetObjects = $this->getPromotedObject($request);
        $publisherPlatforms = [];

        if (isset($request['facebook'])) {
            $publisherPlatforms[] = 'facebook';
        }

        if (isset($request['instagram'])) {
            $publisherPlatforms[] = 'instagram';
        }
        $countries = Country::whereIn('id', $request['countries'])->pluck('code')->toArray();
        $genders = $request['gender'] == 'male' ? [1] : ($request['gender'] == 'female' ? [2] : [1, 2]);
        $locales = $this->getLocale($request['languages']);
        // $locales = collect($request['langauges'] ?? [])->map(fn($lang) => $localeMap[$lang] ?? null)->filter()->values()->toArray();

        $payload = [
            'campaign_id'       => $request['campaign_id'],
            'name'              => $request['name'],
            'bid_amount'        => $this->toMinorUnits($request['bid_amount']),
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
                'age_min' => (int) $request['age_from'],
                'age_max' => (int) $request['age_to'],
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
            $payload['daily_budget'] = $this->toMinorUnits($request['budget']);
        } else {
            $payload['lifetime_budget'] = $this->toMinorUnits($request['budget']);
        }

        if ($adSetObjects['shouldUnsetDestinationType']) {
            unset($payload['destination_type']);
        }

        $response = $this->apiService->post($endpoint, $this->header['data'], $payload);

        if (!$response['success']) {
            return $this->errorResponse($response['data']['error']['error_user_msg'] ?? $response['data']['error']['message']);
        }

        $id = $response['data']['id'];

        $dataToInsert = [
            'ad_campaign_id'     => $request['ad_campaign_id'],
            'user_id'         => Auth::user()->id,
            'ad_adgroup_id'         => $id,
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

            $result = $mediaType === 'VIDEO'
                ? $this->uploadVideo($platform, $request, $media, $extension)
                : $this->uploadImage($platform, $request, $media, $extension);

            if (!$result['success']) {
                return $result;
            }

            $mediaIds[] = $result['data'];
        }

        return ['success' => true, 'data' => $mediaIds];
    }

    /**
     * Upload a single image to S3 (for our own records) and to Meta's /adimages
     * endpoint, returning the image_hash needed by AdCreative link_data/video_data.
     */
    private function uploadImage($platform, $request, $media, $extension)
    {
        $fileName = time() . '_' . uniqid() . '.' . $extension;
        $s3Path = "uploads/{$platform}/IMAGE/{$fileName}";

        Storage::disk('s3')->put(
            $s3Path,
            file_get_contents($media->getRealPath()),
            ['visibility' => 'public']
        );

        $endpoint = str_replace('{accountId}', $this->account->ad_account_id, $this->config) . '/adimages';

        $payload = [
            'file_name' => $fileName,
            'bytes'     => base64_encode(file_get_contents($media->getRealPath())),
        ];

        $response = $this->apiService->post($endpoint, $this->header['data'], $payload);
        $result = $response['data'];

        if (!$response['success']) {
            return $this->errorResponse($result['error']['error_user_msg'] ?? $result['error']['message'] ?? 'Failed to upload image.');
        }

        $image = $result['images']['bytes'];

        $dataToInsert = [
            'ad_media_id'       => $image['hash'],
            'ad_account_id'     => $this->account->id,
            'ad_campaign_id'    => $request['ad_campaign_id'],
            'platform'          => 'facebook',
            'name'              => $fileName,
            'url'               => $image['url'],
            'download_link'     => $image['url'],
            'type'              => 'IMAGE',
            'status'            => false,
            'file_name'         => $fileName,
            'image_category'    => 'IMAGE',
            'signature'         => $image['hash'],
            'upload_by_type'    => 'UPLOAD_BY_FILE',
            'file_id'           => $image['hash'],
            'user_id'           => Auth::user()->id,
            'ad_format'         => 'FEED',
        ];

        $medias = $this->apiService->success(
            $dataToInsert,
            ['ad_media_id' => $image['hash']],
            new AdMedia
        );

        return ['success' => true, 'data' => [
            'ad_media_id' => $medias['data']['id'],
            'media_id'    => $image['hash'],
            'type'        => 'IMAGE',
        ]];
    }

    /**
     * Upload a video to Meta's /advideos endpoint. Unlike /adimages, this endpoint
     * requires an actual multipart file upload (a base64 "bytes" JSON body — the
     * approach adimages uses — is not a supported way to upload video source).
     */
    private function uploadVideo($platform, $request, $media, $extension)
    {
        $fileName = time() . '_' . uniqid() . '.' . $extension;
        $s3Path = "uploads/{$platform}/VIDEO/{$fileName}";

        Storage::disk('s3')->put(
            $s3Path,
            file_get_contents($media->getRealPath()),
            ['visibility' => 'public']
        );

        $filePath = Storage::disk('s3')->url($s3Path);
        $endpoint = str_replace('{accountId}', $this->account->ad_account_id, $this->config) . '/advideos';

        // Multipart upload must not carry a JSON content-type header.
        $authHeader = ['Authorization' => $this->header['data']['Authorization']];

        $response = $this->apiService->post(
            $endpoint,
            $authHeader,
            ['name' => $fileName],
            'multipart',
            [[
                'name'       => 'source',
                'media_file' => $media->getRealPath(),
                'file_name'  => $fileName,
            ]]
        );

        $result = $response['data'];

        if (!$response['success']) {
            return $this->errorResponse($result['error']['error_user_msg'] ?? $result['error']['message'] ?? 'Failed to upload video.');
        }

        $videoId = $result['id'];

        $dataToInsert = [
            'ad_media_id'       => $videoId,
            'ad_account_id'     => $this->account->id,
            'ad_campaign_id'    => $request['ad_campaign_id'],
            'platform'          => 'facebook',
            'name'              => $fileName,
            'url'               => $filePath,
            'download_link'     => $filePath,
            'type'              => 'VIDEO',
            'status'            => false,
            'file_name'         => $fileName,
            'image_category'    => 'VIDEO',
            'signature'         => $videoId,
            'upload_by_type'    => 'UPLOAD_BY_FILE',
            'file_id'           => $videoId,
            'user_id'           => Auth::user()->id,
            'ad_format'         => 'FEED',
        ];

        $medias = $this->apiService->success(
            $dataToInsert,
            ['ad_media_id' => $videoId],
            new AdMedia
        );

        return ['success' => true, 'data' => [
            'ad_media_id' => $medias['data']['id'],
            'media_id'    => $videoId,
            'type'        => 'VIDEO',
        ]];
    }

    private function storeCreative($platform, $request)
    {
        $endpoint = str_replace('{accountId}', $this->account->ad_account_id, $this->config) . '/adcreatives';
        $payload = [
            'name' => $request['name'],
            'object_story_spec' => [
                'page_id' => $request['page_id'],
            ],
        ];

        if (isset($request['instagram'])) {
            $loginUser = AdAccount::where('user_id', Auth::user()->id)->where('platform', 'instagram')->first();
            if ($loginUser) {
                $payload['object_story_spec']['instagram_user_id'] = $loginUser->ad_account_id;
            }
        }

        if ($request['media_type'] === 'CAROUSEL') {
            $linkData = [
                'message' => $request['description'],
                'description' => $request['description'],
                'link' => $request['target_link'],
            ];

            $linkData['child_attachments'] = array_map(
                fn ($media) => ['image_hash' => $media['media_id'], 'link' => $request['target_link']],
                $request['media']
            );
            $linkData['call_to_action'] = $this->buildFacebookCTAPayload($request['call_to_action'], $request['target_link']);

            $payload['object_story_spec']['link_data'] = $linkData;
        } else if ($request['media_type'] === 'IMAGE') {

            $linkData = [
                'link' => $request['target_link'],
                "description" => $request['description'],
                "message" => $request['description'],
                'image_hash' => $request['media'][0]['media_id']
            ];

            $linkData['call_to_action'] = $this->buildFacebookCTAPayload($request['call_to_action'], $request['target_link']);
            $payload['object_story_spec']['link_data'] = $linkData;
        } else if ($request['media_type'] === 'VIDEO') {

            $linkData = [
                'video_id' => $request['media'][0]['media_id'],
                'title' => $request['name'],
                'link_description' => $request['description'],
            ];

            if (!empty($request['thumbnail_hash'])) {
                $linkData['image_hash'] = $request['thumbnail_hash'];
            }

            $linkData['call_to_action'] = $this->buildFacebookCTAPayload($request['call_to_action'], $request['target_link']);

            $payload['object_story_spec']['video_data'] = $linkData;
        }

        $response = $this->apiService->post($endpoint, $this->header['data'], $payload);

        if (!$response['success']) {
            return $this->errorResponse($response['data']['error']['error_user_msg'] ?? $response['data']['error']['message']);
        }

        $id = $response['data']['id'];

        $dataToInsert = [
            'user_id'                  => Auth::user()->id,
            'ad_adgroup_id'            => $request['ad_adgroup_id'],
            'ad_creative_id'              => $id,
            'platform'                 => 'facebook',
            'ad_account_id'            => $this->account->id,
            'ad_campaign_id'           => $request['ad_campaign_id'],
            'name'                     => $request['name'],
            'ad_format'                => $request['ad_format'] ?? null,
            'message'                  => $request['description'] ?? null,
            'page_id'                  => $request['page_id'] ?? null,
            'call_to_action'           => $request['call_to_action'] ?? null,
            'url'                      => $request['target_link'] ?? null,
            'type'                     => $request['media_type'],
        ];



        $creative  = $this->apiService->success(
            $dataToInsert,
            ['ad_creative_id' => $id],
            new AdCreative
        );

        foreach ($request['media'] as $media) {
            $mediaToInsert = ['ad_media_id' => $media['ad_media_id'], 'ad_creative_id' => $creative['data']['id']];
            $this->apiService->success(
                $mediaToInsert,
                ['ad_media_id' => $media['ad_media_id'], 'ad_creative_id' => $creative['data']['id']],
                new AdCreativeMedia
            );
        }

        return $creative;
    }

    private function storeAd($platform, $request)
    {
        $endpoint = str_replace('{accountId}', $this->account->ad_account_id, $this->config) . '/ads';


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

        $id = $response['data']['id'];

        $dataToInsert = [
            'user_id'                  => Auth::user()->id,
            'ad_adgroup_id'            => $request['ad_adgroup_id'],
            'ad_creative_id'           => $request['ad_creative_id'],
            'ad_id'                    => $id,
            'status'                   => false,
            'platform'                 => 'facebook',
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
            $response = $this->refreshToken($this->account->access_token);

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

    public function refreshToken($accessToken)
    {
        $endpoint = adminSetting('ads.facebook.access_token');
        $response = $this->apiService->get($endpoint, [], [
            'grant_type' => 'fb_exchange_token',
            'client_id' => adminSetting('ads.facebook.client_id'),
            'client_secret' => adminSetting('ads.facebook.client_secret'),
            'fb_exchange_token' => $accessToken,
        ]);


        if ($response['success']) {
            $this->account->access_token = $response['data']['access_token'];
            $this->account->expires_at = Carbon::now()->addSeconds(3600);

            $this->account->save();

            $this->account->refresh();

            return $this->successResponse($this->account->access_token);
        }

        if (!$response['success']) {
            return $this->errorResponse($response['data']['error']['error_user_msg'] ?? $response['data']['error']['message']);
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
        // NOTE: previously this hard-coded $objective = 'OUTCOME_TRAFFIC' regardless
        // of what the advertiser actually selected, which meant Awareness/Sales/Leads/
        // Engagement/App Promotion campaigns always built their promoted_object (and
        // destination_type) as if they were Traffic campaigns. Must read the real value.
        $objective = $request['objective'] ?? 'OUTCOME_TRAFFIC';
        $goal = $request['optimization_goal'] ?? null;
        $destinationType = $request['destination_type'] ?? null;

        $promotedObjectRules = [
            'OUTCOME_AWARENESS' => [
                'default' => ['page_id'],
            ],
            'OUTCOME_TRAFFIC' => [
                'default' => [],
            ],
            'OUTCOME_ENGAGEMENT' => [
                'OFFSITE_CONVERSIONS' => ['pixel_id', 'custom_event_type'],
                'default' => ['page_id'],
            ],
            'OUTCOME_APP_PROMOTION' => [
                'default' => ['application_id', 'object_store_url'],
            ],
            'OUTCOME_LEADS' => [
                'OFFSITE_CONVERSIONS' => ['pixel_id', 'custom_event_type'],
                'default' => ['page_id'],
            ],
            'OUTCOME_SALES' => [
                'default' => ['pixel_id', 'custom_event_type'],
            ],
        ];

        $fields = $promotedObjectRules[$objective][$goal]
            ?? ($promotedObjectRules[$objective]['default'] ?? []);

        // A website/app destination on Traffic needs its own promoted_object fields,
        // independent of the objective-level rules above.
        if ($objective === 'OUTCOME_TRAFFIC') {
            if ($destinationType === 'APP') {
                $fields = ['application_id', 'object_store_url'];
            } elseif (in_array($destinationType, ['MESSENGER', 'WHATSAPP'], true)) {
                $fields = ['page_id'];
            }
        }

        $promotedObject = [];

        foreach ($fields as $field) {
            if (!empty($request[$field])) {
                $promotedObject[$field] = $request[$field];
            }
        }

        // destination_type only applies to AdSets built around Traffic (website/app/
        // messaging destinations) or Engagement (messaging); every other objective
        // must not send it at all.
        $shouldUnsetDestinationType = empty($destinationType)
            || !in_array($objective, ['OUTCOME_TRAFFIC', 'OUTCOME_ENGAGEMENT'], true);

        return [
            'promoted_objects' => $promotedObject,
            'shouldUnsetDestinationType' => $shouldUnsetDestinationType
        ];
    }

    /**
     * Convert a decimal advertiser-facing amount into the ad account currency's
     * smallest billable unit, per Meta's documented currency offsets
     * (https://developers.facebook.com/docs/marketing-api/currencies/).
     */
    private function toMinorUnits(float $amount): int
    {
        $zeroDecimalCurrencies = [
            'CLP', 'COP', 'CRC', 'HUF', 'ISK', 'IDR', 'JPY', 'KRW', 'PYG', 'TWD', 'VND',
        ];

        $currency = strtoupper($this->account->currency ?? 'USD');
        $offset = in_array($currency, $zeroDecimalCurrencies, true) ? 1 : 100;

        return (int) round($amount * $offset);
    }

    private function getLocale($languages)
    {
        $endpoint = 'https://graph.facebook.com/v22.0/search?type=adlocale&q=';
        $languageCodes = ['english' => 'en', 'arabic' => 'ar'];

        return collect($languages)
            ->map(function ($language) use ($endpoint, $languageCodes) {
                $code = $languageCodes[$language] ?? $language;

                return Cache::remember("facebook_ad_locale_{$code}", now()->addDay(), function () use ($endpoint, $code) {
                    $response = $this->apiService->get($endpoint, $this->header['data'], [
                        'type' => 'adlocale',
                        'q' => $code,
                        'limit' => 2,
                    ]);

                    return $response['data']['data'][0]['key'] ?? null;
                });
            })
            ->filter()
            ->values()
            ->all();
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

    /**
     * Almost every AdCreativeLinkDataCallToAction type takes the destination
     * link in value.link; only these carry no link (no-op / page-native actions).
     */
    private function buildFacebookCTAPayload($ctaType, $url)
    {
        $noLinkTypes = ['INTERESTED', 'NO_BUTTON', 'OPEN_INSTANT_APP'];

        $ctaPayload = [
            'type' => $ctaType,
            'value' => [],
        ];

        if (!in_array($ctaType, $noLinkTypes, true)) {
            $ctaPayload['value']['link'] = $url;
        }

        return $ctaPayload;
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

        if ($request['media_type'] === 'VIDEO' && !empty($request['thumbnail'])) {
            $thumbnailResponse = $this->uploadImage($platform, $request, $request['thumbnail'], strtolower($request['thumbnail']->getClientOriginalExtension()));

            if (!$thumbnailResponse['success']) {
                return $thumbnailResponse;
            }

            $request['thumbnail_hash'] = $thumbnailResponse['data']['media_id'];
        }

        // Step 4: update creative
        $response = $this->updateCreative($platform, $request, $adGroupResponse['data']);
        
        $request['creative_id'] = $response['data']['ad_creative_id'];
        $request['ad_creative_id'] = $response['data']['id'];

        // Step 4: update Ad
        return $this->updateAd($platform, $request, $campaign);
        
    }

    private function updateCampaign($platform, $id, $request)
    {
        $campaign = AdCampaign::find($id);

        $endpoint = "https://graph.facebook.com/v25.0/{$campaign->ad_campaign_id}";
      
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
       
        $endpoint = "https://graph.facebook.com/v25.0/{$adGroup->ad_adgroup_id}";
        $adSetObjects = $this->getPromotedObject($request);
       
        $publisherPlatforms = [];

        if (isset($request['facebook'])) {
            $publisherPlatforms[] = 'facebook';
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
            'bid_amount'        => $this->toMinorUnits($request['bid_amount']),
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
                'age_min' => (int) $request['age_from'],
                'age_max' => (int) $request['age_to'],
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
            $payload['daily_budget'] = $this->toMinorUnits($request['budget']);
        } else {
            $payload['lifetime_budget'] = $this->toMinorUnits($request['budget']);
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
        $endpoint = "https://graph.facebook.com/v25.0/{$creativeId}";
        $payload = [
            'name' => $request['name'],
            'object_story_spec' => [
                'page_id' => $request['page_id'],
            ],
        ];

        if (isset($request['instagram'])) {
            $loginUser = AdAccount::where('user_id', Auth::user()->id)->where('platform', 'instagram')->first();
            if ($loginUser) {
                $payload['object_story_spec']['instagram_user_id'] = $loginUser->ad_account_id;
            }
        }

        if ($request['media_type'] === 'CAROUSEL') {
            $linkData = [
                'message' => $request['description'],
                'description' => $request['description'],
                'link' => $request['target_link'],
            ];

            $linkData['child_attachments'] = array_map(
                fn ($media) => ['image_hash' => $media['media_id'], 'link' => $request['target_link']],
                $request['media']
            );
            $linkData['call_to_action'] = $this->buildFacebookCTAPayload($request['call_to_action'], $request['target_link']);

            $payload['object_story_spec']['link_data'] = $linkData;
        } else if ($request['media_type'] === 'IMAGE') {
            $linkData = [
                'link' => $request['target_link'],
                "description" => $request['description'],
                "message" => $request['description'],
                'image_hash' => $request['media'][0]['media_id']
            ];

            $linkData['call_to_action'] = $this->buildFacebookCTAPayload($request['call_to_action'], $request['target_link']);
            $payload['object_story_spec']['link_data'] = $linkData;
        } else if ($request['media_type'] === 'VIDEO') {

            $linkData = [
                'video_id' => $request['media'][0]['media_id'],
                'title' => $request['name'],
                'link_description' => $request['description'],
            ];

            if (!empty($request['thumbnail_hash'])) {
                $linkData['image_hash'] = $request['thumbnail_hash'];
            }

            $linkData['call_to_action'] = $this->buildFacebookCTAPayload($request['call_to_action'], $request['target_link']);

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
            'platform'                 => 'facebook',
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

        $endpoint = "https://graph.facebook.com/v25.0/{$ad->ad_id}";


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
            'platform'                 => 'facebook',
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

            $endpoint = "https://graph.facebook.com/v25.0/{$ad->ad_id}";
    
            $response = $this->apiService->delete(
                $endpoint,
                $this->header['data']
            );
    
            if (!$response['success']) {
                return $this->errorResponse($response['data']['error']['error_user_msg'] ?? $response['data']['error']['message']);
            }

            $ad->delete();
        }

        // Delete Creative
        if ($creative) {

            $endpoint = "https://graph.facebook.com/v25.0/{$creative->ad_creative_id}";

            $response = $this->apiService->delete(
                $endpoint,
                $this->header['data']
            );

            if (!$response['success']) {
                return $this->errorResponse($response['data']['error']['error_user_msg'] ?? $response['data']['error']['message']);
            }

            $creative->delete();
        }

        // Delete Media (images/videos are per-file, so branch on each item's own type)
        if (count($media)) {
            foreach ($media as $each) {
                if ($each->type === 'VIDEO') {
                    $endpoint = str_replace('{accountId}', $this->account->ad_account_id, $this->config) . '/advideos';
                } else {
                    $endpoint = str_replace('{accountId}', $this->account->ad_account_id, $this->config) . '/adimages';
                }

                $response = $this->apiService->delete(
                    $endpoint,
                    $this->header['data'],
                    ['hash' => $each->ad_media_id]
                );

                if (!$response['success']) {
                    return $this->errorResponse($response['data']['error']['error_user_msg'] ?? $response['data']['error']['message']);
                }

                $each->delete();
            }
        }
      




        // Delete Ad Group
        if ($adGroup) {
    
            $endpoint = "https://graph.facebook.com/v25.0/{$adGroup->ad_adgroup_id}";
    
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
    
            $endpoint = "https://graph.facebook.com/v25.0/{$campaign->ad_campaign_id}";
    
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
