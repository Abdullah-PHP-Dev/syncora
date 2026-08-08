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
        return route('admin.ads.platform.callback', 'facebook');
        //   return config('app.url') . '/admin/ads/facebook/callback';
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
            return redirect()->route('admin.ads.dashboard')->with('error', data_get($data, 'error.message', 'Facebook did not return an access token.'));
        }

        $accessToken = data_get($data, 'access_token');
        $expiresIn = data_get($data, 'expires_in', 3600); // Default to 3600 seconds if not found

        $accountResponse = $this->getFBAdAccount($accessToken);

        if (!$accountResponse['success']) {
            return redirect()->route('admin.ads.dashboard')->with('error', $accountResponse['error']);
        }

        $accountId = str_replace('act_', '', $accountResponse['facebook_account_id']);
        $expiresAt = Carbon::now()->addSeconds($expiresIn);

        $this->apiService->success(
            [
                'platform'      => 'facebook',
                'user_id'       => Auth::user()->id,
                'name'          => $accountResponse['name'] ?? "Facebook Ad Account {$accountId}",
                'currency'      => $accountResponse['currency'] ?? null,
                'ad_account_id' => $accountId,
                'access_token'  => $accessToken,
                'refresh_token' => data_get($data, 'refresh_token'),
                'expires_at'    => $expiresAt,
                'status'        => 'active',
            ],
            ['platform' => 'facebook', 'ad_account_id' => $accountId, 'user_id' => Auth::user()->id],
            new AdAccount
        );

        if (!empty($accountResponse['instagram_account_id'])) {
            $this->apiService->success(
                [
                    'platform'      => 'instagram',
                    'user_id'       => Auth::user()->id,
                    'name'          => $accountResponse['name'] ?? "Instagram Account {$accountResponse['instagram_account_id']}",
                    'ad_account_id' => $accountResponse['instagram_account_id'],
                    'access_token'  => $accessToken,
                    'refresh_token' => data_get($data, 'refresh_token'),
                    'expires_at'    => $expiresAt,
                    'status'        => 'active',
                ],
                ['platform' => 'instagram', 'ad_account_id' => $accountResponse['instagram_account_id'], 'user_id' => Auth::user()->id],
                new AdAccount
            );
        }

        return redirect()->route('admin.ads.dashboard')->with('success', 'Facebook ad account connected successfully.');
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
                $endpoint = adminSetting('ads.instagram.account.endpoint');
                // $this->config['base_url'] . $accountId . '/instagram_accounts',
                $response = $this->httpClient::get(
                    $this->config['base_url'] . $accountId . '/instagram_accounts',
                    [
                        'access_token' => $accessToken,
                    ]
                );

                $instaRes = $response->json();

                if (!$response->successful()) {
                    return $this->errorResponse($instaRes['error']['error_user_title'] ?? $instaRes['error']['message']);
                }

                if (!empty($response->json()['data'])) {
                    $accounts = [
                        'facebook_account_id' => $accountId,
                        'instagram_account_id' => $response->json()['data'][0]['id'],
                        'name' => $account['name'] ?? null,
                        'currency' => $account['currency'] ?? null,
                    ];

                    break;
                }
            }
        }

        if (empty($accounts)) {
            return $this->errorResponse('No active Facebook ad account was found for this user.');
        }

        return [
            'success' => true,
            'facebook_account_id' => $accounts['facebook_account_id'],
            'instagram_account_id' => $accounts['instagram_account_id'] ?? null,
            'name' => $accounts['name'] ?? null,
            'currency' => $accounts['currency'] ?? null,
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

        if ($request['media_type'] === 'VIDEO' && !empty($request['thumbnail'])) {
            $thumbnailResponse = $this->uploadThumbnail($request['thumbnail']);

            if (!$thumbnailResponse['success']) {
                return $thumbnailResponse;
            }

            $request['thumbnail_hash'] = $thumbnailResponse['data']['hash'];
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
            'creation_state'      => 'PUBLISHED',
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

        $cards = $request['media_type'] === 'CAROUSEL'
            ? (json_decode($request['carousel_cards'] ?? '[]', true) ?: [])
            : [];

        foreach ($request['media'] as $index => $media) {
            $card = $cards[$index] ?? [];
            $extension = strtolower($media->getClientOriginalExtension());
            $mediaType = $this->getMediaType($extension); // IMAGE | VIDEO
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

            $mediaHash = $result['data']['hash'];

            $dataToInsert = [
                'ad_media_id'       => $mediaHash,
                'ad_account_id'     => $this->account->id,
                'ad_campaign_id'    => $request['ad_campaign_id'],
                'platform'          => 'facebook',
                'name'              => $fileName,
                'url'               => $result['data']['url'] ?? $filePath,
                'download_link'     => $result['data']['url'] ?? $filePath,
                'type'              => $mediaType,
                'status'            => false,
                'file_name'         => $fileName,
                'image_category'    => $mediaType,
                'signature'         => $mediaHash,
                'upload_by_type'    => 'UPLOAD_BY_FILE',
                'file_id'           => $mediaHash,
                'user_id'           => Auth::user()->id,
                'ad_format'         => 'FEED',
                'title'             => $card['title'] ?? null,
                'description'       => $card['description'] ?? null,
            ];

            $medias = $this->apiService->success(
                $dataToInsert,
                ['ad_media_id' => $mediaHash],
                new AdMedia
            );

            $mediaIds[] = [
                'ad_media_id' => $medias['data']['id'],
                'media_id' => $mediaHash,
            ];
        }

        return ['success' => true, 'data' => $mediaIds];
    }

    /**
     * Upload a single image to Meta's /adimages endpoint (base64 "bytes" body is
     * the documented way to upload image source there), returning the image_hash
     * needed by AdCreative link_data/video_data/child_attachments.
     */
    private function uploadImage($media, $fileName)
    {
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

        return ['success' => true, 'data' => ['hash' => $image['hash'], 'url' => $image['url']]];
    }

    /**
     * Upload a video to Meta's /advideos endpoint. Unlike /adimages, this endpoint
     * requires an actual multipart file upload (a base64 "bytes" JSON body - the
     * approach adimages uses - is not a supported way to upload video source).
     */
    private function uploadVideo($media, $fileName)
    {
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

        return ['success' => true, 'data' => ['hash' => $result['id'], 'url' => null]];
    }

    /**
     * Video ads need a thumbnail (image_hash) for object_story_spec.video_data -
     * uploaded the same way as any other creative image via /adimages.
     */
    private function uploadThumbnail($thumbnail)
    {
        $fileName = 'thumb_' . time() . '_' . uniqid() . '.' . strtolower($thumbnail->getClientOriginalExtension());

        return $this->uploadImage($thumbnail, $fileName);
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
                $payload['object_story_spec']['instagram_actor_id'] = $loginUser->ad_account_id;
            }
        }

        if ($request['media_type'] === 'CAROUSEL') {
            $cards = json_decode($request['carousel_cards'] ?? '[]', true) ?: [];

            $childAttachments = [];
            foreach ($request['media'] as $index => $media) {
                $card = $cards[$index] ?? [];

                $childAttachments[] = [
                    'image_hash'  => $media['media_id'],
                    'link'        => $card['link'] ?? $request['target_link'],
                    'name'        => $card['title'] ?? $request['name'],
                    'description' => $card['description'] ?? $request['description'],
                ];
            }

            $linkData = [
                'message' => $request['description'],
                'link' => $request['target_link'],
                'child_attachments' => $childAttachments,
            ];

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
        // Must read the advertiser's actual objective - every objective other than
        // Traffic needs its own promoted_object fields (page_id / pixel_id / app ids),
        // and hard-coding this to Traffic left every other objective's adset either
        // missing required fields or carrying fields Meta doesn't expect for it.
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
        // messaging destinations) or Engagement via Messenger/WhatsApp conversations.
        // Meta also requires optimization_goal=CONVERSATIONS whenever the destination
        // is Messenger/WhatsApp - enforced here too in case a request bypasses the
        // frontend's own goal-locking.
        $shouldUnsetDestinationType = empty($destinationType)
            || !in_array($objective, ['OUTCOME_TRAFFIC', 'OUTCOME_ENGAGEMENT'], true)
            || (in_array($destinationType, ['MESSENGER', 'WHATSAPP'], true) && $goal !== 'CONVERSATIONS');

        return [
            'promoted_objects' => $promotedObject,
            'shouldUnsetDestinationType' => $shouldUnsetDestinationType
        ];
    }

    private function getLocale($languages)
    {
        $endpoint = 'https://graph.facebook.com/v22.0/search?type=adlocale&q=';
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

    private function buildFacebookCTAPayload($ctaType, $url)
    {
        $ctaPayload = [
            'type' => $ctaType,
            'value' => []
        ];

        switch ($ctaType) {
            case 'BOOK_TRAVEL':
            case 'BOOK_NOW':
            case 'BUY_NOW':
            case 'PURCHASE_GIFT_CARDS':
            case 'GET_EVENT_TICKETS':
            case 'BUY_VIA_MESSAGE':
                $ctaPayload['value']['link'] = $url;
                break;

            case 'CONTACT_US':
            case 'GET_IN_TOUCH':
            case 'MAKE_AN_APPOINTMENT':
            case 'BOOK_A_CONSULTATION':
            case 'ASK_ABOUT_SERVICES':
                $ctaPayload['value']['link'] = $url;
                break;

            case 'DONATE':
            case 'DONATE_NOW':
                $ctaPayload['value']['link'] = $url;
                break;

            case 'GET_DIRECTIONS':
                $ctaPayload['value']['link'] = $url;
                break;

            case 'INTERESTED':
                // Here you might want to add custom handling for "INTERESTED" CTA
                break;

            case 'LEARN_MORE':
            case 'SEE_MORE':
            case 'OPEN_LINK':
            case 'VISIT_PROFILE':
            case 'VIEW_PRODUCT':
                $ctaPayload['value']['link'] = $url;
                break;

            case 'LIKE_PAGE':
            case 'VISIT_WORLD':
            case 'JOIN_GROUP':
            case 'JOIN_CHANNEL':
                $ctaPayload['value']['link'] = $url;
                break;

            case 'MESSAGE_PAGE':
            case 'WHATSAPP_MESSAGE':
            case 'CHAT_ON_WHATSAPP':
            case 'SEND_UPDATES':
            case 'CHAT_WITH_US':
                $ctaPayload['value']['link'] = $url;
                break;

            case 'RAISE_MONEY':
            case 'SEND_TIP':
                $ctaPayload['value']['link'] = $url;
                break;

            case 'VIEW_INSTAGRAM_PROFILE':
                $ctaPayload['value']['link'] = $url;
                break;

            case 'INSTAGRAM_MESSAGE':
                $ctaPayload['value']['link'] = $url;
                break;

            case 'LOYALTY_LEARN_MORE':
                $ctaPayload['value']['link'] = $url;
                break;

            case 'PAY_TO_ACCESS':
                $ctaPayload['value']['link'] = $url;
                break;

            case 'TRY_IN_CAMERA':
            case 'SWIPE_UP_PRODUCT':
            case 'SWIPE_UP_SHOP':
                $ctaPayload['value']['link'] = $url;
                break;

            case 'GET_MOBILE_APP':
            case 'INSTALL_MOBILE_APP':
            case 'USE_MOBILE_APP':
                $ctaPayload['value']['link'] = $url;
                break;

            case 'WATCH_VIDEO':
            case 'WATCH_MORE':
                $ctaPayload['value']['link'] = $url;
                break;

            case 'NO_BUTTON':
                // No link for no button CTA
                break;

            case 'MOBILE_DOWNLOAD':
            case 'GET_OFFER':
                $ctaPayload['value']['link'] = $url;
                break;

            case 'GET_OFFER_VIEW':
                $ctaPayload['value']['link'] = $url;
                break;

            case 'UPDATE_APP':
                $ctaPayload['value']['link'] = $url;
                break;

            case 'BET_NOW':
                $ctaPayload['value']['link'] = $url;
                break;

            case 'ADD_TO_CART':
                $ctaPayload['value']['link'] = $url;
                break;

            case 'SELL_NOW':
                $ctaPayload['value']['link'] = $url;
                break;

            case 'GET_SHOWTIMES':
                $ctaPayload['value']['link'] = $url;
                break;

            case 'LISTEN_NOW':
            case 'LISTEN_MUSIC':
                $ctaPayload['value']['link'] = $url;
                break;

            case 'VOTE_NOW':
                $ctaPayload['value']['link'] = $url;
                break;

            case 'REGISTER_NOW':
                $ctaPayload['value']['link'] = $url;
                break;

            case 'OPEN_INSTANT_APP':
                // Handle special cases like opening instant apps
                break;

            case 'EVENT_RSVP':
                $ctaPayload['value']['link'] = $url;
                break;

            case 'CIVIC_ACTION':
                $ctaPayload['value']['link'] = $url;
                break;

            case 'SEND_INVITES':
                $ctaPayload['value']['link'] = $url;
                break;

            case 'REFER_FRIENDS':
                $ctaPayload['value']['link'] = $url;
                break;

            case 'REQUEST_TIME':
                $ctaPayload['value']['link'] = $url;
                break;

            case 'SEARCH_MORE':
                $ctaPayload['value']['link'] = $url;
                break;

            case 'TRY_IT':
            case 'TRY_ON':
                $ctaPayload['value']['link'] = $url;
                break;

            case 'LINK_CARD':
                $ctaPayload['value']['link'] = $url;
                break;

            case 'DIAL_CODE':
                $ctaPayload['value']['link'] = $url;
                break;

            case 'FIND_YOUR_GROUPS':
                $ctaPayload['value']['link'] = $url;
                break;

            case 'START_ORDER':
                $ctaPayload['value']['link'] = $url;
                break;

            default:
                // Handle unrecognized CTA types or if there's no CTA
                break;
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
                $payload['object_story_spec']['instagram_actor_id'] = $loginUser->ad_account_id;
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

            $linkData['call_to_action'] = $this->buildFacebookCTAPayload($request['call_to_action'], $request['target_link']);
            $payload['object_story_spec']['link_data'] = $linkData;
        } else if ($request['media_type'] === 'VIDEO') {

            $linkData = [
                'image_url' => $request['image_url'],
                'video_id' => $request['video_id'],
                'description' => $request['description'],
                //"link_description" => "Come check out our new store in Menlo Park!", 

            ];
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
