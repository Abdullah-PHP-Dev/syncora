<?php

namespace App\Services\AdServices;

use Illuminate\Support\Facades\Redirect;
use App\Models\Admin\AdAccount;
use App\Models\Admin\PlatformPage;
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
use Illuminate\Support\Facades\Log;
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
        // Added 'instagram_basic' permission to allow reading profile fields
        $scopes = implode(',', [
            'ads_management',
            'ads_read',
            'pages_show_list',
            'pages_read_engagement',
            'business_management',
            'instagram_basic',
        ]);

        return redirect("https://www.facebook.com/v25.0/dialog/oauth?client_id={$clientId}&redirect_uri={$this->getCallbackUrl()}&state={$this->state}&code_verifier={$this->codeVerifier}&scope={$scopes}");
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
        $endpoint = adminSetting('ads.facebook.access_token');

        $response = $this->apiService->get($endpoint, [], [
            'client_id'     => adminSetting('ads.facebook.client_id'),
            'client_secret' => adminSetting('ads.facebook.client_secret'),
            'grant_type'    => 'authorization_code',
            'redirect_uri'  => $redirectUri,
            'code'          => $code,
        ]);

        $data = $response['data'];

        if (!$response['success']) {
            return redirect()->route('admin.ads.dashboard')->with('error', data_get($data, 'error.message', 'Facebook did not return an access token.'));
        }

        $accessToken = data_get($data, 'access_token');
        $expiresIn   = data_get($data, 'expires_in', 3600);
        $expiresAt   = Carbon::now()->addSeconds($expiresIn);

        $accountResponse = $this->getFBAdAccount($accessToken);

        if (!$accountResponse['success']) {
            return redirect()->route('admin.ads.dashboard')->with('error', $accountResponse['error']);
        }

        $connected = 0;
        $pagesSaved = 0;
        $instagramSaved = '';
        $currency = '';
        foreach ($accountResponse['accounts'] as $item) {
            // 1. Extract Facebook Ad Account Data
            $fbData = $item['facebook'] ?? null;
            $localAccountId = null;

            if ($fbData) {
                $rawAccountId = $fbData['account_id'];
                $currency = $fbData['currency'];
                $fbAccountRecord = $this->apiService->success(
                    [
                        'platform'      => 'facebook',
                        'user_id'       => Auth::id(),
                        'name'          => $fbData['name'] ?? "Facebook Ad Account {$rawAccountId}",
                        'currency'      => $currency ?? null,
                        'ad_account_id' => $rawAccountId,
                        'access_token'  => $accessToken,
                        'refresh_token' => data_get($data, 'refresh_token'),
                        'expires_at'    => $expiresAt,
                        'status'        => 'active',
                    ],
                    [
                        'platform'      => 'facebook',
                        'ad_account_id' => $rawAccountId,
                        'user_id'       => Auth::id()
                    ],
                    new AdAccount
                );

                $localAccountId = $fbAccountRecord['data']['id'] ?? null;
                $connected++;
            }

            // 2. Extract and Loop Through Connected Facebook Pages - stored in
            // a platform-agnostic table so other services (Instagram, TikTok,
            // X, LinkedIn) can reuse the same "pick a page" UI later.
            foreach ($item['pages'] ?? [] as $page) {
                $this->apiService->success(
                    [
                        'platform'         => 'facebook',
                        'user_id'          => Auth::id(),
                        'ad_account_id'    => $localAccountId,
                        'page_id'          => $page['id'],
                        'name'             => $page['name'] ?? null,
                        'username'         => $page['username'] ?? null,
                        'description'      => $page['about'] ?? null,
                        'category'         => $page['category'] ?? null,
                        'link'             => $page['link'] ?? null,
                        'likes_count'      => $page['fan_count'] ?? null,
                        'followers_count'  => $page['followers_count'] ?? null,
                        'business_id'      => $fbData['business']['id'] ?? null,
                        'access_token'     => $page['access_token'] ?? null,
                        'picture'          => $page['picture']['data']['url'] ?? null,
                        'status'           => 'active',
                    ],
                    [
                        'platform' => 'facebook',
                        'page_id'  => $page['id'],
                        'user_id'  => Auth::id(),
                    ],
                    new PlatformPage
                );

                $pagesSaved++;
            }

            // 3. Extract and Loop Through Linked Instagram Accounts
            $instagrams = $item['instagrams'] ?? [];

            foreach ($instagrams as $igAccount) {
                $igId = $igAccount['id'];
                $instagramSaved = $igAccount['name'];
                $this->apiService->success(
                    [
                        'platform'      => 'instagram',
                        'user_id'       => Auth::id(),
                        'name'          => $igAccount['name'] ?? $igAccount['username'] ?? "Instagram Account {$igId}",
                        'ad_account_id' => $igId, // Store IG Actor / Profile ID in ad_account_id or profile_id
                        'access_token'  => $accessToken,
                        'refresh_token' => data_get($data, 'refresh_token'),
                        'expires_at'    => $expiresAt,
                        'status'        => 'active',
                        'currency'      => $currency ?? null,
                    ],
                    [
                        'platform'      => 'instagram', 
                        'ad_account_id' => $igId, 
                        'user_id'       => Auth::id()
                    ],
                    new AdAccount
                );
            }
        }

        $message = "Connected {$connected} Facebook ad account(s), {$pagesSaved} page(s)." . ($instagramSaved ? ' Instagram account linked.' : '');

        return redirect()->route('admin.ads.dashboard')->with('success', $message);
    }

    private function getFBAdAccount($accessToken)
    {
        $endpoint = adminSetting('ads.facebook.account.endpoint', 'https://graph.facebook.com/v22.0/me/adaccounts');

        $response = $this->httpClient::get($endpoint, [
            'fields'       => 'id,name,account_id,account_status,currency,business',
            'access_token' => $accessToken,
        ]);

        $result = $response->json();
      
        if (!$response->successful()) {
            return $this->errorResponse($result['error']['error_user_title'] ?? $result['error']['message']);
        }

        $activeAccounts = array_values(array_filter(
            $result['data'] ?? [],
            fn($account) => (int) ($account['account_status'] ?? 0) === 1 && !empty($account['business'])
        ));

        if (empty($activeAccounts)) {
            return $this->errorResponse('No active Facebook Business ad account was found for this user. Personal ad accounts are not supported.');
        }

        $accounts = array_map(function ($account) use ($accessToken) {
            $instagramAccounts = $this->getInstagramBusinessAccount($accessToken, $account['id']);
            $pages = $this->getBusinessPages($accessToken, $account['business']['id']);

            return [
                'facebook'   => $account,
                'instagrams'  => $instagramAccounts ?? null,
                'pages'      => $pages,
            ];
        }, $activeAccounts);

        return ['success' => true, 'accounts' => $accounts];
    }

    /**
     * Instagram accounts eligible for use as object_story_spec.instagram_actor_id
     * on a given ad account - NOT the same as "every Instagram account the
     * Business Manager owns" (that's /{business_id}?fields=instagram_accounts,
     * which used to be queried here and caused Meta to reject creative
     * creation with "(#100) Param instagram_actor_id must be a valid
     * Instagram account id": an Instagram account can belong to the business
     * without being assigned to this specific ad account under Business
     * Settings > Ad Account > Instagram Accounts, and only assigned accounts
     * are valid here). $adAccountId must include the "act_" prefix.
     */
    private function getInstagramBusinessAccount($accessToken, string $adAccountId): ?array
    {
        $response = $this->httpClient::get("https://graph.facebook.com/v22.0/{$adAccountId}/instagram_accounts", [
            'fields'       => 'id,username,name,profile_pic',
            'access_token' => $accessToken,
        ]);

        if (!$response->successful()) {
            Log::warning('Facebook Instagram lookup: ad account instagram_accounts request failed', [
                'ad_account_id' => $adAccountId,
                'response'      => $response->json(),
            ]);

            return [];
        }

        return $response->json()['data'] ?? [];
    }

    /**
     * Facebook Pages belonging to a Business Manager - both formally "owned"
     * pages (added under Business Settings > Accounts > Pages) and "client"
     * pages (shared with this business by another, e.g. an agency setup).
     * Fetched off the Business node itself, same pattern that worked for
     * instagram_accounts, rather than /me/accounts (which only lists Pages
     * the token's user personally administers, not the business as a whole).
     */
    private function getBusinessPages($accessToken, string $businessId): array
    {
        $response = $this->httpClient::get("https://graph.facebook.com/v22.0/{$businessId}", [
            'fields'       => 'owned_pages{id,name,username,about,category,link,fan_count,followers_count,access_token,picture{url}},client_pages{id,name,username,about,category,link,fan_count,followers_count,access_token,picture{url}}',
            'access_token' => $accessToken,
        ]);

        if (!$response->successful()) {
            Log::warning('Facebook Pages lookup failed', [
                'business_id' => $businessId,
                'response'    => $response->json(),
            ]);

            return [];
        }

        $result = $response->json();

        return array_merge(
            $result['owned_pages']['data'] ?? [],
            $result['client_pages']['data'] ?? []
        );
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

        // Special Ad Category is set once here and is immutable for the
        // life of the campaign (Meta rejects changing it later) - see
        // storeAdGroup()'s docblock for the targeting restrictions it
        // triggers. Was previously hardcoded to [] (NONE) unconditionally.
        $specialAdCategory = $request['special_ad_category'] ?? null;

        $payload = [
            'name'                => $request['name'],
            'status'              => 'PAUSED',
            'start_time'          => Carbon::parse($request['start_time'])->utc()->format('Y-m-d\TH:i:s.v\Z'),
            'stop_time'           => Carbon::parse($request['end_time'])->utc()->format('Y-m-d\TH:i:s.v\Z'),
            'creation_state'      => 'PUBLISHED',
            'special_ad_categories' => $specialAdCategory ? [$specialAdCategory] : [],
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
            'special_ad_category' => $specialAdCategory,
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
        $ageFrom = $request['age_from'];
        $ageTo = $request['age_to'];
        $locales = $this->getLocale($request['languages']);
        // $locales = collect($request['langauges'] ?? [])->map(fn($lang) => $localeMap[$lang] ?? null)->filter()->values()->toArray();

        // Special Ad Category (housing/employment/credit/social-issues ads)
        // legally forbids narrowing by age or gender - Meta rejects the
        // ad set outright otherwise. Silently forcing the broadest
        // allowed range here (rather than surfacing a 400 from Meta) so a
        // category chosen after the Audience step was already filled in
        // doesn't produce a confusing rejected submission.
        $specialAdCategory = $request['special_ad_category'] ?? null;

        if ($specialAdCategory) {
            $genders = [1, 2];
            $ageFrom = 18;
            $ageTo = 65;
        }

        $detailedTargeting = $this->buildDetailedTargeting($request);

        if ($specialAdCategory) {
            // Meta also restricts which Detailed Targeting entities
            // remain eligible under a Special Ad Category (server-side,
            // via its own "Special Ad Audience" allow-list) rather than
            // rejecting all of them outright - but which subset is safe
            // isn't something this app can determine client-side, so the
            // safer default is to drop detailed targeting entirely for
            // these campaigns rather than risk sending an entity Meta
            // will reject.
            $detailedTargeting['flexible_spec'] = [];
        }

        $devicePlatforms = !empty($request['device_platforms']) ? $request['device_platforms'] : ['mobile', 'desktop'];

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
                    $ageFrom,
                    $ageTo
                ],
                "device_platforms" => $devicePlatforms,
                "targeting_automation" => [
                    "advantage_audience" => 1,
                    "individual_setting" => [
                        "age" => 1,
                        "gender" => 1
                    ]
                ],
                'publisher_platforms' => $publisherPlatforms,
                'flexible_spec' => $detailedTargeting['flexible_spec'],
                'custom_audiences' => $detailedTargeting['custom_audiences'],
                'excluded_custom_audiences' => $detailedTargeting['excluded_custom_audiences'],
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
                'age_from' => $ageFrom,
                'age_to' => $ageTo,
            ]),
            'publisher_platforms' => json_encode($publisherPlatforms),
            // Resolved detailed-targeting payload (for reference) plus
            // every raw local selection needed to re-populate the edit
            // form - same "one JSON bag" shape LinkedinAdService uses for
            // its own targeting_criteria column.
            'targeting_criteria' => json_encode([
                'flexible_spec' => $detailedTargeting['flexible_spec'],
                'custom_audiences' => $detailedTargeting['custom_audiences'],
                'excluded_custom_audiences' => $detailedTargeting['excluded_custom_audiences'],
                'device_platforms' => $devicePlatforms,
                'local' => $detailedTargeting['local'],
            ]),
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

    /**
     * Splits a comma-separated free-text field (Detailed Targeting/Life
     * Events/Industries/Income/Custom Audience ID inputs) into trimmed,
     * non-empty values.
     */
    private function splitCsv(?string $value): array
    {
        if (empty($value)) {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $value))));
    }

    /**
     * Resolves free-text queries to Meta targeting entity {id,name} pairs
     * via the Ad Account's Targeting Search endpoint (GET /act_{id}/
     * targetingsearch?q=...&limit_type=...) - used for every detailed-
     * targeting facet without a small static enum worth hardcoding
     * (interests, behaviors, life_events, industries, income). One
     * request per query since the endpoint only searches a single string
     * at a time; each is independently best-effort so one unmatched query
     * doesn't drop the rest.
     */
    private function resolveDetailedTargeting(string $limitType, array $queries): array
    {
        $endpoint = str_replace('{accountId}', $this->account->ad_account_id, $this->config) . '/targetingsearch';
        $results = [];

        foreach ($queries as $query) {
            try {
                $response = $this->apiService->get($endpoint, $this->header['data'], [
                    'q'          => $query,
                    'limit_type' => $limitType,
                    'limit'      => 1,
                ]);

                if (!$response['success'] || empty($response['data']['data'][0]['id'])) {
                    Log::warning('Facebook targeting search found no match.', ['limit_type' => $limitType, 'query' => $query, 'response' => $response['data'] ?? null]);
                    continue;
                }

                $results[] = [
                    'id'   => $response['data']['data'][0]['id'],
                    'name' => $response['data']['data'][0]['name'] ?? $query,
                ];
            } catch (\Throwable $e) {
                Log::warning('Facebook targeting search threw.', ['limit_type' => $limitType, 'query' => $query, 'error' => $e->getMessage()]);
            }
        }

        return $results;
    }

    /**
     * Builds the ad set's flexible_spec / custom_audiences / excluded_
     * custom_audiences from the form's Detailed Targeting inputs, verified
     * against Meta's "Detailed Targeting" and "Flexible Targeting" docs.
     * Every facet resolved here (interests, behaviors, life_events,
     * industries, income, plus the fixed enums relationship_statuses/
     * education_statuses) is combined into a single flexible_spec entry -
     * multiple keys within the same entry are OR'd together per Meta's
     * documented flexible_spec semantics, matching the default "reaches
     * anyone matching any of these" behavior Ads Manager itself uses
     * unless an advertiser explicitly narrows with a second AND'd group
     * (not exposed here - see class docblock for the practical-subset
     * scope this covers).
     *
     * Custom Audiences are targeted by ID, pasted in from Ads Manager
     * rather than resolved via a live lookup - same "no live API call at
     * page-render time" pattern XAdService's funding_instrument_id
     * already uses - since they're the advertiser's own audiences, not a
     * public taxonomy a typeahead search would help with.
     *
     * Interest/behavior *exclusions* are deliberately not supported here:
     * Meta permanently removed Detailed Targeting Exclusions on March 31,
     * 2025 - only excluded_custom_audiences remains a valid way to
     * suppress an audience.
     */
    private function buildDetailedTargeting(array $request): array
    {
        $spec = [];
        $local = [];

        $interests = [];
        $behaviors = [];

        foreach ($this->splitCsv($request['detailed_targeting'] ?? '') as $query) {
            $interestMatch = $this->resolveDetailedTargeting('interests', [$query]);

            if (!empty($interestMatch)) {
                $interests = array_merge($interests, $interestMatch);
                continue;
            }

            $behaviorMatch = $this->resolveDetailedTargeting('behaviors', [$query]);

            if (!empty($behaviorMatch)) {
                $behaviors = array_merge($behaviors, $behaviorMatch);
            }
        }

        if (!empty($interests)) {
            $spec['interests'] = $interests;
        }

        if (!empty($behaviors)) {
            $spec['behaviors'] = $behaviors;
        }

        $local['detailed_targeting'] = $request['detailed_targeting'] ?? '';

        $lifeEvents = $this->resolveDetailedTargeting('life_events', $this->splitCsv($request['life_events'] ?? ''));

        if (!empty($lifeEvents)) {
            $spec['life_events'] = $lifeEvents;
        }

        $local['life_events'] = $request['life_events'] ?? '';

        $industries = $this->resolveDetailedTargeting('industries', $this->splitCsv($request['industries'] ?? ''));

        if (!empty($industries)) {
            $spec['industries'] = $industries;
        }

        $local['industries'] = $request['industries'] ?? '';

        $income = $this->resolveDetailedTargeting('income', $this->splitCsv($request['income'] ?? ''));

        if (!empty($income)) {
            $spec['income'] = $income;
        }

        $local['income'] = $request['income'] ?? '';

        // Relationship status - fixed numeric codes per Meta's documented
        // enum (5 is not assigned to any status and is deliberately absent).
        $relationshipMap = [
            'single' => 1, 'in_relationship' => 2, 'married' => 3, 'engaged' => 4,
            'its_complicated' => 6, 'open_relationship' => 7, 'widowed' => 8,
            'separated' => 9, 'divorced' => 10, 'civil_union' => 11, 'domestic_partnership' => 12,
        ];
        $relationshipStatuses = array_values(array_filter(array_map(
            fn($r) => $relationshipMap[$r] ?? null,
            $request['relationship_statuses'] ?? []
        )));

        if (!empty($relationshipStatuses)) {
            $spec['relationship_statuses'] = $relationshipStatuses;
        }

        $local['relationship_statuses'] = $request['relationship_statuses'] ?? [];

        // Education status - fixed enum per Meta's documented values.
        $educationStatuses = array_values(array_intersect($request['education_statuses'] ?? [], [
            'HIGH_SCHOOL', 'UNDERGRAD', 'ALUM', 'HIGH_SCHOOL_GRAD', 'SOME_COLLEGE',
            'ASSOCIATE_DEGREE', 'IN_GRAD_SCHOOL', 'MASTER_DEGREE', 'PROFESSIONAL_DEGREE',
            'DOCTORATE_DEGREE', 'UNSPECIFIED',
        ]));

        if (!empty($educationStatuses)) {
            $spec['education_statuses'] = $educationStatuses;
        }

        $local['education_statuses'] = $request['education_statuses'] ?? [];

        $customAudiences = array_map(fn($id) => ['id' => $id], $this->splitCsv($request['custom_audiences'] ?? ''));
        $excludedCustomAudiences = array_map(fn($id) => ['id' => $id], $this->splitCsv($request['excluded_custom_audiences'] ?? ''));
        $local['custom_audiences'] = $request['custom_audiences'] ?? '';
        $local['excluded_custom_audiences'] = $request['excluded_custom_audiences'] ?? '';

        return [
            'flexible_spec'              => !empty($spec) ? [$spec] : [],
            'custom_audiences'           => $customAudiences,
            'excluded_custom_audiences'  => $excludedCustomAudiences,
            'local'                      => $local,
        ];
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

    public function update($platform, $id, $request)
    {
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
        $ageFrom = $request['age_from'];
        $ageTo = $request['age_to'];
        $locales = $this->getLocale($request['languages']);
        // $locales = collect($request['langauges'] ?? [])->map(fn($lang) => $localeMap[$lang] ?? null)->filter()->values()->toArray();

        // Special Ad Category is immutable once the campaign is created
        // (Meta rejects changing it) and the edit form doesn't resubmit
        // it, so it's read from the campaign record itself rather than
        // $request - see storeAdGroup()'s docblock for why this forces
        // the broadest age/gender range.
        $specialAdCategory = AdCampaign::find($campaignId)?->special_ad_category;

        if ($specialAdCategory) {
            $genders = [1, 2];
            $ageFrom = 18;
            $ageTo = 65;
        }

        $detailedTargeting = $this->buildDetailedTargeting($request);

        if ($specialAdCategory) {
            $detailedTargeting['flexible_spec'] = [];
        }

        $devicePlatforms = !empty($request['device_platforms']) ? $request['device_platforms'] : ['mobile', 'desktop'];

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
                    $ageFrom,
                    $ageTo
                ],
                "device_platforms" => $devicePlatforms,
                "targeting_automation" => [
                    "advantage_audience" => 1,
                    "individual_setting" => [
                        "age" => 1,
                        "gender" => 1
                    ]
                ],
                'publisher_platforms' => $publisherPlatforms,
                'flexible_spec' => $detailedTargeting['flexible_spec'],
                'custom_audiences' => $detailedTargeting['custom_audiences'],
                'excluded_custom_audiences' => $detailedTargeting['excluded_custom_audiences'],
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
                'age_from' => $ageFrom,
                'age_to' => $ageTo,
            ]),
            'publisher_platforms' => json_encode($publisherPlatforms),
            'targeting_criteria' => json_encode([
                'flexible_spec' => $detailedTargeting['flexible_spec'],
                'custom_audiences' => $detailedTargeting['custom_audiences'],
                'excluded_custom_audiences' => $detailedTargeting['excluded_custom_audiences'],
                'device_platforms' => $devicePlatforms,
                'local' => $detailedTargeting['local'],
            ]),
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
