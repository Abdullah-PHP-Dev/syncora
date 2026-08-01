<?php

namespace App\Services\AdServices;

use App\Models\Admin\AdAccount;
use App\Models\Admin\AdCampaign;
use App\Models\Admin\AdAdGroup;
use App\Models\Admin\AdCreative;
use App\Models\Admin\AdMedia;
use App\Models\Admin\Ad;
use App\Models\Country;
use App\Services\ApiService;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Carbon\Carbon;

/**
 * X (Twitter) Ads API integration - Promoted Tweets, base
 * https://ads-api.x.com/12/.
 *
 * Architecturally different from every other platform in this app: X's Ads
 * API only accepts OAuth 1.0a user-context signing (a computed HMAC-SHA1
 * `Authorization: OAuth ...` header on *every* request, not a reusable
 * Bearer token) - OAuth 2.0 Bearer tokens are documented as unsupported for
 * Ads API writes. That's why `ad_accounts.token_secret` already existed as
 * a column before this module was written - it's the OAuth 1.0a access
 * token secret, alongside `access_token` for the token itself and
 * `client_id`/`client_secret` for the consumer key/secret.
 *
 * Hierarchy: Campaign (budget, funding_instrument_id) -> Line Item (the
 * ad-group equivalent: objective, placements, bid) -> a nullcast
 * ("promoted-only") Tweet -> a Promoted Tweet record linking the two.
 * Endpoints/fields verified via developers.x.com/docs.x.com this session
 * (campaign-management reference, targeting-options, chunked media
 * upload); see inline notes for the couple of fields intentionally left
 * out because their exact enum values couldn't be pinned down from current
 * docs (AGE targeting buckets, per-objective `charge_by`).
 *
 * IMPORTANT: no developer/test X Ads account was available to exercise
 * this against, same caveat as the Google/YouTube module - verify against
 * a live account before relying on it for real spend. redirect() has been
 * corrected (real oauth_* param names/endpoints; the original stub sent
 * literal "AdService_*" params, which X's request_token endpoint would
 * reject) but callback() - the token exchange leg - is left unimplemented,
 * matching the same scope boundary already drawn for Google/YouTube this
 * session (this module is campaign CRUD, not the account-linking flow).
 */
class XAdService
{
    protected $account, $config, $uploadUrl, $apiService;

    public function __construct(AdAccount $account, ApiService $apiService)
    {
        $this->apiService = $apiService;
        $this->account = $account->wherePlatform('x')->whereUserId(Auth::user()->id)->first();
        $this->config = adminSetting('ads.x.base_url');
        $this->uploadUrl = adminSetting('ads.x.upload_url');
    }

    public function redirect($platform, $state)
    {
        $clientId = adminSetting('ads.x.client_id');
        $clientSecret = adminSetting('ads.x.client_secret');
        $requestTokenUrl = adminSetting('ads.x.request_token_url');

        $params = [
            'oauth_callback'         => $this->getCallbackUrl(),
            'oauth_consumer_key'     => $clientId,
            'oauth_nonce'            => Str::random(32),
            'oauth_signature_method' => 'HMAC-SHA1',
            'oauth_timestamp'        => time(),
            'oauth_version'          => '1.0',
        ];

        $params['oauth_signature'] = $this->signature('POST', $requestTokenUrl, $params, $clientSecret, '');

        $authHeader = 'OAuth ' . collect($params)->map(fn($v, $k) => rawurlencode($k) . '="' . rawurlencode($v) . '"')->implode(', ');

        $response = $this->apiService->post($requestTokenUrl, ['Authorization' => $authHeader]);

        if (!$response['success']) {
            return $this->errorResponse($response['body'] ?? 'Failed to get X request token.');
        }

        parse_str($response['body'], $tokens);

        if (!isset($tokens['oauth_token'])) {
            return $this->errorResponse('Missing oauth_token in X request_token response.');
        }

        session(['x_oauth_token_secret' => $tokens['oauth_token_secret'], 'x_state' => $state]);

        return Redirect::away(adminSetting('ads.x.authorize_url') . '?oauth_token=' . $tokens['oauth_token']);
    }

