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

    /**
     * oauth2/access_token/'s field names (auth_code/app_id/secret in, and
     * access_token/advertiser_ids/scope in the data envelope) verified
     * against TikTok's own business-api-sdk AuthenticationApi reference,
     * same standard applied to the rest of this class - "code" (the name
     * most other OAuth providers use) is checked as a fallback only in
     * case TikTok ever changes the callback query param, not because it's
     * the documented one.
     *
     * Doesn't use callTikTok()/$this->header - both assume an already-
     * connected $this->account, which doesn't exist yet on a first-time
     * connect (that's exactly what this method is creating).
     *
     * advertiser_ids is a list because one auth grant can cover multiple
     * TikTok Business accounts at once - every one of them gets its own
     * ad_accounts row here, matching how a single Meta/Google OAuth grant
     * fans out into multiple rows elsewhere in this app.
     *
     * $state isn't validated against session here - SocialAdManagerService
     * ::callback() (the only caller) regenerates 'ad_state' via
     * setSession() on every callback before invoking this method, so by
     * the time this runs the session no longer holds the value that was
     * actually issued to TikTok during redirect(). Comparing against it
     * would reject every real callback, not just forged ones.
     */
    public function callback($platform, $state)
    {
        $authCode = request()->input('auth_code') ?: request()->input('code');

        if (!$authCode) {
            return redirect()->route('admin.ads.dashboard')->with('error', 'TikTok did not return an authorization code.');
        }

        $tokenResponse = $this->apiService->post($this->config . 'oauth2/access_token/', ['Content-Type' => "application/json"], [
            'app_id'    => (string) adminSetting('ads.tiktok.client_id'),
            'secret'    => (string) adminSetting('ads.tiktok.client_secret'),
            'grant_type'    => 'authorization_code',
            'auth_code' => $authCode,
        ], 'json');

        $token = $this->parseTikTokResponse($tokenResponse);

        if (!$token['success']) {
            return redirect()->route('admin.ads.dashboard')->with('error', $token['error']);
        }

        $accessToken = $token['data']['access_token'] ?? null;
        $advertiserIds = $token['data']['advertiser_ids'] ?? [];

        if (!$accessToken || empty($advertiserIds)) {
            return redirect()->route('admin.ads.dashboard')->with('error', 'TikTok did not return an access token or any advertiser accounts to connect.');
        }

        // Best-effort - a failure here still leaves every advertiser id
        // connectable below (with its id as a fallback name), rather than
        // aborting the whole connection over what's just display metadata.
        $infoResponse = $this->apiService->get($this->config . 'advertiser/info/', [
            'Access-Token' => $accessToken,
            'Content-Type' => 'application/json',
        ], [
            'advertiser_ids' => json_encode(array_values($advertiserIds)),
        ]);
       
        $info = $this->parseTikTokResponse($infoResponse);
       
        $advertiserDetails = collect($info['success'] ? ($info['data']['list'] ?? $info['data'] ?? []) : [])
            ->keyBy('advertiser_id');

        $connected = 0;

        foreach ($advertiserIds as $advertiserId) {
            $details = $advertiserDetails->get($advertiserId, []);

            $this->apiService->success(
                [
                    'platform'      => $platform,
                    'user_id'       => Auth::user()->id,
                    'name'          => $details['name'] ?? "TikTok Advertiser {$advertiserId}",
                    'currency'      => $details['currency'] ?? null,
                    'ad_account_id' => $advertiserId,
                    'access_token'  => $accessToken,
                    'status'        => 'active',
                ],
                ['platform' => $platform, 'ad_account_id' => $advertiserId, 'user_id' => Auth::user()->id],
                new AdAccount
            );

            $connected++;
        }

        return redirect()->route('admin.ads.dashboard')->with('success', "Connected {$connected} TikTok advertiser account(s).");
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

        // Carousel Ads need a music track (TikTok rejects ad/create/
        // without one - "Please select valid music for Carousel Ads.",
        // confirmed live) and the auto-picked Commercial Music Library
        // default (buildCreative()'s getDefaultMusicId() fallback) hasn't
        // reliably satisfied that check in testing - a self-uploaded
        // track lets the admin supply one directly instead of depending
        // on it. Only relevant for CAROUSEL; every other ad_format either
        // doesn't use music_id at all or (SINGLE_VIDEO) has its own audio.
        if ($request['media_type'] === 'CAROUSEL' && !empty($request['music'])) {
            $musicResponse = $this->uploadMusic($request['music']);
            
            if (!$musicResponse['success']) {
                return $musicResponse;
            }

            $request['music_id'] = $musicResponse['data']['id'];
        }
       
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
            'campaign_name'      => $request['name'] . ' ' . time(),
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
            'name'                => $request['name'] . ' ' . time(),
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

        $placementType = $request['placement_type'] ?? 'PLACEMENT_TYPE_AUTOMATIC';

        // Non-OCPM billing doesn't support automatic placement at all
        // ("This ad objective only supports manual placement.", confirmed
        // live across REACH/CPM, VIDEO_VIEWS/CPV, and ENGAGEMENT/CPC -
        // three different objectives, one common factor). OCPM (TRAFFIC,
        // confirmed live) accepted automatic placement fine. Keyed off
        // billing_event rather than a growing objective whitelist since
        // that's the variable that actually tracked with the result across
        // all four tests, not objective_type itself - e.g. TRAFFIC itself
        // can also run under CPC (see optimizationGoalBillingMap's CLICK
        // entry in the blade), which on this evidence would need the same
        // forcing, not just other objectives. Placements itself is still
        // handled below the same way as any other NORMAL adgroup (defaults
        // to PLACEMENT_TIKTOK if the admin didn't pick any).
        if ($request['billing_event'] !== 'OCPM') {
            $placementType = 'PLACEMENT_TYPE_NORMAL';
        }

        $payload = [
            'advertiser_id'      => $this->account->ad_account_id,
            'campaign_id'        => $request['campaign_id'],
            'adgroup_name'       => $request['name'],
            'promotion_type'     => $request['promotion_type'] ?? null,
            'placement_type'     => $placementType,
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

        // REACH also requires explicit frequency capping ("Please set a
        // frequency cap.", confirmed live) - TikTok has no default of its
        // own. 2 views per 7 days is a conservative, commonly-used
        // default; there's no form field for this yet, so request values
        // are only honored if some future UI change adds one.
        if ($request['objective'] === 'REACH') {
            $payload['frequency'] = $request['frequency'] ?? 2;
            $payload['frequency_schedule'] = $request['frequency_schedule'] ?? 7;
        }

        // TikTok defaults to BID_TYPE_NO_BID (fully automatic bidding) when
        // bid_type isn't sent at all - in that mode any bid_price/
        // conversion_bid_price value is ignored outright, which is what
        // produced "Bid needs to be greater than 0.00" even though a real
        // bid_amount was sent. bid_type must be explicitly BID_TYPE_CUSTOM
        // for a manual bid to be read at all. Which *field* gets read also
        // depends on billing_event: OCPM (value/conversion-optimized
        // billing) reads conversion_bid_price, not bid_price - CPC/CPM/CPV
        // read bid_price - sending the bid under the wrong field for OCPM
        // has the same silent-zero effect as not sending bid_type at all.
        //
        // AUTOMATIC_VALUE_OPTIMIZATION is the one optimization_goal that
        // categorically rejects a custom bid at all ("the bid type
        // 'BID_TYPE_CUSTOM' is not supported with the 'AUTOMATIC_VALUE_
        // OPTIMIZATION' goal.", confirmed live) - it hands all bidding
        // control to TikTok by definition, so bid_amount is ignored
        // entirely here rather than sent and rejected.
        if (!empty($request['bid_amount']) && $request['optimization_goal'] !== 'AUTOMATIC_VALUE_OPTIMIZATION') {
            $bidField = $request['billing_event'] === 'OCPM' ? 'conversion_bid_price' : 'bid_price';
            $payload['bid_type'] = 'BID_TYPE_CUSTOM';
            $payload[$bidField] = (float) $request['bid_amount'];

            // VIDEO_VIEWS + a custom bid requires bid_display_mode set to
            // match the billing event explicitly ("'bid_display_mode'
            // needs to be 'CPV' when 'objective_type' is 'VIDEO_VIEW' and
            // 'bid_type' is 'CUSTOM'.", confirmed live) - other objectives
            // let TikTok assign this itself (it showed up as CPMV on its
            // own in every other objective tested), so it's only forced
            // here for CPV billing specifically.
            if ($request['billing_event'] === 'CPV') {
                $payload['bid_display_mode'] = 'CPV';
            }
        }

        if (!empty($request['promotion_target_type'])) {
            $payload['promotion_target_type'] = $request['promotion_target_type'];
        }

        if ($request['objective'] === 'APP_PROMOTION' && !empty($request['app_id'])) {
            $payload['app_id'] = $request['app_id'];
        }

        // Was gated to only CONVERT/VALUE, silently dropping pixel_id for
        // AUTOMATIC_VALUE_OPTIMIZATION even when the admin provided one -
        // confirmed live that TikTok requires a pixel for WEB_CONVERSIONS
        // regardless of which of its three optimization goals is picked
        // ("Please select a pixel.", still rejected even with a real
        // pixel_id in the request until this gate was widened). Just
        // forwards it whenever present now rather than gatekeeping by a
        // goal list our own validation already got wrong once.
        if (!empty($request['pixel_id'])) {
            $payload['pixel_id'] = $request['pixel_id'];
        }

        if (!empty($request['optimization_event'])) {
            $payload['optimization_event'] = $request['optimization_event'];
        }

        // TikTok requires placements only for NORMAL (manual) placement -
        // for AUTOMATIC it must be omitted from the payload entirely, not
        // sent empty, or the call is rejected. Defaults to PLACEMENT_
        // TIKTOK when REACH forced NORMAL above but the admin never
        // picked any (the "Placements" picker only appears in the form
        // when Manual is explicitly selected, which REACH's own forced
        // override bypasses) - REACH itself would otherwise reject the
        // adgroup for having no placements at all under NORMAL type.
        if ($placementType === 'PLACEMENT_TYPE_NORMAL') {
            $payload['placements'] = !empty($request['placements'])
                ? array_values((array) $request['placements'])
                : ['PLACEMENT_TIKTOK'];
        }

        // storeAd()->store() chains storeCampaign() straight into this
        // with zero delay, referencing the campaign_id storeCampaign()
        // just got back - occasionally TikTok's own systems haven't
        // finished propagating that brand-new campaign yet, and
        // adgroup/create/ rejects it as invalid even though the exact
        // same payload against the exact same campaign_id succeeds
        // seconds later (confirmed live this session, on a call that had
        // failed moments earlier through the app). Retried only on
        // failure, so a successful create always stops the loop
        // immediately - this can't produce duplicate ad groups.
      
        $result = $this->callTikTok('post', $endpoint, $payload);
        $attempts = 1;

        while (!$result['success'] && $attempts < 3) {
            sleep(2);
            $result = $this->callTikTok('post', $endpoint, $payload);
            $attempts++;
        }

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
            'placement_type'        => $placementType,
            'placements'            => isset($payload['placements']) ? json_encode($payload['placements']) : null,
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
            'bid_type'              => $payload['bid_type'] ?? null,
            'bid_price'             => $payload['bid_price'] ?? null,
            'conversion_bid_price'  => $payload['conversion_bid_price'] ?? null,
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

        // Standard TikTok Carousel Ads share one caption/CTA/link across
        // all cards (unlike Facebook's per-card child_attachments) - the
        // Carousel creative schema (confirmed against TikTok's own
        // AdcreateCreatives reference) has no per-image title/description
        // field at all, only the shared ad_text/landing_page_url/
        // call_to_action set once on the whole creative. There's
        // deliberately no per-card data collected or stored here.
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
                ? $this->uploadVideo($media, $fileName, $request['video_cover'] ?? null)
                : $this->uploadImage($media, $fileName);

            if (!$result['success']) {
                return $result;
            }

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
            ];

            $mediaRecord = $this->apiService->success(
                $dataToInsert,
                ['ad_media_id' => $result['data']['id']],
                new AdMedia
            );
          
            $mediaIds[] = [
                'ad_media_id'    => $mediaRecord['data']['id'],
                'media_id'       => $result['data']['id'],
                'cover_image_id' => $result['data']['cover_image_id'] ?? null,
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

    /**
     * $coverMedia is the admin's own optional cover/thumbnail upload
     * (the "Video Cover" field, shown only for media_type=VIDEO) - when
     * provided, it's uploaded via uploadImage() and used directly instead
     * of fetchVideoCoverImageId()'s auto-extracted frame, since letting
     * TikTok pick an arbitrary frame from the video can land on a blank/
     * transitional moment. Falls back to auto-generation when no cover
     * was uploaded, same as before.
     */
    private function uploadVideo($media, $fileName, $coverMedia = null)
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

        // file/video/ad/upload/'s data is a LIST (confirmed live - even a
        // single video comes back as one entry in an array), unlike
        // file/image/ad/upload/'s single object - and it carries no
        // video_cover_url or any other cover field at all, unlike what
        // was here before.
        $videoId = $result['data'][0]['video_id'] ?? null;

        $coverImageId = $coverMedia
            ? $this->uploadCoverImage($coverMedia)
            : null;

        // fetchVideoCoverImageId() chains 3 sequential TikTok calls with
        // no pacing (video info, download, image upload) right after this
        // method's own upload call - easy to trip a QPS limit and get a
        // null back even though nothing is actually wrong (confirmed live
        // this session: an identical call failed once, then succeeded
        // immediately on retry with more spacing between attempts). One
        // retry after a short pause absorbs that without masking a
        // genuine failure - a second consecutive failure still surfaces
        // as no cover, which buildCreative() and TikTok's own "must
        // upload an image" rejection make visible rather than silent.
        if (!$coverImageId && $videoId) {
            $coverImageId = $this->fetchVideoCoverImageId($videoId);

            if (!$coverImageId) {
                sleep(2);
                $coverImageId = $this->fetchVideoCoverImageId($videoId);
            }
        }

        return $this->successResponse([
            'id'             => $videoId,
            'url'            => null,
            'cover_image_id' => $coverImageId,
        ]);
    }

    /**
     * Plain file/image/ad/upload/ call for a manually-provided video
     * cover - same mechanics as uploadImage(), kept separate so its
     * return shape (just the image_id, no 'url' wrapper) matches what
     * uploadVideo() needs directly rather than reusing uploadImage()'s
     * successResponse() wrapper.
     */
    private function uploadCoverImage($coverMedia): ?string
    {
        $fileName = time() . '_' . uniqid() . '.' . strtolower($coverMedia->getClientOriginalExtension());

        $result = $this->callTikTokMultipart($this->config . 'file/image/ad/upload/', [
            'advertiser_id'   => $this->account->ad_account_id,
            'upload_type'     => 'UPLOAD_BY_FILE',
            'file_name'       => $fileName,
            'image_signature' => md5_file($coverMedia->getRealPath()),
        ], [[
            'name'       => 'image_file',
            'media_file' => $coverMedia->getRealPath(),
            'file_name'  => $fileName,
        ]]);

        return $result['success'] ? ($result['data']['image_id'] ?? null) : null;
    }

    /**
     * TikTok rejects SINGLE_VIDEO ad creation with "You must upload an
     * image." unless the creative also carries an image_ids cover
     * (confirmed live against this sandbox) - there's no field on
     * file/video/ad/upload/ to set one directly. file/video/ad/info/'s
     * video_cover_url is only a signed CDN preview link, not an
     * image_id ad/create/'s image_ids will accept, so the actual fix is
     * downloading that preview and re-uploading it through file/image/ad
     * /upload/ to get a real, usable image_id - fully automatic, no
     * extra upload asked of the admin. Best-effort: returning null on
     * any failure here just leaves the video ad without an auto cover,
     * which TikTok will then reject with the same clear "must upload an
     * image" error rather than this method silently producing a broken
     * creative.
     */
    private function fetchVideoCoverImageId(string $videoId): ?string
    {
        $infoResult = $this->callTikTok('get', $this->config . 'file/video/ad/info/', [
            'advertiser_id' => $this->account->ad_account_id,
            'video_ids'     => json_encode([$videoId]),
        ]);

        $coverUrl = $infoResult['data']['list'][0]['video_cover_url'] ?? null;

        if (!$coverUrl) {
            return null;
        }

        $imageContents = @file_get_contents($coverUrl);

        if ($imageContents === false) {
            return null;
        }

        $tmpPath = tempnam(sys_get_temp_dir(), 'tt_cover_');
        file_put_contents($tmpPath, $imageContents);
        $coverFileName = 'cover_' . $videoId . '.jpg';

        $uploadResult = $this->callTikTokMultipart($this->config . 'file/image/ad/upload/', [
            'advertiser_id'   => $this->account->ad_account_id,
            'upload_type'     => 'UPLOAD_BY_FILE',
            'file_name'       => $coverFileName,
            'image_signature' => md5_file($tmpPath),
        ], [[
            'name'       => 'image_file',
            'media_file' => $tmpPath,
            'file_name'  => $coverFileName,
        ]]);

        @unlink($tmpPath);

        return $uploadResult['success'] ? ($uploadResult['data']['image_id'] ?? null) : null;
    }

    /**
     * file/music/upload/ - self-uploaded track for Carousel Ads (see
     * store()'s call site). Verified live this session: field names
     * (music_file/music_signature, same upload_type/file_name shape as
     * uploadImage()/uploadVideo()) and that the response returns a single
     * object (not a list, unlike file/video/ad/upload/) with music_id
     * directly on it.
     */
    private function uploadMusic($media): array
    {
        $fileName = time() . '_' . uniqid() . '.' . strtolower($media->getClientOriginalExtension());
        $endpoint = $this->config . 'file/music/upload/';

        $result = $this->callTikTokMultipart($endpoint, [
            'advertiser_id'   => $this->account->ad_account_id,
            'upload_type'     => 'UPLOAD_BY_FILE',
            'file_name'       => $fileName,
            'music_signature' => md5_file($media->getRealPath()),
        ], [[
            'name'       => 'music_file',
            'media_file' => $media->getRealPath(),
            'file_name'  => $fileName,
        ]]);
       
        if (!$result['success']) {
            return $result;
        }

        $musicId = $result['data']['music_id'] ?? null;

        if (!$musicId) {
            return $this->errorResponse('TikTok did not return a music_id.');
        }

        return $this->successResponse(['id' => $musicId]);
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

    /**
     * identity_id/identity_type come from the "TikTok Identity" select
     * (getIdentities()) via page_id/identity_type on the request, not
     * re-fetched here - re-fetching and grabbing firstWhere('identity_
     * type', 'TT_USER') would silently ignore whatever the admin
     * actually picked, and crash outright (null array access) the moment
     * no TT_USER identity exists at all, e.g. an account that only has a
     * BC_AUTH_TT one.
     */
    private function buildCreative($request)
    {
        $creative = [
            'ad_name'          => $request['name'],
            'ad_text'          => $request['description'] ?? '',
            'call_to_action'   => $request['call_to_action'],
            'landing_page_url' => $request['target_link'],
            'identity_id'      => $request['page_id'],
            'identity_type'    => $request['identity_type'] ?? 'TT_USER',
        ];

        if ($request['media_type'] === 'VIDEO') {
            $creative['ad_format'] = 'SINGLE_VIDEO';
            $creative['video_id'] = $request['media'][0]['media_id'];

            // TikTok rejects SINGLE_VIDEO without a cover image
            // ("You must upload an image.", confirmed live) -
            // storeMedia()/uploadVideo() auto-generates one from the
            // video's own TikTok-hosted preview frame via
            // fetchVideoCoverImageId(), so nothing extra is asked of
            // the admin. Omitted (not sent empty) if that lookup failed,
            // so TikTok's own rejection makes the real cause clear
            // rather than this silently sending a broken creative.
            if (!empty($request['media'][0]['cover_image_id'])) {
                $creative['image_ids'] = [$request['media'][0]['cover_image_id']];
            }
        } elseif ($request['media_type'] === 'CAROUSEL') {
            // TikTok rejects CAROUSEL_ADS without music_id too ("Please
            // select valid music for Carousel Ads.", confirmed live).
            // Prefers the admin's own uploaded track (store()'s
            // uploadMusic() call, request['music_id']) over the auto-
            // picked Commercial Music Library default - four different
            // CML/self-uploaded combinations were all rejected against
            // this sandbox with the same message, so getDefaultMusicId()
            // is a best-effort fallback here, not a confirmed fix the way
            // it is for SINGLE_IMAGE below.
            $creative['ad_format'] = 'CAROUSEL_ADS';
            $creative['image_ids'] = array_column($request['media'], 'media_id');
            $creative['music_id'] = $request['music_id'] ?? $this->getDefaultMusicId();
        } else {
            // TikTok converts a static image into a video-like "post"
            // internally and rejects the ad without backing music
            // ("The source of this post is invalid. Please try again." -
            // an image-size/content-independent failure, confirmed live
            // with both a 200x200 and a proper 1080x1080 image) -
            // getDefaultMusicId() confirmed working end-to-end (real ad
            // created) using the first track TikTok's own catalog
            // returns.
            $creative['ad_format'] = 'SINGLE_IMAGE';
            $creative['image_ids'] = [$request['media'][0]['media_id']];
            $creative['music_id'] = $this->getDefaultMusicId();
        }

        return $creative;
    }

    /**
     * TikTok's Commercial Music Library (file/music/get/) - just grabs
     * whatever track TikTok's own catalog returns first, since this app
     * has no music-selection UI. Cached per-request-lifecycle isn't
     * needed (buildCreative() only calls this once per ad), but genuine
     * failures return null rather than throwing, so a catalog hiccup
     * degrades to "TikTok rejects the ad with its own clear message"
     * rather than a 500.
     */
    private function getDefaultMusicId(): ?string
    {
        $result = $this->callTikTok('get', $this->config . 'file/music/get/', [
            'advertiser_id' => $this->account->ad_account_id,
            'page_size'     => 1,
        ]);

        return $result['success'] ? ($result['data']['musics'][0]['music_id'] ?? null) : null;
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

    /**
     * TT_USER identities (real, verified TikTok profiles this advertiser
     * is authorized to post ads as) can have that authorization expire or
     * be revoked by the profile owner at any time on TikTok's side -
     * independent of the ad account connection or access token being
     * fine. That's exactly what produces TikTok's "You no longer have
     * access to the TikTok account used in this ad" error on create/
     * update.
     *
     * CUSTOMIZED_USER ("Custom Identity") is deliberately excluded below,
     * not just left out of the map - TikTok deprecated it platform-wide
     * as part of its "F.I.R.S.T." policy rollout (effective January
     * 2026): identity/create/ still succeeds and identity/get/ still
     * lists existing ones, but ad/create/ now rejects them outright with
     * "Custom identities are no longer supported" (confirmed live against
     * this exact sandbox account, not just from release notes). BC_AUTH_TT
     * (a Business-Center-linked account) is TikTok's other still-valid
     * type alongside TT_USER, so both are kept.
     *
     * No identity_type filter is sent to identity/get/ itself - confirmed
     * empirically that omitting it returns every type together in one
     * call (each item carrying its own identity_type) rather than needing
     * one call per type; filtering out CUSTOMIZED_USER happens client-side
     * below instead.
     */
    public function getIdentities(): array
    {
        $result = $this->callTikTok('get', $this->config . 'identity/get/', [
            'advertiser_id' => $this->account->ad_account_id,
        ]);

        if (!$result['success']) {
            return $result;
        }

        $identities = $result['data']['identity_list'] ?? $result['data']['list'] ?? [];

        return $this->successResponse(
            collect($identities)
                ->map(fn($identity) => [
                    'id'    => $identity['identity_id'] ?? null,
                    'type'  => $identity['identity_type'] ?? 'TT_USER',
                    'name'  => $identity['display_name'] ?? $identity['identity_id'] ?? 'Unnamed identity',
                    'image' => $identity['profile_image'] ?? null,
                ])
                ->filter(fn($identity) => $identity['id'] && $identity['type'] !== 'CUSTOMIZED_USER')
                ->values()
                ->toArray()
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
            'placements'     => ['PLACEMENT_TIKTOK'],
            'objective_type' => $objective,
            'level_range'    => 'TO_COUNTRY',
        ]);
    
        if (!$result['success']) {
            // TikTok's sandbox doesn't implement tool/region/ at all
            // (confirmed empirically - it returns a plain HTTP 404, not a
            // TikTok API error envelope), which would otherwise block
            // campaign creation entirely on sandbox no matter which
            // countries were picked. 102358 is TikTok's own documented
            // sandbox/test location id for the United States. Scoped to
            // the sandbox host specifically - a genuine transient failure
            // against the real business-api.tiktok.com host still returns
            // [] and correctly blocks storeAdGroup(), rather than silently
            // mistargeting a live campaign at the US.
            return str_contains($this->config, 'sandbox') ? ['102358'] : [];
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
   
        // Same fix as storeAdGroup() - bid_type must be explicitly
        // BID_TYPE_CUSTOM or any bid value is silently ignored (auto-bid
        // mode), OCPM reads conversion_bid_price not bid_price, and
        // AUTOMATIC_VALUE_OPTIMIZATION categorically rejects a custom bid
        // at all. Neither billing_event nor optimization_goal are part of
        // adgroup/update/'s own payload (TikTok doesn't support changing
        // either after creation), so $adGroup's stored values - what this
        // ad group was actually created with - are the source of truth
        // here, not the edit form's fields.
        if (!empty($request['bid_amount']) && $adGroup->optimization_goal !== 'AUTOMATIC_VALUE_OPTIMIZATION') {
            $bidField = $adGroup->billing_event === 'OCPM' ? 'conversion_bid_price' : 'bid_price';
            $payload['bid_type'] = 'BID_TYPE_CUSTOM';
            $payload[$bidField] = (float) $request['bid_amount'];
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
            'bid_type'            => $payload['bid_type'] ?? null,
            'bid_price'           => $payload['bid_price'] ?? null,
            'conversion_bid_price' => $payload['conversion_bid_price'] ?? null,
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

        // ad/update/'s real shape (confirmed live, one field at a time):
        // plural 'creatives' array like ad/create/ (the old singular
        // 'creative' key was silently ignored - "creatives: Missing data
        // for required field."), adgroup_id required at the top level
        // ("adgroup_id: Missing data..."), and ad_id nested inside each
        // creative rather than top-level ("creatives.0.ad_id: Missing
        // data..." when it was only sent at the top).
        $creative['ad_id'] = $existingAd->ad_id;

        $result = $this->callTikTok('post', $this->config . 'ad/update/', [
            'advertiser_id' => $this->account->ad_account_id,
            'adgroup_id'    => $adGroup['ad_adgroup_id'],
            'creatives'     => [$creative],
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

    /**
     * Pause/reactivate without deleting anything - was entirely missing
     * (SocialAdManagerService::updateStatus() calls this on every
     * platform's service uniformly, so invoking it for TikTok threw
     * "Call to undefined method" every time). Mirrors destroy()'s use of
     * the same *_status/update/ endpoints, just ENABLE/DISABLE instead of
     * DELETE - TikTok has no single "pause everything under this
     * campaign" call, so campaign/adgroup/ad each need their own request.
     */
    public function updateStatus($id, $status)
    {
        $campaign = AdCampaign::findOrFail($id);
        $adGroup = AdAdGroup::whereAdCampaignId($id)->first();
        $ad = Ad::whereAdCampaignId($id)->first();

        $operationStatus = $status === 'ACTIVE' ? 'ENABLE' : 'DISABLE';
        $isActive = $status === 'ACTIVE';

        $result = $this->callTikTok('post', $this->config . 'campaign/status/update/', [
            'advertiser_id'    => $this->account->ad_account_id,
            'campaign_ids'     => [$campaign->ad_campaign_id],
            'operation_status' => $operationStatus,
        ]);

        if (!$result['success']) {
            return $result;
        }

        if ($adGroup) {
            $this->callTikTok('post', $this->config . 'adgroup/status/update/', [
                'advertiser_id'    => $this->account->ad_account_id,
                'adgroup_ids'      => [$adGroup->ad_adgroup_id],
                'operation_status' => $operationStatus,
            ]);
            $adGroup->update(['status' => $isActive]);
        }

        if ($ad) {
            $this->callTikTok('post', $this->config . 'ad/status/update/', [
                'advertiser_id'    => $this->account->ad_account_id,
                'adgroup_id'       => $adGroup->ad_adgroup_id ?? null,
                'ad_ids'           => [$ad->ad_id],
                'operation_status' => $operationStatus,
            ]);
            $ad->update(['status' => $isActive]);
        }

        $campaign->update(['status' => $isActive]);

        return $this->successResponse(['status' => $status]);
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
