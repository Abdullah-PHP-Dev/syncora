<?php

namespace App\Services\AdServices\Concerns;

use App\Models\Admin\AdAccount;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;
use Carbon\Carbon;

/**
 * Shared Google Ads REST plumbing (OAuth, token refresh, mutate/search
 * helpers) used by both GoogleAdService (Search) and YoutubeAdService
 * (Demand Gen) - they're not really different platforms at the API/auth
 * level, just different campaign types on the same Google Ads customer, so
 * duplicating this HTTP/token logic across two classes would just create
 * two copies that can silently drift out of sync.
 */
trait GoogleAdsApiTrait
{
    protected $account, $config, $apiService, $header;

    public function redirect($platform, $state)
    {
        dd('ok');
        $clientId = adminSetting('ads.google.client_id');

        $url = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
            'client_id'     => $clientId,
            'redirect_uri'  => $this->getCallbackUrl($platform),
            'response_type' => 'code',
            // offline + consent are what actually mint a refresh_token.
            // Google only returns one on the FIRST consent for a given
            // client/user pair unless prompt=consent forces the screen
            // again, and without it a reconnect silently yields an
            // access-token-only response that dies 60 minutes later.
            'access_type'   => 'offline',
            'prompt'        => 'consent',
            'state'         => $state,
            'scope'         => 'https://www.googleapis.com/auth/adwords',
        ]);
        dd([
            'client_id'     => $clientId,
            'redirect_uri'  => $this->getCallbackUrl($platform),
            'response_type' => 'code',
            // offline + consent are what actually mint a refresh_token.
            // Google only returns one on the FIRST consent for a given
            // client/user pair unless prompt=consent forces the screen
            // again, and without it a reconnect silently yields an
            // access-token-only response that dies 60 minutes later.
            'access_type'   => 'offline',
            'prompt'        => 'consent',
            'state'         => $state,
            'scope'         => 'https://www.googleapis.com/auth/adwords',
        ]);
        return Redirect::away($url);
    }

    /**
     * Must be byte-identical between the authorize step and the token
     * exchange, and must be registered under the OAuth client's
     * "Authorized redirect URIs" in the Cloud Console - Google rejects any
     * mismatch with redirect_uri_mismatch.
     *
     * Built from the named route rather than a hardcoded host so the same
     * code works on socialeaz.com and labs.socialeaz.com (both are
     * registered on the client) instead of always claiming the production
     * host. $platform keeps google/youtube distinct: YouTube Demand Gen
     * runs on the same Google Ads customer, but it is its own platform key
     * with its own registered callback.
     */
    private function getCallbackUrl(string $platform = 'google'): string
    {
        return route('admin.ads.platform.callback', $platform);
    }

    /**
     * Google/YouTube Ads had no callback() at all - SocialAdManagerService
     * dispatches to it after Google redirects back, so connecting an
     * account died with "Call to undefined method". Mirrors
     * LinkedinAdService::callback(): exchange the code, enumerate every
     * customer the member can reach, and persist one AdAccount row per
     * usable customer.
     */
    public function callback($platform, $state = null)
    {
        $platform = in_array($platform, ['google', 'youtube'], true) ? $platform : 'google';

        // Google reports a refused/cancelled consent by redirecting back
        // with ?error=access_denied rather than a code.
        if (request()->filled('error')) {
            Log::warning('Google Ads OAuth authorization refused.', [
                'error'             => request()->input('error'),
                'error_description' => request()->input('error_description'),
            ]);

            return redirect()->route('admin.ads.dashboard')->with('error', 'Google refused the ads authorization: ' . (request()->input('error_description') ?: request()->input('error')));
        }

        $code = request()->input('code');

        if (!$code) {
            return redirect()->route('admin.ads.dashboard')->with('error', 'Google did not return an authorization code.');
        }

        $tokenEndpoint = adminSetting('ads.google.access_token') ?: 'https://oauth2.googleapis.com/token';

        $tokenResponse = $this->apiService->post($tokenEndpoint, [], [
            'code'          => $code,
            'client_id'     => adminSetting('ads.google.client_id'),
            'client_secret' => adminSetting('ads.google.client_secret'),
            'redirect_uri'  => $this->getCallbackUrl($platform),
            'grant_type'    => 'authorization_code',
        ], 'form');

        if (!$tokenResponse['success']) {
            Log::warning('Google Ads token exchange failed.', [
                'status' => $tokenResponse['status'] ?? null,
                'body'   => $tokenResponse['body'] ?? ($tokenResponse['error'] ?? null),
            ]);

            return redirect()->route('admin.ads.dashboard')->with('error', $tokenResponse['data']['error_description'] ?? 'Failed to exchange code for a Google access token.');
        }

        $token = $tokenResponse['data'];
        $accessToken = $token['access_token'] ?? null;

        if (!$accessToken) {
            return redirect()->route('admin.ads.dashboard')->with('error', 'Google returned no access token.');
        }

        // A missing refresh_token means the account can only work for the
        // next hour - worth failing loudly rather than saving a row that
        // silently stops refreshing. Happens when the user has previously
        // consented and prompt=consent was not honoured.
        if (empty($token['refresh_token'])) {
            Log::warning('Google Ads token exchange returned no refresh_token.', ['platform' => $platform]);
        }

        $developerToken = adminSetting('ads.google.developer_token');

        if (empty($developerToken)) {
            return redirect()->route('admin.ads.dashboard')->with('error', 'No Google Ads developer token is configured (ads.google.developer_token). Get one from the Google Ads API Center on your manager account.');
        }

        $headers = [
            'Authorization'   => 'Bearer ' . $accessToken,
            'developer-token' => $developerToken,
            'Content-Type'    => 'application/json',
        ];

        $loginCustomerId = adminSetting('ads.google.login_customer_id');

        if (!empty($loginCustomerId)) {
            $headers['login-customer-id'] = str_replace('-', '', $loginCustomerId);
        }

        $base = adminSetting('ads.google.base_url') ?: 'https://googleads.googleapis.com/v21/';

        // Every customer this member has access to, as resource names
        // ("customers/1234567890") - the Google Ads equivalent of
        // LinkedIn's adAccountUsers?q=authenticatedUser walk.
        $listResponse = $this->apiService->get($base . 'customers:listAccessibleCustomers', $headers);

        if (!$listResponse['success']) {
            // 401/403 here almost always means the developer token is not
            // approved for this account, or the token was minted without
            // the adwords scope. Google names the reason in the body.
            Log::warning('Google listAccessibleCustomers failed.', [
                'status' => $listResponse['status'] ?? null,
                'body'   => $listResponse['body'] ?? ($listResponse['error'] ?? null),
            ]);

            return redirect()->route('admin.ads.dashboard')->with('error', $listResponse['data']['error']['message'] ?? 'Connected, but could not list your Google Ads accounts.');
        }

        $expiresAt = Carbon::now()->addSeconds($token['expires_in'] ?? 3600);
        $connected = 0;
        $skippedManagers = 0;

        foreach ($listResponse['data']['resourceNames'] ?? [] as $resourceName) {
            if (!preg_match('#customers/(\d+)#', $resourceName, $matches)) {
                continue;
            }

            $customerId = $matches[1];

            // descriptive_name/currency_code aren't in listAccessibleCustomers -
            // only a GAQL query against the customer itself returns them.
            // Best-effort: a customer under a manager needs
            // login-customer-id to be queryable, so when this fails the row
            // is still saved with a fallback name rather than dropped.
            $detail = [];
            $detailResponse = $this->apiService->post(
                $base . 'customers/' . $customerId . '/googleAds:search',
                $headers,
                ['query' => 'SELECT customer.id, customer.descriptive_name, customer.currency_code, customer.manager, customer.test_account FROM customer LIMIT 1']
            );

            if ($detailResponse['success']) {
                $detail = $detailResponse['data']['results'][0]['customer'] ?? [];
            } else {
                Log::info('Google Ads customer detail lookup failed; saving with defaults.', [
                    'customer_id' => $customerId,
                    'body'        => $detailResponse['body'] ?? null,
                ]);
            }

            // Manager (MCC) accounts cannot host campaigns themselves -
            // they're the thing you'd put in login_customer_id instead.
            if (!empty($detail['manager'])) {
                $skippedManagers++;
                continue;
            }

            $this->apiService->success(
                [
                    'platform'      => $platform,
                    'user_id'       => Auth::id(),
                    'name'          => $detail['descriptiveName'] ?? ('Google Ads ' . $customerId),
                    'currency'      => $detail['currencyCode'] ?? null,
                    'ad_account_id' => $customerId,
                    'access_token'  => $accessToken,
                    'refresh_token' => $token['refresh_token'] ?? null,
                    'expires_at'    => $expiresAt,
                    'status'        => 'active',
                ],
                [
                    'platform'      => $platform,
                    'ad_account_id' => $customerId,
                    'user_id'       => Auth::id(),
                ],
                new AdAccount
            );

            $connected++;
        }

        if ($connected === 0) {
            $hint = $skippedManagers > 0
                ? " Only manager (MCC) accounts were returned - put the manager ID in the ads.google.login_customer_id setting and connect a client account under it."
                : ' Make sure the Google Account you authorized has access to a Google Ads account.';

            return redirect()->route('admin.ads.dashboard')->with('error', 'Connected to Google, but no usable Google Ads account was found.' . $hint);
        }

        return redirect()->route('admin.ads.dashboard')->with('success', "Connected {$connected} Google Ads account(s).");
    }

    /**
     * The numeric Google Ads customer ID, without the dashes it's usually
     * displayed with (eg. "123-456-7890" in the UI, "1234567890" in URLs).
     */
    protected function customerId(): string
    {
        return str_replace('-', '', $this->account->ad_account_id);
    }

    protected function getHeaders()
    {
        if ($this->tokenIsValid($this->account->expires_at)) {
            $accessToken = $this->account->access_token;
        } else {
            $response = $this->refreshToken($this->account);

            if (!$response['success']) {
                return [];
            }

            $accessToken = $response['data'];
        }

        $headers = [
            'Authorization'   => "Bearer $accessToken",
            'developer-token' => adminSetting('ads.google.developer_token'),
            'Content-Type'    => 'application/json',
        ];

        $loginCustomerId = adminSetting('ads.google.login_customer_id');

        if (!empty($loginCustomerId)) {
            $headers['login-customer-id'] = str_replace('-', '', $loginCustomerId);
        }

        return $headers;
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
        $endpoint = adminSetting('ads.google.access_token');

        $response = $this->apiService->post($endpoint, [], [
            'client_id'     => adminSetting('ads.google.client_id'),
            'client_secret' => adminSetting('ads.google.client_secret'),
            'refresh_token' => $account->refresh_token,
            'grant_type'    => 'refresh_token',
        ], 'form');

        if ($response['success']) {
            $account->access_token = $response['data']['access_token'];
            $account->expires_at = Carbon::now()->addSeconds($response['data']['expires_in'] ?? 3600);
            $account->save();
            $account->refresh();

            return $this->successResponse($account->access_token);
        }

        return $this->errorResponse($response['data']['error_description'] ?? 'Refresh Token Error');
    }

    protected function errorResponse($error)
    {
        return ['success' => false, 'error' => $error];
    }

    protected function successResponse($data)
    {
        return ['success' => true, 'data' => $data];
    }

    /**
     * Single-operation mutate helper - Google's REST API signals failure via
     * a normal HTTP 4xx status with a standard {"error":{"message":...}}
     * body (unlike TikTok/Snapchat's "200 but internal error" quirk), so
     * ApiService's own success/fail detection already covers it; this just
     * unwraps results[0].
     */
    protected function mutate($endpoint, array $payload)
    {
        $response = $this->apiService->post($endpoint, $this->header, $payload);

        if (!$response['success']) {
            return $this->errorResponse($response['data']['error']['message'] ?? 'Google Ads API request failed.');
        }

        return $this->successResponse($response['data']['results'][0] ?? []);
    }

    /**
     * Multi-operation mutate helper for endpoints where several create
     * operations are sent in one request (eg. one call per keyword or per
     * targeting criterion, rather than one call per item).
     */
    protected function mutateMultiple($endpoint, array $operations)
    {
        $response = $this->apiService->post($endpoint, $this->header, ['operations' => $operations]);

        if (!$response['success']) {
            return $this->errorResponse($response['data']['error']['message'] ?? 'Google Ads API request failed.');
        }

        return $this->successResponse($response['data']['results'] ?? []);
    }

    /**
     * GAQL search - used only for read-only lookups (geo/language constant
     * resolution), never for mutating anything.
     */
    protected function search(string $query)
    {
        $endpoint = $this->config . 'customers/' . $this->customerId() . '/googleAds:search';

        $response = $this->apiService->post($endpoint, $this->header, ['query' => $query]);

        if (!$response['success']) {
            return $this->errorResponse($response['data']['error']['message'] ?? 'Google Ads API search failed.');
        }

        return $this->successResponse($response['data']['results'] ?? []);
    }

    /**
     * Google's geo_target_constant IDs are Google's own stable criteria IDs
     * (not ISO country codes), so they're resolved dynamically via GAQL
     * rather than a hardcoded ISO->ID table that would silently go stale.
     * Matches by country name against geo_target_constant.canonical_name
     * for target_type=Country.
     */
    protected function resolveGeoTargetConstants(array $countryIds): array
    {
        $countryNames = \App\Models\Country::whereIn('id', $countryIds)
            ->pluck('name')
            ->map(fn($name) => strtolower($name))
            ->toArray();

        if (empty($countryNames)) {
            return [];
        }

        $result = $this->search(
            "SELECT geo_target_constant.canonical_name, geo_target_constant.resource_name " .
            "FROM geo_target_constant WHERE geo_target_constant.target_type = 'Country' AND geo_target_constant.status = 'ENABLED'"
        );

        if (!$result['success']) {
            return [];
        }

        return collect($result['data'])
            ->filter(fn($row) => in_array(strtolower($row['geoTargetConstant']['canonicalName'] ?? ''), $countryNames, true))
            ->pluck('geoTargetConstant.resourceName')
            ->filter()
            ->values()
            ->all();
    }

    /**
     * language_constant.code uses the same ISO 639-1 codes (en, ar, es...)
     * this app's languages[] checkboxes already submit, so this is a direct
     * match rather than a name-based lookup.
     */
    protected function resolveLanguageConstants(array $languageCodes): array
    {
        if (empty($languageCodes)) {
            return [];
        }

        $result = $this->search(
            'SELECT language_constant.code, language_constant.resource_name FROM language_constant'
        );

        if (!$result['success']) {
            return [];
        }

        $codes = array_map('strtolower', $languageCodes);

        return collect($result['data'])
            ->filter(fn($row) => in_array(strtolower($row['languageConstant']['code'] ?? ''), $codes, true))
            ->pluck('languageConstant.resourceName')
            ->filter()
            ->values()
            ->all();
    }

    protected function parseTextList($value, int $maxLength): array
    {
        return collect(preg_split('/\r\n|\r|\n/', $value ?? ''))
            ->map(fn($line) => trim($line))
            ->filter()
            ->map(fn($line) => mb_substr($line, 0, $maxLength))
            ->values()
            ->all();
    }

    /**
     * Location/language (CampaignCriterion) + age/gender (AdGroupCriterion)
     * targeting - identical shape for Search and Demand Gen, both being
     * plain criterion resources on the same underlying customer.
     */
    protected function storeTargeting($request)
    {
        $locationIds = $this->resolveGeoTargetConstants($request['countries'] ?? []);

        if (empty($locationIds)) {
            return $this->errorResponse('Could not resolve the selected countries to Google geo target constants. Please double-check the Countries selection.');
        }

        $languageIds = $this->resolveLanguageConstants($request['languages'] ?? []);

        $campaignEndpoint = $this->config . 'customers/' . $this->customerId() . '/campaignCriteria:mutate';
        $campaignOperations = [];

        foreach ($locationIds as $geoResource) {
            $campaignOperations[] = ['create' => [
                'campaign' => $request['campaign_resource'],
                'location' => ['geoTargetConstant' => $geoResource],
            ]];
        }

        foreach ($languageIds as $langResource) {
            $campaignOperations[] = ['create' => [
                'campaign' => $request['campaign_resource'],
                'language' => ['languageConstant' => $langResource],
            ]];
        }

        $result = $this->mutateMultiple($campaignEndpoint, $campaignOperations);

        if (!$result['success']) {
            return $result;
        }

        $demographicOperations = [];

        if (!empty($request['gender']) && $request['gender'] !== 'both') {
            $demographicOperations[] = ['create' => [
                'adGroup' => $request['adgroup_resource'],
                'gender'  => ['type' => strtoupper($request['gender']) === 'MALE' ? 'MALE' : 'FEMALE'],
            ]];
        }

        foreach ($request['age_range'] ?? [] as $ageRange) {
            $demographicOperations[] = ['create' => [
                'adGroup'  => $request['adgroup_resource'],
                'ageRange' => ['type' => $ageRange],
            ]];
        }

        if (empty($demographicOperations)) {
            return $this->successResponse(null);
        }

        $adGroupCriteriaEndpoint = $this->config . 'customers/' . $this->customerId() . '/adGroupCriteria:mutate';

        return $this->mutateMultiple($adGroupCriteriaEndpoint, $demographicOperations);
    }

    /**
     * Campaign name + start/end date update, shared since it's identical
     * between Search and Demand Gen campaigns - only the ad-level update
     * that follows differs per campaign type.
     */
    protected function updateCampaignNameAndDates($campaign, $request)
    {
        $campaignUpdate = [
            'name'      => $request['name'],
            'startDate' => Carbon::parse($request['start_time'])->format('Y-m-d'),
            'endDate'   => Carbon::parse($request['end_time'])->format('Y-m-d'),
        ];

        $result = $this->mutate(
            $this->config . 'customers/' . $this->customerId() . '/campaigns:mutate',
            ['operations' => [[
                'update'     => array_merge(['resourceName' => $campaign->ad_campaign_id], $campaignUpdate),
                'updateMask' => 'name,start_date,end_date',
            ]]]
        );

        if (!$result['success']) {
            return $result;
        }

        $campaign->update([
            'name'       => $request['name'],
            'start_time' => $request['start_time'],
            'end_time'   => $request['end_time'],
        ]);

        return $this->successResponse(null);
    }

    /**
     * Pause/reactivate. Google's campaign.status accepts ENABLED/PAUSED/
     * REMOVED via the same update-mutate pattern used everywhere else here.
     */
    public function updateStatus($id, $status)
    {
        $campaign = \App\Models\Admin\AdCampaign::findOrFail($id);
        $googleStatus = $status === 'ACTIVE' ? 'ENABLED' : 'PAUSED';

        $result = $this->mutate(
            $this->config . 'customers/' . $this->customerId() . '/campaigns:mutate',
            ['operations' => [[
                'update'     => ['resourceName' => $campaign->ad_campaign_id, 'status' => $googleStatus],
                'updateMask' => 'status',
            ]]]
        );

        if (!$result['success']) {
            return $result;
        }

        $campaign->update(['status' => $status === 'ACTIVE']);

        return $this->successResponse(['status' => $status]);
    }

    /**
     * Google Ads has no hard delete for campaigns - the real mechanism
     * (matching what the Ads UI itself does) is an update-mutate setting
     * status to REMOVED, which is permanent and irreversible via the API
     * but keeps the resource's history/reporting intact on Google's side.
     */
    public function destroy($platform, $id)
    {
        $campaign = \App\Models\Admin\AdCampaign::findOrFail($id);

        $result = $this->mutate(
            $this->config . 'customers/' . $this->customerId() . '/campaigns:mutate',
            ['operations' => [[
                'update'     => ['resourceName' => $campaign->ad_campaign_id, 'status' => 'REMOVED'],
                'updateMask' => 'status',
            ]]]
        );

        if (!$result['success']) {
            return $result;
        }

        \App\Models\Admin\AdAdGroup::whereAdCampaignId($id)->delete();
        \App\Models\Admin\AdCreative::whereAdCampaignId($id)->delete();
        \App\Models\Admin\Ad::whereAdCampaignId($id)->delete();
        $campaign->delete();

        return $this->successResponse(null);
    }
}
