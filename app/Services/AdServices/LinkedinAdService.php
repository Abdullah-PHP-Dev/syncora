<?php

namespace App\Services\AdServices;

use App\Models\SocialAccount;
use App\Models\Admin\AdCampaign;
use App\Models\Admin\AdAdGroup;
use App\Models\Admin\AdMedia;
use App\Models\Admin\Ad;
use App\Models\Admin\AdCreative;
use App\Models\Admin\AdCreativeMedia;
use App\Models\Country;
use App\Services\ApiService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;

/**
 * LinkedIn Marketing API (REST, versioned via the Linkedin-Version header,
 * same convention LinkedInPostService uses for organic content) integration.
 *
 * Structural mapping onto this app's generic platform-agnostic ad_* tables
 * (the same tables Facebook/Snapchat/TikTok already share):
 *   - ad_campaigns  = LinkedIn's Campaign Group (adCampaignGroups) - the
 *     outer wrapper; mostly name/schedule/status.
 *   - ad_adgroups   = LinkedIn's Campaign (adCampaigns) - where budget,
 *     bidding, objective and targeting actually live, the same role
 *     Facebook's AdSet / Snapchat's AdSquad play in those services.
 *   - ad_creatives  = LinkedIn's Creative (creatives) - LinkedIn has no
 *     separate "Ad" object the way Facebook/Snapchat/TikTok do; the
 *     Creative *is* the ad. An `ads` row is still created alongside it
 *     (ad_id = the same creative URN) purely so LinkedIn fits the generic
 *     campaign->adGroups->creatives / campaign->ads schema every other
 *     service (and the shared destroy()/edit() views) already expect.
 *
 * Two creative shapes are supported:
 *   - SPONSORED_CONTENT: an image/video ad. LinkedIn has no way to attach
 *     media directly to a Creative - it must reference a Post via
 *     content.reference, so storeCreative() first creates a "Direct
 *     Sponsored Content" Post (distribution.feedDistribution=NONE, so it
 *     never appears on the Page's own organic feed, only as an ad) using
 *     the same Posts/Images/Videos endpoints LinkedInPostService uses for
 *     organic content.
 *   - TEXT_AD: LinkedIn's classic small sidebar text+logo ad, which
 *     carries its own inline content.textAd block and needs no Post.
 *
 * LinkedIn publishes no webhook event type for ad delivery metrics (spend,
 * impressions, clicks) - reporting is pull-only via the adAnalytics
 * finder. (LinkedIn does have a webhook product generally, registered in
 * the developer portal's "Webhooks" tab and gated on an approved use case,
 * and one Marketing-side subscription API - leadNotifications, for Lead Gen
 * Form responses, which can be scoped to a urn:li:sponsoredAccount - but
 * neither covers delivery metrics.) registerAdEventsCallback() below
 * records the callback URL for an admin to register by hand if LinkedIn
 * ever ships a push-capable ads product - see LinkedinAdWebhookController.
 *
 * Targeting (see buildTargeting()) covers locations, age, gender,
 * seniority, job titles, industries, skills, company size, years of
 * experience and company names - verified against LinkedIn's "Targeting
 * Criteria Facet URNs" and "Ad Targeting" docs, including the two facet
 * pairs LinkedIn's API rejects if AND'ed together (Titles+Seniorities,
 * Employers+Industries/CompanySize). Locations/Titles/Industries/Skills/
 * Employers have no small static enum worth hardcoding, so
 * resolveEntityUrns() resolves each by name through LinkedIn's Typeahead
 * API at submit time instead. The fixed-enum facets (age, gender,
 * seniority, company size) are per LinkedIn's published documentation as
 * of this writing - confirm against a live GET .../adTargetingFacets
 * response before relying on them long-term, since LinkedIn does
 * periodically revise its facet value sets (same caveat SnapchatAdService's
 * docblock flags for its own inferred endpoints). Note gender genuinely is
 * a supported facet (urn:li:adTargetingFacet:genders) - LinkedIn requires
 * any app exposing it, or any targeting facet, to show a discrimination
 * notice in its UI (see the create/edit views' Audience step).
 *
 * Unlike Facebook/Snapchat, LinkedIn's REST API has no hard-DELETE for
 * Campaign Groups/Campaigns/Creatives - the documented way to remove them
 * is a PARTIAL_UPDATE to status=ARCHIVED (see destroy()/archiveRemote()).
 * Creatives are also immutable once created - there is no documented way
 * to change a Creative's content/reference in place - so updateCreative()/
 * updateAd() are no-ops, the same real-world constraint XAdService::
 * update() already documents for X's immutable Tweets.
 */
class LinkedinAdService
{
    protected $account, $config, $apiService, $header;
    protected string $linkedinVersion = '202606';

    public function __construct(SocialAccount $account, ApiService $apiService)
    {
        $this->apiService = $apiService;
        $this->account = $account->wherePlatform('linkedin')->whereUserId(Auth::user()->id)->first();
        $this->config = adminSetting('ads.linkedin.base_url') ?: 'https://api.linkedin.com/rest/';

        if ($this->account) {
            $this->header = $this->getHeaders();
        }
    }

    public function redirect($platform, $state)
    {
        $clientId = adminSetting('ads.linkedin.client_id');

        $url = 'https://www.linkedin.com/oauth/v2/authorization?' . http_build_query([
            'response_type' => 'code',
            'client_id'     => $clientId,
            'redirect_uri'  => $this->getCallbackUrl(),
            'state'         => $state,
            // r_ads: read ad accounts/campaigns/creatives. rw_ads: create/
            // manage them. r_ads_reporting: the adAnalytics finder - the
            // pull-only substitute for the webhook LinkedIn doesn't offer,
            // see class docblock.
            'scope'         => 'r_ads rw_ads r_ads_reporting',
        ]);

        return Redirect::to($url);
    }

    private function getCallbackUrl()
    {
        return route('admin.ads.platform.callback', 'linkedin');
    }

