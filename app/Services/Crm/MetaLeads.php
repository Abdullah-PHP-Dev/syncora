<?php

namespace App\Services\Crm;

use App\Models\SocialAccount;
use App\Services\PostServices\MetaPostService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Meta Lead Ads (instant forms) for the CRM.
 *
 * Permission: `leads_retrieval`, requested in the Facebook consent
 * (SocialAuthService::facebookPermissionParams) and shown as the "Lead ads"
 * capability in the Connection Hub. Delivery: the Page's "leadgen" webhook
 * field. A Page's POST /subscribed_apps REPLACES its field list, so every
 * caller (posts + Messenger) must send the same list: pageFields().
 * Backfill: GET /{page}/leadgen_forms → /{form}/leads, for missed webhooks.
 */
class MetaLeads
{
    private const GRAPH = 'https://graph.facebook.com/v25.0/';

    /** The Page's granted permissions include lead retrieval. */
    public static function granted(SocialAccount $page): bool
    {
        $scopes = array_merge((array) ($page->scopes ?? []), (array) ($page->connection?->granted_scopes ?? []));

        return in_array('leads_retrieval', $scopes, true);
    }

    /** The one webhook field list for a Page: everything the app handles, plus leadgen when allowed. */
    public static function pageFields(SocialAccount $page): string
    {
        return MetaPostService::PAGE_WEBHOOK_FIELDS.(self::granted($page) ? ',leadgen' : '');
    }

    /**
     * Subscribe a Page with the full list. If Meta refuses "leadgen" (app not
     * approved yet), retry without it so feed and Messenger keep working.
     *
     * @return array{ok: bool, leadgen: bool, error: ?string}
     */
    public static function subscribe(SocialAccount $page, ?string $token = null): array
    {
        $token ??= $page->asset_token ?: $page->access_token;
        $fields = self::pageFields($page);
        $call = fn (string $list) => Http::timeout(15)->asForm()->post(self::GRAPH.$page->platform_account_id.'/subscribed_apps', ['subscribed_fields' => $list, 'access_token' => $token]);

        $response = $call($fields);
        $leadgen = str_contains($fields, 'leadgen');
        if (! ($response->successful() && $response->json('success')) && $leadgen) {
            Log::info('Meta refused the leadgen field; subscribing without it.', ['page' => $page->platform_account_id, 'error' => $response->json('error.message')]);
            $response = $call(MetaPostService::PAGE_WEBHOOK_FIELDS);
            $leadgen = false;
        }
        $ok = $response->successful() && (bool) $response->json('success');
        $page->forceFill(['metadata' => array_merge($page->metadata ?? [], ['webhook_subscriptions' => $ok ?: ($page->metadata['webhook_subscriptions'] ?? false), 'leadgen_subscribed' => $ok && $leadgen])])->save();

        return ['ok' => $ok, 'leadgen' => $ok && $leadgen, 'error' => $ok ? null : ($response->json('error.message') ?? $response->body())];
    }

    /**
     * Backfill: leads from every instant form on the Page since $since (or
     * the last sync). Each goes through LeadCapture, which skips ones it has.
     *
     * @return array{created: int, forms: int, error: ?string}
     */
    public static function sync(SocialAccount $page, ?int $since = null): array
    {
        $token = $page->asset_token ?: $page->access_token;
        if (! $token || ! self::granted($page)) {
            return ['created' => 0, 'forms' => 0, 'error' => $token ? 'leads_retrieval not granted' : 'no Page token (reconnect the Page)'];
        }
        $since ??= (int) ($page->metadata['leads_synced_at'] ?? now()->subDays(30)->timestamp);
        $forms = Http::timeout(20)->get(self::GRAPH.$page->platform_account_id.'/leadgen_forms', ['fields' => 'id,name,status', 'limit' => 100, 'access_token' => $token]);
        if (! $forms->successful()) {
            return ['created' => 0, 'forms' => 0, 'error' => $forms->json('error.message') ?? 'HTTP '.$forms->status()];
        }

        $created = 0;
        $capture = app(LeadCapture::class);
        foreach ($forms->json('data') ?? [] as $form) {
            $url = self::GRAPH.$form['id'].'/leads';
            $params = [
                'fields' => 'id,created_time,field_data,ad_id,ad_name,adset_name,campaign_id,campaign_name,form_id,platform',
                'limit' => 100, 'access_token' => $token,
                'filtering' => json_encode([['field' => 'time_created', 'operator' => 'GREATER_THAN', 'value' => $since]]),
            ];
            for ($pages = 0; $url && $pages < 20; $pages++) {
                $response = Http::timeout(20)->get($url, $params);
                if (! $response->successful()) {
                    Log::warning('Meta lead backfill failed for a form.', ['form' => $form['id'], 'error' => $response->json('error.message')]);
                    break;
                }
                foreach ($response->json('data') ?? [] as $lead) {
                    $created += $capture->storeMetaLead($page, $lead + ['form_id' => $form['id']]) ? 1 : 0;
                }
                $url = $response->json('paging.next');
                $params = []; // "next" already carries every parameter
            }
        }
        $page->forceFill(['metadata' => array_merge($page->metadata ?? [], ['leads_synced_at' => now()->timestamp])])->save();

        return ['created' => $created, 'forms' => count($forms->json('data') ?? []), 'error' => null];
    }
}
