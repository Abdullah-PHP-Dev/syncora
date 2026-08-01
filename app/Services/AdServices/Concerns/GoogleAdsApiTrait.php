<?php

namespace App\Services\AdServices\Concerns;

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
        $clientId = adminSetting('ads.google.client_id');

        $url = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
            'client_id'     => $clientId,
            'redirect_uri'  => $this->getCallbackUrl(),
            'response_type' => 'code',
            'access_type'   => 'offline',
            'prompt'        => 'consent',
            'state'         => $state,
            'scope'         => 'https://www.googleapis.com/auth/adwords',
        ]);

        return Redirect::away($url);
    }

    private function getCallbackUrl()
    {
        return config('services.app_url') . '/admin/social/auth/google/callback';
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