    /**
     * $platform is passed by SocialAdManagerService::callback() ahead of
     * $state - the signature previously omitted it, so $state silently
     * received the platform string instead. Neither is used for anything
     * beyond the error/abort path below, but the arity now matches the
     * caller (and TiktokAdService::callback()'s signature).
     */
    public function callback($platform, $state = null)
    {
        // LinkedIn reports a refused/failed authorization by redirecting
        // back with error/error_description instead of a code - most often
        // `unauthorized_scope_error`, meaning the app has not been granted
        // the Advertising API product that the r_ads/rw_ads/r_ads_reporting
        // scopes in redirect() require. Without this branch that arrived
        // here as a null $code and got reported as a generic token-exchange
        // failure, hiding the real cause.
        if (request()->filled('error')) {
            Log::warning('LinkedIn ads OAuth authorization was refused.', [
                'error'             => request()->input('error'),
                'error_description' => request()->input('error_description'),
            ]);

            return redirect()->route('admin.ads.dashboard')->with('error', 'LinkedIn refused the ads authorization: ' . (request()->input('error_description') ?: request()->input('error')));
        }

        $code = request()->input('code');

        if (!$code) {
            return redirect()->route('admin.ads.dashboard')->with('error', 'LinkedIn did not return an authorization code.');
        }

        $tokenResponse = $this->apiService->post(
            'https://www.linkedin.com/oauth/v2/accessToken',
            [],
            [
                'grant_type'    => 'authorization_code',
                'code'          => $code,
                'client_id'     => adminSetting('ads.linkedin.client_id'),
                'client_secret' => adminSetting('ads.linkedin.client_secret'),
                'redirect_uri'  => $this->getCallbackUrl(),
            ],
            'form'
        );

        if (!$tokenResponse['success']) {
            Log::warning('LinkedIn ads token exchange failed.', [
                'status' => $tokenResponse['status'] ?? null,
                'body'   => $tokenResponse['body'] ?? ($tokenResponse['error'] ?? null),
            ]);

            return redirect()->route('admin.ads.dashboard')->with('error', $tokenResponse['data']['error_description'] ?? 'Failed to exchange code for a LinkedIn access token.');
        }

        $data = $tokenResponse['data'];
        $accessToken = $data['access_token'];
        $expiresAt = Carbon::now()->addSeconds($data['expires_in'] ?? 3600);

        $headers = [
            'Authorization'             => 'Bearer ' . $accessToken,
            'Linkedin-Version'          => $this->linkedinVersion,
            'X-Restli-Protocol-Version' => '2.0.0',
        ];

        // Every ad account this member has a role on - the same "resolve
        // every manageable entity" shape as
        // PostAccountController::callbackLinkedin()'s organizationAcls walk.
        $accountsResponse = $this->apiService->get($this->config . 'adAccountUsers', $headers, [
            'q' => 'authenticatedUser',
        ]);
        if (!$accountsResponse['success']) {
            // A 403 here almost always means the token came back without the
            // r_ads/rw_ads scopes because the app is not approved for the
            // Advertising API product - LinkedIn issues the token anyway and
            // only refuses at the resource, so this is the first point the
            // problem is observable. Log the raw body; LinkedIn's error
            // envelope names the missing permission.
            Log::warning('LinkedIn adAccountUsers fetch failed.', [
                'status' => $accountsResponse['status'] ?? null,
                'body'   => $accountsResponse['body'] ?? ($accountsResponse['error'] ?? null),
            ]);

            return redirect()->route('admin.ads.dashboard')->with('error', $accountsResponse['data']['message'] ?? 'Connected, but could not fetch your LinkedIn Ad Accounts.');
        }

        $connected = 0;

        foreach ($accountsResponse['data']['elements'] ?? [] as $entry) {
            $accountUrn = $entry['account'] ?? null;

            if (!$accountUrn || !preg_match('#urn:li:sponsoredAccount:(\d+)#', $accountUrn, $matches)) {
                continue;
            }

            $accountId = $matches[1];

            $detailResponse = $this->apiService->get($this->config . 'adAccounts/' . $accountId, $headers);
            $detail = $detailResponse['success'] ? $detailResponse['data'] : [];

            // Skip accounts that aren't usable (eg. REMOVED/CANCELED) - the
            // same active-only filter FacebookAdService applies to Pages.
            if (!empty($detail['status']) && !in_array($detail['status'], ['ACTIVE', 'DRAFT'], true)) {
                continue;
            }

            // Ad accounts and Organization Pages are different LinkedIn
            // entities linked by this `reference` field - stored in
            // `metadata['profile_id']` (the same convention
            // SnapchatAdService uses for its own account->profile
            // relationship) so createSponsoredPost() knows which
            // Organization to author as.
            $orgId = null;
            if (!empty($detail['reference']) && preg_match('#urn:li:organization:(\d+)#', $detail['reference'], $orgMatch)) {
                $orgId = $orgMatch[1];
            }

            $result = $this->apiService->success(
                [
                    'platform'      => 'linkedin',
                    'user_id'       => Auth::id(),
                    'name'          => $detail['name'] ?? "LinkedIn Ad Account {$accountId}",
                    'platform_account_id' => $accountId,
                    'access_token'  => $accessToken,
                    'refresh_token' => $data['refresh_token'] ?? null,
                    'expires_at'    => $expiresAt,
                    'has_ads_permission' => true,
                    'metadata'      => array_filter(['currency' => $detail['currency'] ?? null, 'profile_id' => $orgId]),
                ],
                [
                    'platform'      => 'linkedin',
                    'platform_account_id' => $accountId,
                    'user_id'       => Auth::id(),
                ],
                new SocialAccount
            );

            $connected++;

            try {
                $this->registerAdEventsCallback($result['data']);
            } catch (\Throwable $e) {
                Log::warning('LinkedIn ad events callback registration failed after connect.', ['account_id' => $result['data']->id, 'error' => $e->getMessage()]);
            }
        }

        if ($connected === 0) {
            return redirect()->route('admin.ads.dashboard')->with('error', 'Connected to LinkedIn, but no usable Ad Account was found where you have a role.');
        }

        return redirect()->route('admin.ads.dashboard')->with('success', "Connected {$connected} LinkedIn Ad Account(s).");
    }

    /**
     * See class docblock - there is no real LinkedIn endpoint to register a
     * push subscription against. This records the callback URL an admin
     * would register by hand if the org is later approved for a push-
     * capable LinkedIn product; reporting stays pull-only via the
     * adAnalytics finder in the meantime.
     */
    private function registerAdEventsCallback(SocialAccount $account): void
    {
        $metadata = $account->metadata ?? [];
        $metadata['settings'] = array_merge($metadata['settings'] ?? [], [
            'ad_events_callback_url' => route('ads.webhook.linkedin.receive'),
        ]);

        $account->update(['metadata' => $metadata]);
    }