    private function getCallbackUrl()
    {
        return config('services.app_url') . '/admin/social/auth/x/callback';
    }

    // ------------------------------------------------------------------
    // OAuth 1.0a request signing (RFC 5849) - every Ads API and media
    // upload call needs a freshly computed signature; there's no reusable
    // Bearer token the way every other platform in this app works.
    // ------------------------------------------------------------------

    private function signature(string $method, string $url, array $params, string $consumerSecret, string $tokenSecret): string
    {
        $encoded = [];

        foreach ($params as $key => $value) {
            $encoded[rawurlencode($key)] = rawurlencode($value);
        }

        ksort($encoded);

        $paramString = collect($encoded)->map(fn($v, $k) => "$k=$v")->implode('&');
        $baseString = strtoupper($method) . '&' . rawurlencode($url) . '&' . rawurlencode($paramString);
        $signingKey = rawurlencode($consumerSecret) . '&' . rawurlencode($tokenSecret);

        return base64_encode(hash_hmac('sha1', $baseString, $signingKey, true));
    }

    private function oauthHeader(string $method, string $url, array $params = []): string
    {
        $oauthParams = [
            'oauth_consumer_key'     => adminSetting('ads.x.client_id'),
            'oauth_nonce'            => Str::random(32),
            'oauth_signature_method' => 'HMAC-SHA1',
            'oauth_timestamp'        => time(),
            'oauth_token'            => $this->account->access_token,
            'oauth_version'          => '1.0',
        ];

        $allParams = array_merge($params, $oauthParams);
        $oauthParams['oauth_signature'] = $this->signature(
            $method,
            $url,
            $allParams,
            adminSetting('ads.x.client_secret'),
            $this->account->token_secret ?? ''
        );

        return 'OAuth ' . collect($oauthParams)->map(fn($v, $k) => rawurlencode($k) . '="' . rawurlencode($v) . '"')->implode(', ');
    }