    /**
     * Guards every write entry point below. Without it, an unconnected (or
     * expired-and-unrefreshable) LinkedIn account fataled on
     * $this->account->platform_account_id / $this->header['data'] deep inside the
     * first API call, which the campaign form's AJAX handler could only
     * render as a bare "Server Error".
     */
    private function accountIsUsable()
    {
        if (!$this->account) {
            return $this->errorResponse('No connected LinkedIn Ad Account. Connect one from the Ads dashboard first.');
        }

        if (!($this->header['success'] ?? false)) {
            return $this->errorResponse($this->header['error'] ?? 'The LinkedIn Ad Account token has expired. Reconnect the account from the Ads dashboard.');
        }

        return null;
    }

    public function store($platform, $request)
    {
        if ($guard = $this->accountIsUsable()) {
            return $guard;
        }

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

        if (($request['creative_type'] ?? 'SPONSORED_CONTENT') === 'SPONSORED_CONTENT') {
            $response = $this->storeMedia($platform, $request);

            if (!$response['success']) {
                return $response;
            }

            $request['media'] = $response['data'];
        }

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
        $endpoint = $this->config . 'adAccounts/' . $this->account->platform_account_id . '/adCampaignGroups';

        $payload = array_filter([
            'account'     => 'urn:li:sponsoredAccount:' . $this->account->platform_account_id,
            'name'        => $request['name'],
            'status'      => 'DRAFT',
            'runSchedule' => array_filter([
                'start' => Carbon::parse($request['start_time'])->utc()->getTimestampMs(),
                'end'   => !empty($request['end_time']) ? Carbon::parse($request['end_time'])->utc()->getTimestampMs() : null,
            ]),
        ]);

        $response = $this->apiService->post($endpoint, $this->header['data'], $payload);

        if (!$response['success']) {
            return $this->errorResponse($response['data']['message'] ?? 'Failed to create LinkedIn Campaign Group.');
        }

        $id = $response['restli_id'] ?? null;

        if (!$id) {
            return $this->errorResponse('LinkedIn did not return a Campaign Group ID.');
        }

        $dataToInsert = [
            'ad_campaign_id' => $id,
            'user_id'        => Auth::id(),
            'social_account_id'  => $this->account->id,
            'name'           => $request['name'],
            'objective'      => $request['objective'] ?? null,
            'platform'       => $platform,
            'start_time'     => $request['start_time'],
            'end_time'       => $request['end_time'] ?? null,
            'budget_mode'    => $request['budget_mode'] ?? null,
            'budget'         => $request['final_budget'] ?? ($request['budget'] ?? null),
            'status'         => false,
        ];

        return $this->apiService->success($dataToInsert, ['ad_campaign_id' => $id], new AdCampaign);
    }

    private function storeAdGroup($platform, $request)
    {
        $endpoint = $this->config . 'adAccounts/' . $this->account->platform_account_id . '/adCampaigns';
        $targeting = $this->buildTargeting($request);

        $objective = $request['objective'] ?? 'WEBSITE_VISIT';
        $optimizationGoal = $request['optimization_goal'] ?? $this->defaultOptimizationGoal($objective);
        $costType = $request['bid_type'] ?? 'CPC';
        $creativeType = $request['creative_type'] ?? 'SPONSORED_CONTENT';

        $payload = array_filter([
            'account'                => 'urn:li:sponsoredAccount:' . $this->account->platform_account_id,
            'campaignGroup'          => 'urn:li:sponsoredCampaignGroup:' . $request['campaign_id'],
            'name'                   => $request['name'],
            'type'                   => $creativeType === 'TEXT_AD' ? 'TEXT_AD' : 'SPONSORED_UPDATES',
            'status'                 => 'DRAFT',
            'costType'               => $costType,
            'objectiveType'          => $objective,
            'optimizationTargetType' => $optimizationGoal,
            'locale'                 => ['country' => 'US', 'language' => 'en'],
            'runSchedule'            => array_filter([
                'start' => Carbon::parse($request['start_time'])->utc()->getTimestampMs(),
                'end'   => !empty($request['end_time']) ? Carbon::parse($request['end_time'])->utc()->getTimestampMs() : null,
            ]),
            'targetingCriteria'      => $targeting['targetingCriteria'],
        ]);

        $budgetAmount = number_format((float) $request['budget'], 2, '.', '');

        if (($request['budget_mode'] ?? 'daily') === 'daily') {
            $payload['dailyBudget'] = ['currencyCode' => $this->account->currency ?? 'USD', 'amount' => $budgetAmount];
        } else {
            $payload['totalBudget'] = ['currencyCode' => $this->account->currency ?? 'USD', 'amount' => $budgetAmount];
        }

        if (!empty($request['bid_amount'])) {
            $payload['unitCost'] = ['currencyCode' => $this->account->currency ?? 'USD', 'amount' => number_format((float) $request['bid_amount'], 2, '.', '')];
        }

        $response = $this->apiService->post($endpoint, $this->header['data'], $payload);

        if (!$response['success']) {
            return $this->errorResponse($response['data']['message'] ?? 'Failed to create LinkedIn Campaign.');
        }

        $id = $response['restli_id'] ?? null;

        if (!$id) {
            return $this->errorResponse('LinkedIn did not return a Campaign ID.');
        }

        $dataToInsert = [
            'ad_campaign_id'      => $request['ad_campaign_id'],
            'user_id'             => Auth::id(),
            'ad_adgroup_id'       => $id,
            'social_account_id'       => $this->account->id,
            'name'                => $request['name'],
            'platform'            => $platform,
            'objective'           => $objective,
            'optimization_goal'   => $optimizationGoal,
            'bid_type'            => $costType,
            'bid_price'           => $request['bid_amount'] ?? null,
            'budget_mode'         => $request['budget_mode'] ?? 'daily',
            'budget'              => $request['final_budget'] ?? $request['budget'],
            'schedule_start_time' => $request['start_time'],
            'schedule_end_time'   => $request['end_time'] ?? null,
            // Local Country IDs (not the resolved LinkedIn geo URNs) - same
            // "our own representation, for re-populating the edit form"
            // role location_ids plays for Facebook/Snapchat, since a
            // urn:li:geo: value can't be reverse-mapped back to a Country
            // row. The URNs LinkedIn actually needs live in
            // targeting_criteria below instead.
            'location_ids'        => json_encode($request['countries'] ?? []),
            // Local, re-editable representation of every targeting facet
            // selected (not just age/seniority anymore) - the resolved
            // LinkedIn URNs actually sent live in targeting_criteria below.
            'age_groups'          => json_encode($targeting['local_selections']),
            'targeting_criteria'  => json_encode($targeting['targetingCriteria']),
            'status'              => false,
        ];

        return $this->apiService->success($dataToInsert, ['ad_adgroup_id' => $id], new AdAdGroup);
    }

    private function defaultOptimizationGoal(string $objective): string
    {
        return match ($objective) {
            'BRAND_AWARENESS'    => 'MAX_IMPRESSION',
            'ENGAGEMENT'         => 'MAX_ENGAGEMENT',
            'VIDEO_VIEW'         => 'MAX_VIDEO_VIEW',
            'LEAD_GENERATION'    => 'MAX_LEAD',
            'WEBSITE_CONVERSION' => 'MAX_CONVERSION',
            // LinkedIn's own "Optimization based on ObjectiveType" table
            // describes Job Applicants as optimizing for "clicks to job
            // ad" - the same click-optimization semantics as Website
            // Visit's MAX_CLICK, which is the closest documented
            // optimizationTargetType; no distinct enum value for it is
            // shown in LinkedIn's Campaign API reference.
            'JOB_APPLICANT'       => 'MAX_CLICK',
            default              => 'MAX_CLICK', // WEBSITE_VISIT
        };
    }

    /**
     * Builds LinkedIn's targetingCriteria.include.and[].or shape from the
     * form's inputs. Covers locations, age, gender, seniority, job titles,
     * industries, skills, company size, years of experience and company
     * names - the facets most relevant to B2B campaign targeting, verified
     * against LinkedIn's "Targeting Criteria Facet URNs" and "Ad Targeting"
     * docs. LinkedIn supports further facets not exposed here (schools,
     * degrees, fields of study, interests, member groups - the last of
     * which LinkedIn has disabled for EEA/Switzerland audiences since May
     * 2024 - company growth rate/category, buyer groups).
     *
     * Two mutual-exclusivity rules are enforced per that same
     * documentation (LinkedIn's API rejects a request that violates
     * them outright): Job Titles may not be AND'ed with Seniorities, and
     * Company Names (employers) may not be AND'ed with Industries or
     * Company Size. When both sides of either pair are submitted, the
     * more specific facet (Titles, Employers) wins and the other is
     * dropped, logged for visibility.
     *
     * Gender is a real, documented facet (urn:li:adTargetingFacet:genders)
     * despite this class's own earlier docblock claiming otherwise - that
     * was wrong. LinkedIn requires any app that exposes it (or any other
     * targeting facet) to display a discrimination notice in its UI; see
     * the create/edit views' Audience step for that notice.
     */
    private function buildTargeting(array $request): array
    {
        $andClauses = [];
        $local = [];

        $geoUrns = [];
        foreach (Country::whereIn('id', $request['countries'] ?? [])->pluck('name') as $countryName) {
            $urn = $this->resolveEntityUrns('urn:li:adTargetingFacet:locations', [$countryName])[0] ?? null;

            if ($urn) {
                $geoUrns[] = $urn;
            }
        }

        if (!empty($geoUrns)) {
            $andClauses[] = ['or' => ['urn:li:adTargetingFacet:locations' => $geoUrns]];
        }

        // Age ranges - fixed enum per LinkedIn's docs. 2147483647 is
        // LinkedIn's own INT_MAX sentinel for "no upper limit" on this and
        // every other range-based facet (company size, years of
        // experience, growth rate) - the previous (55,99) here was wrong.
        $ageMap = [
            '18-24' => 'urn:li:ageRange:(18,24)',
            '25-34' => 'urn:li:ageRange:(25,34)',
            '35-54' => 'urn:li:ageRange:(35,54)',
            '55+'   => 'urn:li:ageRange:(55,2147483647)',
        ];

        $ageUrns = array_values(array_filter(array_map(fn($a) => $ageMap[$a] ?? null, $request['age_range'] ?? [])));

        if (!empty($ageUrns)) {
            $andClauses[] = ['or' => ['urn:li:adTargetingFacet:ageRanges' => $ageUrns]];
        }

        $local['age_ranges'] = $request['age_range'] ?? [];

        $genderMap = ['male' => 'urn:li:gender:MALE', 'female' => 'urn:li:gender:FEMALE'];
        $genderUrns = array_values(array_filter(array_map(fn($g) => $genderMap[$g] ?? null, $request['genders'] ?? [])));

        if (!empty($genderUrns)) {
            $andClauses[] = ['or' => ['urn:li:adTargetingFacet:genders' => $genderUrns]];
        }

        $local['genders'] = $request['genders'] ?? [];

        // Seniority - fixed enum, LinkedIn's published standardized
        // taxonomy (confirmed live via adTargetingEntities: 1 Unpaid,
        // 2 Training, 3 Entry, 4 Senior, ... 9 Partner, 10 Owner).
        $seniorityMap = [
            'unpaid'   => 'urn:li:seniority:1',
            'training' => 'urn:li:seniority:2',
            'entry'    => 'urn:li:seniority:3',
            'senior'   => 'urn:li:seniority:4',
            'manager'  => 'urn:li:seniority:5',
            'director' => 'urn:li:seniority:6',
            'vp'       => 'urn:li:seniority:7',
            'cxo'      => 'urn:li:seniority:8',
            'partner'  => 'urn:li:seniority:9',
            'owner'    => 'urn:li:seniority:10',
        ];

        $seniorityUrns = array_values(array_filter(array_map(fn($s) => $seniorityMap[$s] ?? null, $request['seniorities'] ?? [])));
        $local['seniorities'] = $request['seniorities'] ?? [];

        // Job Titles - free-text, typeahead-resolved (LinkedIn's Titles
        // taxonomy has no static enum small enough to hardcode).
        $titleUrns = $this->resolveEntityUrns('urn:li:adTargetingFacet:titles', $this->splitCsv($request['titles'] ?? ''), 'TITLE');
        $local['titles'] = $request['titles'] ?? '';

        if (!empty($titleUrns)) {
            if (!empty($seniorityUrns)) {
                Log::info('LinkedIn targeting: dropping Seniorities because Job Titles was also submitted (mutually exclusive facets).');
                $seniorityUrns = [];
            }

            $andClauses[] = ['or' => ['urn:li:adTargetingFacet:titles' => $titleUrns]];
        }

        if (!empty($seniorityUrns)) {
            $andClauses[] = ['or' => ['urn:li:adTargetingFacet:seniorities' => $seniorityUrns]];
        }

        // Industries - free-text, typeahead-resolved.
        $industryUrns = $this->resolveEntityUrns('urn:li:adTargetingFacet:industries', $this->splitCsv($request['industries'] ?? ''), 'INDUSTRY');
        $local['industries'] = $request['industries'] ?? '';

        // Company size - fixed enum ranges.
        $staffCountMap = [
            '1'          => 'urn:li:staffCountRange:(1,1)',
            '2-10'       => 'urn:li:staffCountRange:(2,10)',
            '11-50'      => 'urn:li:staffCountRange:(11,50)',
            '51-200'     => 'urn:li:staffCountRange:(51,200)',
            '201-500'    => 'urn:li:staffCountRange:(201,500)',
            '501-1000'   => 'urn:li:staffCountRange:(501,1000)',
            '1001-5000'  => 'urn:li:staffCountRange:(1001,5000)',
            '5001-10000' => 'urn:li:staffCountRange:(5001,10000)',
            '10001+'     => 'urn:li:staffCountRange:(10001,2147483647)',
        ];

        $staffCountUrns = array_values(array_filter(array_map(fn($s) => $staffCountMap[$s] ?? null, $request['company_size'] ?? [])));
        $local['company_size'] = $request['company_size'] ?? [];

        // Company names - free-text, typeahead-resolved.
        $employerUrns = $this->resolveEntityUrns('urn:li:adTargetingFacet:employers', $this->splitCsv($request['employers'] ?? ''), 'COMPANY');
        $local['employers'] = $request['employers'] ?? '';

        if (!empty($employerUrns)) {
            if (!empty($industryUrns) || !empty($staffCountUrns)) {
                Log::info('LinkedIn targeting: dropping Industries/Company Size because Company Names was also submitted (mutually exclusive facets).');
                $industryUrns = [];
                $staffCountUrns = [];
            }

            $andClauses[] = ['or' => ['urn:li:adTargetingFacet:employers' => $employerUrns]];
        }

        if (!empty($industryUrns)) {
            $andClauses[] = ['or' => ['urn:li:adTargetingFacet:industries' => $industryUrns]];
        }

        if (!empty($staffCountUrns)) {
            $andClauses[] = ['or' => ['urn:li:adTargetingFacet:staffCountRanges' => $staffCountUrns]];
        }

        // Skills - free-text, typeahead-resolved.
        $skillUrns = $this->resolveEntityUrns('urn:li:adTargetingFacet:skills', $this->splitCsv($request['skills'] ?? ''), 'SKILL');
        $local['skills'] = $request['skills'] ?? '';

        if (!empty($skillUrns)) {
            $andClauses[] = ['or' => ['urn:li:adTargetingFacet:skills' => $skillUrns]];
        }

        // Years of experience - fixed 1-12+ range; accepts up to 2 URNs
        // as a lower/upper bound pair, not a full enumerated value list.
        $experienceUrns = [];

        if (!empty($request['years_experience_min'])) {
            $experienceUrns[] = 'urn:li:yearsOfExperience:' . (int) $request['years_experience_min'];
        }

        if (!empty($request['years_experience_max'])) {
            $experienceUrns[] = 'urn:li:yearsOfExperience:' . (int) $request['years_experience_max'];
        }

        $local['years_experience_min'] = $request['years_experience_min'] ?? null;
        $local['years_experience_max'] = $request['years_experience_max'] ?? null;

        if (!empty($experienceUrns)) {
            $andClauses[] = ['or' => ['urn:li:adTargetingFacet:yearsOfExperienceRanges' => $experienceUrns]];
        }

        return [
            'targetingCriteria' => ['include' => ['and' => $andClauses]],
            'geo_urns'          => $geoUrns,
            'local_selections'  => $local,
        ];
    }