    /**
     * All Ads API parameters are sent as a signed query string (matching
     * X's own documented example - `POST .../promoted_tweets?line_item_id=
     * X&tweet_ids=Y` with no request body) rather than a JSON/form body,
     * which sidesteps OAuth 1.0a's ambiguity around signing non-form
     * request bodies.
     */
    private function call(string $method, string $endpoint, array $params = [])
    {
        $authHeader = $this->oauthHeader($method, $endpoint, $params);
        $url = $endpoint . (!empty($params) ? '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986) : '');
        $headers = ['Authorization' => $authHeader];

        $response = match (strtoupper($method)) {
            'GET'    => $this->apiService->get($url, $headers),
            'POST'   => $this->apiService->post($url, $headers, []),
            'PUT'    => $this->apiService->put($url, $headers, []),
            'DELETE' => $this->apiService->delete($url, $headers),
            default  => ['success' => false, 'data' => null],
        };

        if (!$response['success']) {
            return $this->errorResponse($response['data']['errors'][0]['message'] ?? $response['body'] ?? 'X Ads API request failed.');
        }

        return $this->successResponse($response['data']['data'] ?? $response['data'] ?? []);
    }

    private function accountId(): string
    {
        return $this->account->ad_account_id;
    }

    // ------------------------------------------------------------------
    // STORE
    // ------------------------------------------------------------------

    public function store($platform, $request)
    {
        $response = $this->storeCampaign($platform, $request);

        if (!$response['success']) {
            return $response;
        }

        $request['campaign_id'] = $response['data']['campaign_id'];
        $request['ad_campaign_id'] = $response['data']['id'];

        $response = $this->storeLineItem($platform, $request);

        if (!$response['success']) {
            return $response;
        }

        $request['line_item_id'] = $response['data']['line_item_id'];
        $request['ad_adgroup_id'] = $response['data']['id'];

        $response = $this->storeTargeting($request);

        if (!$response['success']) {
            return $response;
        }

        $mediaKey = null;

        if (!empty($request['media'])) {
            $response = $this->storeMedia($platform, $request);

            if (!$response['success']) {
                return $response;
            }

            $mediaKey = $response['data'];
        }

        $response = $this->storeTweet($platform, $request, $mediaKey);

        if (!$response['success']) {
            return $response;
        }

        $request['tweet_id'] = $response['data']['tweet_id'];
        $request['ad_creative_id'] = $response['data']['id'];

        return $this->storePromotedTweet($platform, $request);
    }

    private function storeCampaign($platform, $request)
    {
        $params = [
            'name'                  => $request['name'],
            'funding_instrument_id' => $request['funding_instrument_id'],
            'entity_status'         => 'PAUSED',
            'start_time'            => Carbon::parse($request['start_time'])->toIso8601String(),
            'end_time'              => Carbon::parse($request['end_time'])->toIso8601String(),
        ];

        if ($request['budget_mode'] === 'daily') {
            $params['daily_budget_amount_local_micro'] = (int) ((float) $request['budget'] * 1000000);
        } else {
            $params['total_budget_amount_local_micro'] = (int) ((float) $request['budget'] * 1000000);
            $params['standard_delivery'] = 'true';
        }

        $result = $this->call('POST', $this->config . 'accounts/' . $this->accountId() . '/campaigns', $params);

        if (!$result['success']) {
            return $result;
        }

        $campaignId = $result['data']['id'];

        $dataToInsert = [
            'ad_campaign_id'        => $campaignId,
            'user_id'               => Auth::id(),
            'ad_account_id'         => $this->account->id,
            'name'                  => $request['name'],
            'platform'              => $platform,
            'funding_instrument_id' => $request['funding_instrument_id'],
            'budget_mode'           => $request['budget_mode'],
            'budget'                => $request['budget'],
            'start_time'            => $request['start_time'],
            'end_time'              => $request['end_time'],
            'status'                => false,
        ];

        $campaignRecord = $this->apiService->success($dataToInsert, ['ad_campaign_id' => $campaignId], new AdCampaign);

        return $this->successResponse(['campaign_id' => $campaignId, 'id' => $campaignRecord['data']->id]);
    }

    private function storeLineItem($platform, $request)
    {
        $params = [
            'campaign_id'   => $request['campaign_id'],
            'name'          => $request['name'] . ' Line Item',
            'objective'     => $request['objective'],
            'product_type'  => 'PROMOTED_TWEETS',
            'placements'    => implode(',', $request['placements'] ?? ['ALL_ON_TWITTER']),
            'bid_type'      => $request['bid_type'],
            'entity_status' => 'ACTIVE',
        ];

        // charge_by is intentionally omitted - X derives a sensible default
        // billing event from the objective when it isn't supplied, and the
        // exact per-objective charge_by mapping isn't confirmed against
        // current docs, so guessing a value here risks silently breaking
        // line item creation outright rather than just being suboptimal.
        if ($request['bid_type'] !== 'AUTO' && !empty($request['bid_amount'])) {
            $params['bid_amount_local_micro'] = (int) ((float) $request['bid_amount'] * 1000000);
        }

        $result = $this->call('POST', $this->config . 'accounts/' . $this->accountId() . '/line_items', $params);

        if (!$result['success']) {
            return $result;
        }

        $lineItemId = $result['data']['id'];

        $dataToInsert = [
            'ad_campaign_id' => $request['ad_campaign_id'],
            'user_id'        => Auth::id(),
            'ad_adgroup_id'  => $lineItemId,
            'ad_account_id'  => $this->account->id,
            'platform'       => $platform,
            'name'           => $params['name'],
            'objective'      => $request['objective'],
            'placements'     => json_encode($request['placements'] ?? ['ALL_ON_TWITTER']),
            'placement_type' => 'PROMOTED_TWEETS',
            'bid_type'       => $request['bid_type'],
            'bid_price'      => $request['bid_amount'] ?? null,
            'gender'         => $request['gender'] ?? null,
            'languages'      => json_encode($request['languages'] ?? []),
            'location_ids'   => json_encode($request['countries'] ?? []),
            'status'         => true,
        ];

        $lineItemRecord = $this->apiService->success($dataToInsert, ['ad_adgroup_id' => $lineItemId], new AdAdGroup);

        return $this->successResponse(['line_item_id' => $lineItemId, 'id' => $lineItemRecord['data']->id]);
    }

    private function storeTargeting($request)
    {
        $operations = [];

        $locationValues = $this->resolveLocations($request['countries'] ?? []);

        if (empty($locationValues)) {
            return $this->errorResponse('Could not resolve the selected countries to X location targeting values. Please double-check the Countries selection.');
        }

        foreach ($locationValues as $locationValue) {
            $operations[] = ['targeting_type' => 'LOCATION', 'targeting_value' => $locationValue];
        }

        foreach ($request['languages'] ?? [] as $languageCode) {
            $operations[] = ['targeting_type' => 'LANGUAGE', 'targeting_value' => $languageCode];
        }

        if (!empty($request['gender']) && $request['gender'] !== 'both') {
            $operations[] = ['targeting_type' => 'GENDER', 'targeting_value' => strtolower($request['gender']) === 'male' ? '1' : '2'];
        }

        // Age targeting is intentionally left out - X's overlapping age
        // bucket set (18-24, 18-34, 18-49... rather than a clean partition)
        // couldn't be pinned down to exact API-literal enum strings from
        // current docs, and a wrong guess here would silently either fail
        // the request or target the wrong audience rather than just being
        // suboptimal, so it's safer to omit than fabricate.

        foreach ($operations as $op) {
            $result = $this->call('POST', $this->config . 'accounts/' . $this->accountId() . '/targeting_criteria', array_merge($op, [
                'line_item_id' => $request['line_item_id'],
            ]));

            if (!$result['success']) {
                return $result;
            }
        }

        return $this->successResponse(null);
    }

    /**
     * X's location targeting_value is an opaque ID string returned by its
     * own locations lookup endpoint (not a plain country code), so - same
     * problem as Google's geo_target_constant and TikTok's location_ids -
     * it's resolved dynamically per selected country rather than a
     * hardcoded table that would silently go stale.
     */
    private function resolveLocations(array $countryIds): array
    {
        $countryNames = Country::whereIn('id', $countryIds)->pluck('name')->toArray();
        $values = [];

        foreach ($countryNames as $name) {
            $result = $this->call('GET', $this->config . 'targeting_criteria/locations', [
                'location_type' => 'COUNTRIES',
                'q'              => $name,
            ]);

            if ($result['success'] && !empty($result['data'][0]['targeting_value'])) {
                $values[] = $result['data'][0]['targeting_value'];
            }
        }

        return $values;
    }

    /**
     * Single-shot upload (INIT -> one APPEND -> FINALIZE, no multi-chunk
     * splitting) - mirrors how SnapchatAdService handles media in this app
     * despite Snapchat's own API also technically supporting resumable
     * chunked uploads. Returns the media_id_string for use on the Tweet.
     */
    private function storeMedia($platform, $request)
    {
        $media = $request['media'][0];
        $extension = strtolower($media->getClientOriginalExtension());
        $isVideo = in_array($extension, ['mp4', 'mov']);
        $mediaType = $isVideo ? 'video/mp4' : ($extension === 'gif' ? 'image/gif' : 'image/jpeg');
        $mediaCategory = $isVideo ? 'TWEET_VIDEO' : ($extension === 'gif' ? 'TWEET_GIF' : 'TWEET_IMAGE');
        $totalBytes = $media->getSize();

        $fileName = time() . '_' . uniqid() . '.' . $extension;
        $s3Path = "uploads/{$platform}/media/{$fileName}";
        Storage::disk('s3')->put($s3Path, file_get_contents($media->getRealPath()), ['visibility' => 'public']);
        $fileUrl = Storage::disk('s3')->url($s3Path);

        $initResult = $this->call('POST', $this->uploadUrl, [
            'command'        => 'INIT',
            'media_type'     => $mediaType,
            'total_bytes'    => $totalBytes,
            'media_category' => $mediaCategory,
        ]);

        if (!$initResult['success']) {
            return $initResult;
        }

        $mediaId = $initResult['data']['media_id_string'] ?? $initResult['data']['media_id'] ?? null;

        if (!$mediaId) {
            return $this->errorResponse('X media INIT did not return a media_id.');
        }

        $appendParams = ['command' => 'APPEND', 'media_id' => $mediaId, 'segment_index' => 0];
        $authHeader = $this->oauthHeader('POST', $this->uploadUrl, $appendParams);
        $appendUrl = $this->uploadUrl . '?' . http_build_query($appendParams, '', '&', PHP_QUERY_RFC3986);

        $appendResponse = $this->apiService->post(
            $appendUrl,
            ['Authorization' => $authHeader],
            [],
            'multipart',
            [[
                'name'       => 'media',
                'file_name'  => $fileName,
                'media_file' => $media->getRealPath(),
            ]]
        );

        if (!$appendResponse['success']) {
            return $this->errorResponse('Failed to upload media binary to X.');
        }

        $finalizeResult = $this->call('POST', $this->uploadUrl, ['command' => 'FINALIZE', 'media_id' => $mediaId]);

        if (!$finalizeResult['success']) {
            return $finalizeResult;
        }

        if (isset($finalizeResult['data']['processing_info'])) {
            for ($i = 0; $i < 5; $i++) {
                $statusResult = $this->call('GET', $this->uploadUrl, ['command' => 'STATUS', 'media_id' => $mediaId]);
                $state = $statusResult['data']['processing_info']['state'] ?? null;

                if ($state === 'succeeded') {
                    break;
                }

                if ($state === 'failed') {
                    return $this->errorResponse('X media processing failed.');
                }

                sleep($statusResult['data']['processing_info']['check_after_secs'] ?? 2);
            }
        }

        $this->apiService->success(
            [
                'user_id'        => Auth::id(),
                'platform'       => $platform,
                'ad_account_id'  => $this->account->id,
                'ad_campaign_id' => $request['ad_campaign_id'],
                'name'           => $fileName,
                'file_name'      => $fileName,
                'type'           => $isVideo ? 'VIDEO' : 'IMAGE',
                'url'            => $fileUrl,
                'file_id'        => $mediaId,
            ],
            [],
            new AdMedia
        );

        return $this->successResponse($mediaId);
    }

    /**
     * Creates a nullcast ("Promoted-Only") Tweet - not published to the
     * account's organic timeline, only usable as ad creative - rather than
     * building out X's separate Website Card resource for link previews;
     * the target URL is appended to the Tweet text and left to X's own
     * automatic link unfurling, which is simpler and doesn't require
     * guessing an unverified Cards endpoint shape.
     */
    private function storeTweet($platform, $request, $mediaKey = null)
    {
        if (empty($this->account->profile_id)) {
            return $this->errorResponse('This X account is missing its numeric user ID (profile_id) - required to create a promoted Tweet. Reconnect the account.');
        }

        $text = $request['message'];

        if (!empty($request['target_link']) && !str_contains($text, $request['target_link'])) {
            $text .= ' ' . $request['target_link'];
        }

        $params = [
            'text'       => $text,
            'nullcast'   => 'true',
            'as_user_id' => $this->account->profile_id,
        ];

        if ($mediaKey) {
            $params['media_ids'] = $mediaKey;
        }

        $result = $this->call('POST', $this->config . 'accounts/' . $this->accountId() . '/tweet', $params);

        if (!$result['success']) {
            return $result;
        }

        $tweetId = $result['data']['id'];

        $creativeRecord = $this->apiService->success(
            [
                'user_id'        => Auth::id(),
                'ad_adgroup_id'  => $request['ad_adgroup_id'],
                'ad_creative_id' => $tweetId,
                'platform'       => $platform,
                'ad_account_id'  => $this->account->id,
                'ad_campaign_id' => $request['ad_campaign_id'],
                'name'           => $request['name'],
                'type'           => 'PROMOTED_TWEET',
                'message'        => $request['message'],
                'url'            => $request['target_link'] ?? null,
            ],
            ['ad_creative_id' => $tweetId],
            new AdCreative
        );

        return $this->successResponse(['tweet_id' => $tweetId, 'id' => $creativeRecord['data']->id]);
    }

    private function storePromotedTweet($platform, $request)
    {
        $result = $this->call('POST', $this->config . 'accounts/' . $this->accountId() . '/promoted_tweets', [
            'line_item_id' => $request['line_item_id'],
            'tweet_ids'    => $request['tweet_id'],
        ]);

        if (!$result['success']) {
            return $result;
        }

        $promotedTweetId = $result['data']['id'];

        return $this->apiService->success(
            [
                'user_id'        => Auth::id(),
                'ad_adgroup_id'  => $request['ad_adgroup_id'],
                'ad_creative_id' => $request['ad_creative_id'],
                'ad_id'          => $promotedTweetId,
                'status'         => false,
                'platform'       => $platform,
                'ad_account_id'  => $this->account->id,
                'ad_campaign_id' => $request['ad_campaign_id'],
                'name'           => $request['name'],
                'type'           => 'PROMOTED_TWEET',
                'text'           => $request['message'],
            ],
            ['ad_id' => $promotedTweetId],
            new Ad
        );
    }

    // ------------------------------------------------------------------
    // UPDATE / STATUS / DESTROY
    // ------------------------------------------------------------------

    /**
     * Tweets are immutable on X - once published (even as nullcast) the
     * text/media can't be edited via the API - so unlike the other
     * platforms' edit forms, only the campaign's name/dates are updatable
     * here. Line item targeting/bid and the Tweet itself are locked in at
     * creation.
     */
    public function update($platform, $id, $request)
    {
        $campaign = AdCampaign::findOrFail($id);

        $params = [
            'name'       => $request['name'],
            'start_time' => Carbon::parse($request['start_time'])->toIso8601String(),
            'end_time'   => Carbon::parse($request['end_time'])->toIso8601String(),
        ];

        $result = $this->call('PUT', $this->config . 'accounts/' . $this->accountId() . '/campaigns/' . $campaign->ad_campaign_id, $params);

        if (!$result['success']) {
            return $result;
        }

        $campaign->update([
            'name'       => $request['name'],
            'start_time' => $request['start_time'],
            'end_time'   => $request['end_time'],
        ]);

        return $this->successResponse(['ad_campaign_id' => $campaign->id]);
    }

    public function updateStatus($id, $status)
    {
        $campaign = AdCampaign::findOrFail($id);

        $result = $this->call('PUT', $this->config . 'accounts/' . $this->accountId() . '/campaigns/' . $campaign->ad_campaign_id, [
            'entity_status' => $status === 'ACTIVE' ? 'ACTIVE' : 'PAUSED',
        ]);

        if (!$result['success']) {
            return $result;
        }

        $campaign->update(['status' => $status === 'ACTIVE']);

        return $this->successResponse(['status' => $status]);
    }

    public function destroy($platform, $id)
    {
        $campaign = AdCampaign::findOrFail($id);

        $result = $this->call('PUT', $this->config . 'accounts/' . $this->accountId() . '/campaigns/' . $campaign->ad_campaign_id, [
            'entity_status' => 'DELETED',
        ]);

        if (!$result['success']) {
            return $result;
        }

        AdAdGroup::whereAdCampaignId($id)->delete();
        AdCreative::whereAdCampaignId($id)->delete();
        Ad::whereAdCampaignId($id)->delete();
        $campaign->delete();

        return $this->successResponse(null);
    }

    private function errorResponse($error)
    {
        return ['success' => false, 'error' => $error];
    }

    private function successResponse($data)
    {
        return ['success' => true, 'data' => $data];
    }
}