    /**
     * Splits a comma-separated free-text field (Industries/Titles/Skills/
     * Company Names inputs) into trimmed, non-empty query strings.
     */
    private function splitCsv(?string $value): array
    {
        if (empty($value)) {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $value))));
    }

    /**
     * Resolves free-text queries to LinkedIn targeting entity URNs via its
     * Typeahead API - used for every facet without a small, stable, static
     * enum worth hardcoding (locations, industries, job titles, skills,
     * company names). `q=typeahead` and `queryVersion=QUERY_USES_URNS` are
     * the exact literal values LinkedIn's docs show in a working sample
     * request - a previous version of this method sent `q=TYPEAHEAD`
     * (wrong case) with no queryVersion at all, which the API's default
     * fallback likely masked rather than actually accepted. locale is
     * deliberately omitted - LinkedIn defaults it to language=en/
     * country=US, which is what every caller here wants anyway, and its
     * documented wire format (locale=(language:en,country:US), a Rest.li
     * compound literal) doesn't match plain dotted query params. One
     * request per query since Typeahead only accepts a single search
     * string at a time; each is independently best-effort so one
     * unmatched query doesn't drop the rest.
     */
    private function resolveEntityUrns(string $facetUrn, array $queries, ?string $entityType = null): array
    {
        $urns = [];

        foreach ($queries as $query) {
            try {
                $params = [
                    'q'            => 'typeahead',
                    'queryVersion' => 'QUERY_USES_URNS',
                    'facet'        => $facetUrn,
                    'query'        => $query,
                ];

                if ($entityType) {
                    $params['entityType'] = $entityType;
                }

                $response = $this->apiService->get($this->config . 'adTargetingEntities', $this->header['data'], $params);

                if (!$response['success'] || empty($response['data']['elements'][0]['urn'])) {
                    Log::warning('LinkedIn targeting typeahead lookup found no match.', ['facet' => $facetUrn, 'query' => $query, 'response' => $response['data'] ?? null]);
                    continue;
                }

                $urns[] = $response['data']['elements'][0]['urn'];
            } catch (\Throwable $e) {
                Log::warning('LinkedIn targeting typeahead lookup threw.', ['facet' => $facetUrn, 'query' => $query, 'error' => $e->getMessage()]);
            }
        }

        return $urns;
    }

    private function storeMedia($platform, $request)
    {
        $mediaIds = [];

        foreach ($request['media'] as $media) {
            $extension = strtolower($media->getClientOriginalExtension());
            $isVideo = in_array($extension, ['mp4', 'mov', 'avi', 'mkv', 'webm']);
            $fileName = time() . '_' . uniqid() . '.' . $extension;
            $s3Path = "uploads/{$platform}/" . ($isVideo ? 'video' : 'image') . "/{$fileName}";

            Storage::disk('r2')->put($s3Path, file_get_contents($media->getRealPath()), ['visibility' => 'public']);
            $filePath = Storage::disk('r2')->url($s3Path);

            $urn = $isVideo
                ? $this->uploadVideo($media)
                : $this->uploadImage($media);

            if (!$urn) {
                return $this->errorResponse('Failed to upload media to LinkedIn.');
            }

            $dataToInsert = [
                'ad_media_id'    => $urn,
                'social_account_id'  => $this->account->id,
                'ad_campaign_id' => $request['ad_campaign_id'],
                'platform'       => $platform,
                'name'           => $fileName,
                'url'            => $filePath,
                'download_link'  => $filePath,
                'type'           => $isVideo ? 'VIDEO' : 'IMAGE',
                'status'         => true,
                'file_name'      => $fileName,
                'image_category' => $isVideo ? 'VIDEO' : 'IMAGE',
                'user_id'        => Auth::id(),
            ];

            $localMedia = $this->apiService->success($dataToInsert, ['ad_media_id' => $urn], new AdMedia);

            $mediaIds[] = [
                'ad_media_id' => $localMedia['data']['id'],
                'media_id'    => $urn,
            ];
        }

        return ['success' => true, 'data' => $mediaIds];
    }

    /**
     * Same initializeUpload/PUT-to-uploadUrl flow as
     * LinkedInPostService::uploadLinkedinImage() for organic posts, but
     * owned by the ad account (urn:li:sponsoredAccount) rather than the
     * organization, since ad-image assets belong to the Ad Account here.
     */
    private function uploadImage($media): ?string
    {
        $initResponse = $this->apiService->post($this->config . 'images?action=initializeUpload', $this->header['data'], [
            'initializeUploadRequest' => [
                'owner' => 'urn:li:sponsoredAccount:' . $this->account->platform_account_id,
            ],
        ]);

        if (!$initResponse['success']) {
            Log::error('LinkedIn ad image init failed', ['response' => $initResponse['data'] ?? null]);
            return null;
        }

        $uploadUrl = $initResponse['data']['value']['uploadUrl'] ?? null;
        $imageUrn = $initResponse['data']['value']['image'] ?? null;

        if (!$uploadUrl || !$imageUrn) {
            return null;
        }

        $uploadResponse = Http::withHeaders(['Authorization' => $this->header['data']['Authorization']])
            ->withBody(file_get_contents($media->getRealPath()), $media->getMimeType())
            ->put($uploadUrl);

        return $uploadResponse->successful() ? $imageUrn : null;
    }

    private function uploadVideo($media): ?string
    {
        $fileSize = $media->getSize();

        $initResponse = $this->apiService->post($this->config . 'videos?action=initializeUpload', $this->header['data'], [
            'initializeUploadRequest' => [
                'owner'           => 'urn:li:sponsoredAccount:' . $this->account->platform_account_id,
                'fileSizeBytes'   => $fileSize,
                'uploadCaptions'  => false,
                'uploadThumbnail' => false,
            ],
        ]);

        if (!$initResponse['success']) {
            Log::error('LinkedIn ad video init failed', ['response' => $initResponse['data'] ?? null]);
            return null;
        }

        $uploadInstructions = $initResponse['data']['value']['uploadInstructions'] ?? [];
        $videoUrn = $initResponse['data']['value']['video'] ?? null;
        $uploadToken = $initResponse['data']['value']['uploadToken'] ?? '';

        if (!$videoUrn || empty($uploadInstructions)) {
            return null;
        }

        $fileContent = file_get_contents($media->getRealPath());
        $uploadedPartIds = [];

        foreach ($uploadInstructions as $instruction) {
            $chunk = substr($fileContent, $instruction['firstByte'], $instruction['lastByte'] - $instruction['firstByte'] + 1);

            $partResponse = Http::withHeaders(['Authorization' => $this->header['data']['Authorization']])
                ->withBody($chunk, 'application/octet-stream')
                ->put($instruction['uploadUrl']);

            if (!$partResponse->successful()) {
                Log::error('LinkedIn ad video part upload failed', ['part' => $instruction]);
                return null;
            }

            $uploadedPartIds[] = $partResponse->header('ETag');
        }

        $finalizeResponse = $this->apiService->post($this->config . 'videos?action=finalizeUpload', $this->header['data'], [
            'finalizeUploadRequest' => [
                'video'           => $videoUrn,
                'uploadToken'     => $uploadToken,
                'uploadedPartIds' => $uploadedPartIds,
            ],
        ]);

        return $finalizeResponse['success'] ? $videoUrn : null;
    }

    private function storeCreative($platform, $request)
    {
        $creativeType = $request['creative_type'] ?? 'SPONSORED_CONTENT';

        if ($creativeType === 'TEXT_AD') {
            $payload = [
                'campaign'       => 'urn:li:sponsoredCampaign:' . $request['adgroup_id'],
                'type'           => 'TEXT_AD',
                'intendedStatus' => 'PAUSED',
                'content'        => [
                    'textAd' => array_filter([
                        'headline'    => $request['name'],
                        'description' => $request['description'] ?? null,
                        'landingPage' => $request['target_link'] ?? null,
                    ]),
                ],
            ];
        } else {
            $postResult = $this->createSponsoredPost($request);

            if (!$postResult['success']) {
                return $postResult;
            }

            $payload = [
                'campaign'       => 'urn:li:sponsoredCampaign:' . $request['adgroup_id'],
                'type'           => 'SPONSORED_STATUS_UPDATE',
                'intendedStatus' => 'PAUSED',
                'content'        => [
                    'reference' => $postResult['data'],
                ],
            ];
        }

        $response = $this->apiService->post($this->config . 'adAccounts/' . $this->account->platform_account_id . '/creatives', $this->header['data'], $payload);

        if (!$response['success']) {
            return $this->errorResponse($response['data']['message'] ?? 'Failed to create LinkedIn Creative.');
        }

        $id = $response['restli_id'] ?? null;

        if (!$id) {
            return $this->errorResponse('LinkedIn did not return a Creative ID.');
        }

        $dataToInsert = [
            'user_id'        => Auth::id(),
            'ad_adgroup_id'  => $request['ad_adgroup_id'],
            'ad_creative_id' => $id,
            'platform'       => 'linkedin',
            'social_account_id'  => $this->account->id,
            'ad_campaign_id' => $request['ad_campaign_id'],
            'name'           => $request['name'],
            'type'           => $creativeType,
            'message'        => $request['description'] ?? null,
            'headline'       => $request['name'],
            'url'            => $request['target_link'] ?? null,
            'call_to_action' => $request['call_to_action'] ?? null,
        ];

        $creative = $this->apiService->success($dataToInsert, ['ad_creative_id' => $id], new AdCreative);

        foreach ($request['media'] ?? [] as $media) {
            $this->apiService->success(
                ['ad_media_id' => $media['ad_media_id'], 'ad_creative_id' => $creative['data']['id']],
                ['ad_media_id' => $media['ad_media_id'], 'ad_creative_id' => $creative['data']['id']],
                new AdCreativeMedia
            );
        }

        return $creative;
    }

    /**
     * "Direct Sponsored Content" - a Post that exists purely as ad content
     * and never appears on the Page's own organic feed
     * (distribution.feedDistribution=NONE). Reuses the same Posts/Images/
     * Videos endpoint shapes LinkedInPostService uses for organic content,
     * since Sponsored Content creatives reference a Post URN via
     * content.reference rather than carrying media inline themselves.
     */
    private function createSponsoredPost($request)
    {
        $organizationId = $this->account->metadata['profile_id'] ?? $this->account->platform_account_id;

        $payload = [
            'author'                    => 'urn:li:organization:' . $organizationId,
            'commentary'                => $request['description'] ?? $request['name'],
            'visibility'                => 'PUBLIC',
            'distribution'              => [
                'feedDistribution'                => 'NONE',
                'targetEntities'                   => [],
                'thirdPartyDistributionChannels'   => [],
            ],
            'lifecycleState'            => 'PUBLISHED',
            'isReshareDisabledByAuthor' => true,
        ];

        if (!empty($request['media'])) {
            if (count($request['media']) === 1) {
                $payload['content'] = [
                    'media' => [
                        'id'    => $request['media'][0]['media_id'],
                        'title' => $request['name'],
                    ],
                ];
            } else {
                $payload['content'] = [
                    'multiImage' => [
                        'images' => array_map(fn($m) => ['id' => $m['media_id']], $request['media']),
                    ],
                ];
            }
        }

        $response = $this->apiService->post($this->config . 'posts', $this->header['data'], $payload);

        if (!$response['success']) {
            return $this->errorResponse($response['data']['message'] ?? 'Failed to create LinkedIn Sponsored Content post.');
        }

        $postUrn = $response['restli_id'] ?? null;

        if (!$postUrn) {
            return $this->errorResponse('LinkedIn did not return a Post ID for the Sponsored Content.');
        }

        return $this->successResponse($postUrn);
    }

    private function storeAd($platform, $request)
    {
        // LinkedIn has no separate "Ad" object - the Creative created in
        // storeCreative() above already is the ad. This just persists the
        // local `ads` row (ad_id = the same creative URN) so LinkedIn fits
        // the generic campaign->ads schema every other service/view
        // expects - see class docblock.
        $dataToInsert = [
            'user_id'        => Auth::id(),
            'ad_adgroup_id'  => $request['ad_adgroup_id'],
            'ad_creative_id' => $request['ad_creative_id'],
            'ad_id'          => $request['creative_id'],
            'status'         => false,
            'platform'       => 'linkedin',
            'type'           => $request['creative_type'] ?? 'SPONSORED_CONTENT',
            'social_account_id'  => $this->account->id,
            'ad_campaign_id' => $request['ad_campaign_id'],
            'name'           => $request['name'],
            'call_to_action' => $request['call_to_action'] ?? null,
        ];

        return $this->apiService->success($dataToInsert, ['ad_id' => $request['creative_id']], new Ad);
    }

    private function getHeaders()
    {
        if ($this->tokenIsValid($this->account->expires_at)) {
            $accessToken = $this->account->access_token;
        } else {
            $response = $this->refreshToken($this->account);

            if (!$response['success']) {
                // Must still carry a 'data' key: every caller reads
                // $this->header['data'] unconditionally, so returning the
                // bare ['success'=>false,'error'=>...] shape here used to
                // pass null into ApiService::post()'s array $headers and
                // die with a TypeError instead of reporting the real
                // problem. LinkedIn only issues refresh tokens to apps
                // approved for programmatic refresh tokens, so for most
                // apps this path means "reconnect the account".
                return $response + ['data' => []];
            }

            $accessToken = $response['data'];
        }

        return [
            'success' => true,
            'data' => [
                'Authorization'             => "Bearer $accessToken",
                'Linkedin-Version'          => $this->linkedinVersion,
                'X-Restli-Protocol-Version' => '2.0.0',
                'Content-Type'              => 'application/json',
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
        $response = $this->apiService->post('https://www.linkedin.com/oauth/v2/accessToken', [], [
            'grant_type'    => 'refresh_token',
            'refresh_token' => $account->refresh_token,
            'client_id'     => adminSetting('ads.linkedin.client_id'),
            'client_secret' => adminSetting('ads.linkedin.client_secret'),
        ], 'form');

        $data = $response['data'];

        if ($response['success']) {
            $this->account->access_token = $data['access_token'];
            $this->account->refresh_token = $data['refresh_token'] ?? $account->refresh_token;
            $this->account->expires_at = Carbon::now()->addSeconds($data['expires_in'] ?? 3600);
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

    public function update($platform, $id, $request)
    {
        if ($guard = $this->accountIsUsable()) {
            return $guard;
        }

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

        $creativeResponse = $this->updateCreative($platform, $request, AdAdGroup::find($adGroupResponse['data']['id']));

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

        $response = $this->apiService->post(
            $this->config . 'adAccounts/' . $this->account->platform_account_id . '/adCampaignGroups/' . $campaign->ad_campaign_id,
            array_merge($this->header['data'], ['X-RestLi-Method' => 'PARTIAL_UPDATE']),
            ['patch' => ['$set' => ['name' => $request['name']]]]
        );

        if (!$response['success']) {
            return $this->errorResponse($response['data']['message'] ?? 'Failed to update LinkedIn Campaign Group.');
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
        $targeting = $this->buildTargeting($request);

        $objective = $request['objective'] ?? $adGroup->objective;
        $optimizationGoal = $request['optimization_goal'] ?? $this->defaultOptimizationGoal($objective);
        $costType = $request['bid_type'] ?? $adGroup->bid_type ?? 'CPC';

        $patch = array_filter([
            'name'              => $request['name'],
            'targetingCriteria' => $targeting['targetingCriteria'],
            'runSchedule'       => array_filter([
                'start' => Carbon::parse($request['start_time'])->utc()->getTimestampMs(),
                'end'   => !empty($request['end_time']) ? Carbon::parse($request['end_time'])->utc()->getTimestampMs() : null,
            ]),
        ]);

        $budgetAmount = number_format((float) $request['budget'], 2, '.', '');

        if (($request['budget_mode'] ?? 'daily') === 'daily') {
            $patch['dailyBudget'] = ['currencyCode' => $this->account->currency ?? 'USD', 'amount' => $budgetAmount];
        } else {
            $patch['totalBudget'] = ['currencyCode' => $this->account->currency ?? 'USD', 'amount' => $budgetAmount];
        }

        if (!empty($request['bid_amount'])) {
            $patch['unitCost'] = ['currencyCode' => $this->account->currency ?? 'USD', 'amount' => number_format((float) $request['bid_amount'], 2, '.', '')];
        }

        $response = $this->apiService->post(
            $this->config . 'adAccounts/' . $this->account->platform_account_id . '/adCampaigns/' . $adGroup->ad_adgroup_id,
            array_merge($this->header['data'], ['X-RestLi-Method' => 'PARTIAL_UPDATE']),
            ['patch' => ['$set' => $patch]]
        );

        if (!$response['success']) {
            return $this->errorResponse($response['data']['message'] ?? 'Failed to update LinkedIn Campaign.');
        }

        $dataToInsert = [
            'ad_campaign_id'      => $campaignId,
            'user_id'             => Auth::id(),
            'ad_adgroup_id'       => $adGroup->ad_adgroup_id,
            'social_account_id'       => $this->account->id,
            'name'                => $request['name'],
            'platform'            => $platform,
            'objective'           => $objective,
            'optimization_goal'   => $optimizationGoal,
            'bid_type'            => $costType,
            'bid_price'           => $request['bid_amount'] ?? null,
            'budget_mode'         => $request['budget_mode'] ?? 'daily',
            'budget'              => $request['final_budget'] ?? $request['budget'],
            'schedule_start_time' => $request['start_time'],
            'schedule_end_time'   => $request['end_time'] ?? null,
            'location_ids'        => json_encode($request['countries'] ?? []),
            'age_groups'          => json_encode($targeting['local_selections']),
            'targeting_criteria'  => json_encode($targeting['targetingCriteria']),
        ];

        return $this->apiService->success($dataToInsert, ['ad_adgroup_id' => $adGroup->ad_adgroup_id], new AdAdGroup);
    }

    /**
     * LinkedIn Creatives are immutable once created - see class docblock.
     * The create/edit form locks these fields for LinkedIn accordingly;
     * this just returns the existing local record unchanged.
     */
    private function updateCreative($platform, $request, $adGroup)
    {
        $existingCreative = $adGroup->creatives->first();

        if (!$existingCreative) {
            return $this->storeCreative($platform, $request);
        }

        return $this->successResponse(['ad_creative_id' => $existingCreative->ad_creative_id, 'id' => $existingCreative->id]);
    }

    private function updateAd($platform, $request, $campaign)
    {
        $ad = $campaign->ads->first();

        if (!$ad) {
            return $this->storeAd($platform, $request);
        }

        return $this->successResponse(['ad_id' => $ad->ad_id, 'id' => $ad->id]);
    }

    /**
     * Pause/reactivate without deleting anything, mirroring
     * SnapchatAdService::updateStatus()'s shape - PARTIAL_UPDATEs both the
     * Campaign Group and Campaign so LinkedIn's UI/reporting reflect it at
     * either level.
     */
    public function updateStatus($id, $status)
    {
        if ($guard = $this->accountIsUsable()) {
            return $guard;
        }

        $campaign = AdCampaign::findOrFail($id);
        $adGroup = AdAdGroup::whereAdCampaignId($id)->first();

        $response = $this->apiService->post(
            $this->config . 'adAccounts/' . $this->account->platform_account_id . '/adCampaignGroups/' . $campaign->ad_campaign_id,
            array_merge($this->header['data'], ['X-RestLi-Method' => 'PARTIAL_UPDATE']),
            ['patch' => ['$set' => ['status' => $status]]]
        );

        if (!$response['success']) {
            return $this->errorResponse($response['data']['message'] ?? 'Failed to update LinkedIn Campaign Group status.');
        }

        $isActive = $status === 'ACTIVE';

        if ($adGroup) {
            $this->apiService->post(
                $this->config . 'adAccounts/' . $this->account->platform_account_id . '/adCampaigns/' . $adGroup->ad_adgroup_id,
                array_merge($this->header['data'], ['X-RestLi-Method' => 'PARTIAL_UPDATE']),
                ['patch' => ['$set' => ['status' => $status]]]
            );
            $adGroup->update(['status' => $isActive]);
        }

        $campaign->update(['status' => $isActive]);

        return $this->successResponse(['status' => $status]);
    }

    /**
     * LinkedIn's REST API has no hard-DELETE for these objects - the
     * documented way to remove them is a PARTIAL_UPDATE to
     * status=ARCHIVED. Each archive call is best-effort (logged, not
     * fatal) so a LinkedIn-side hiccup never blocks removing the local
     * record - unlike Facebook/Snapchat's destroy(), which does support
     * hard delete and returns the remote failure directly.
     */
    public function destroy($platform, $id)
    {
        $campaign = AdCampaign::with(['adGroups.creatives.media', 'ads'])->findOrFail($id);

        $adGroup = $campaign->adGroups->first();
        $creative = $adGroup?->creatives->first();
        $media = $creative?->media ?? collect();
        $ad = $campaign->ads->first();

        if ($creative) {
            $this->archiveRemote('creatives', $creative->ad_creative_id);
        }

        if ($adGroup) {
            $this->archiveRemote('adCampaigns', $adGroup->ad_adgroup_id);
        }

        if ($campaign->ad_campaign_id) {
            $this->archiveRemote('adCampaignGroups', $campaign->ad_campaign_id);
        }

        $ad?->delete();

        foreach ($media as $each) {
            $each->delete();
        }

        $creative?->delete();
        $adGroup?->delete();
        $campaign->delete();

        return $this->successResponse(null);
    }

    private function archiveRemote(string $resource, string $id): void
    {
        try {
            $response = $this->apiService->post(
                $this->config . 'adAccounts/' . $this->account->platform_account_id . '/' . $resource . '/' . $id,
                array_merge($this->header['data'], ['X-RestLi-Method' => 'PARTIAL_UPDATE']),
                ['patch' => ['$set' => ['status' => 'ARCHIVED']]]
            );

            if (!$response['success']) {
                Log::warning("LinkedIn {$resource} archive failed.", ['id' => $id, 'response' => $response['data'] ?? null]);
            }
        } catch (\Throwable $e) {
            Log::warning("LinkedIn {$resource} archive threw.", ['id' => $id, 'error' => $e->getMessage()]);
        }
    }
}
