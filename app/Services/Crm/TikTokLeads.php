<?php

namespace App\Services\Crm;

use App\Models\SocialAccount;
use App\Models\SocialConnection;
use App\Services\Connections\Drivers\TikTokDriver;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * TikTok Lead Generation (Instant Form) for the CRM, TikTok API for Business v1.3.
 *
 * Permission: the TikTok for Business consent (ads step). The developer app
 * must have the Lead/Instant Form permission enabled; TikTok returns numeric
 * scopes, so the Hub's "Lead ads" capability is recorded when the LEAD
 * subscription below succeeds.
 *
 *  - Live: POST subscription/subscribe/ {app_id, secret, subscribe_entity: LEAD,
 *    callback_url, subscription_detail: {advertiser_id, access_token}} per
 *    advertiser. TikTok then POSTs {request_id, object: 1, time, entry: [lead]}
 *    where each lead carries id, advertiser_id, page_id, campaign/ad names and
 *    `changes` [{field, value}] (the form answers). Delivery is at-least-once.
 *    The callback URL carries a secret derived from the app secret.
 *  - Backfill: page/get/ (business_type LEAD_GEN) → page/lead/task/ (create,
 *    then poll by task_id) → page/lead/task/download/ (CSV).
 */
class TikTokLeads
{
    /**
     * Always production, like the account-level calls in TiktokAdService (OAuth,
     * advertiser info): advertiser tokens are production tokens, and a sandbox
     * or mistyped ads.tiktok.base_url must not break lead delivery.
     */
    public static function base(): string
    {
        return 'https://business-api.tiktok.com/open_api/v1.3/';
    }

    /** Secret path segment of the webhook URL (rotates with the app secret). */
    public static function callbackToken(): string
    {
        return substr(hash_hmac('sha256', 'crm-tiktok-leads', (string) adminSetting('ads.tiktok.client_secret')), 0, 40);
    }

    public static function callbackUrl(): string
    {
        return route('api.crm.tiktok.leads', self::callbackToken());
    }

    /**
     * Subscribe one advertiser to LEAD events.
     *
     * @return array{ok: bool, subscription_id: ?string, error: ?string}
     */
    public static function subscribe(SocialAccount $advertiser, bool $force = false): array
    {
        if (! $force && ($existing = $advertiser->metadata['crm_lead_subscription_id'] ?? null)) {
            self::markCapability($advertiser); // a reconnect recomputes capabilities from scopes

            return ['ok' => true, 'subscription_id' => $existing, 'error' => null];
        }
        if ($force && ($previous = $advertiser->metadata['crm_lead_subscription_id'] ?? null)) {
            // A reconnect brings a new token: replace the old subscription instead of stacking a second one.
            Http::timeout(15)->asJson()->post(self::base().'subscription/unsubscribe/', [
                'app_id' => (string) adminSetting('ads.tiktok.client_id'), 'secret' => (string) adminSetting('ads.tiktok.client_secret'), 'subscription_id' => (string) $previous,
            ]);
        }
        $response = Http::timeout(20)->asJson()->post(self::base().'subscription/subscribe/', [
            'app_id' => (string) adminSetting('ads.tiktok.client_id'),
            'secret' => (string) adminSetting('ads.tiktok.client_secret'),
            'subscribe_entity' => 'LEAD',
            'callback_url' => self::callbackUrl(),
            'subscription_detail' => ['advertiser_id' => (string) $advertiser->platform_account_id, 'access_token' => (string) $advertiser->access_token, 'lead_source' => 'INSTANT_FORM'],
        ]);
        $id = $response->json('data.subscription_id');
        $ok = $response->successful() && (int) $response->json('code', -1) === 0 && $id;
        $advertiser->forceFill(['metadata' => array_merge($advertiser->metadata ?? [], array_filter([
            'crm_lead_subscription_id' => $ok ? (string) $id : null,
            'crm_lead_subscription_error' => $ok ? null : mb_substr((string) ($response->json('message') ?? $response->body()), 0, 300),
        ], fn ($v) => $v !== null))])->save();
        if ($ok) {
            self::markCapability($advertiser);
        } else {
            Log::warning('TikTok LEAD subscription failed.', ['advertiser' => $advertiser->platform_account_id, 'code' => $response->json('code'), 'message' => $response->json('message')]);
        }

        return ['ok' => (bool) $ok, 'subscription_id' => $ok ? (string) $id : null, 'error' => $ok ? null : ($response->json('message') ?? 'HTTP '.$response->status())];
    }

    /** Webhook body → CRM leads. Returns how many were created. Never throws. */
    public static function handleWebhook(array $payload): int
    {
        if ((string) ($payload['object'] ?? '') !== '1' && strtoupper((string) ($payload['object'] ?? '')) !== 'LEAD') {
            return 0;
        }
        $created = 0;
        foreach ($payload['entry'] ?? [] as $lead) {
            try {
                $advertiser = SocialAccount::where('platform', 'tiktok')->where('platform_account_id', (string) ($lead['advertiser_id'] ?? ''))->first();
                if (! $advertiser) {
                    Log::info('TikTok lead for an unconnected advertiser.', ['advertiser_id' => $lead['advertiser_id'] ?? null]);
                    continue;
                }
                $answers = collect($lead['changes'] ?? [])->mapWithKeys(fn ($c) => [(string) ($c['field'] ?? '') => $c['value'] ?? null])->all();
                $created += app(LeadCapture::class)->storeTiktokLead($advertiser, (string) ($lead['id'] ?? $lead['lead_id'] ?? ''), $answers, $lead) ? 1 : 0;
            } catch (\Throwable $e) {
                Log::warning('TikTok lead webhook entry failed.', ['error' => $e->getMessage()]);
            }
        }

        return $created;
    }

    /**
     * Backfill every Instant Form of the advertiser through the download task.
     *
     * @return array{created: int, forms: int, error: ?string}
     */
    public static function sync(SocialAccount $advertiser, int $pollSeconds = 2, int $polls = 5): array
    {
        $headers = ['Access-Token' => (string) $advertiser->access_token];
        $id = (string) $advertiser->platform_account_id;
        $pages = Http::timeout(20)->withHeaders($headers)->get(self::base().'page/get/', ['advertiser_id' => $id, 'business_type' => 'LEAD_GEN', 'page_size' => 50]);
        if ((int) $pages->json('code', -1) !== 0) {
            return ['created' => 0, 'forms' => 0, 'error' => $pages->json('message') ?? 'HTTP '.$pages->status()];
        }
        $forms = $pages->json('data.list') ?? [];
        $created = 0;
        foreach (array_slice($forms, 0, 20) as $form) {
            $pageId = (string) ($form['page_id'] ?? '');
            if ($pageId === '') {
                continue;
            }
            $task = Http::timeout(20)->withHeaders($headers)->asJson()->post(self::base().'page/lead/task/', ['advertiser_id' => $id, 'page_id' => $pageId]);
            $taskId = $task->json('data.task_id');
            $status = $task->json('data.status');
            for ($i = 0; $taskId && $status !== 'SUCCEED' && $status !== 'FAILED' && $i < $polls; $i++) {
                sleep($pollSeconds);
                $status = Http::timeout(20)->withHeaders($headers)->asJson()->post(self::base().'page/lead/task/', ['advertiser_id' => $id, 'task_id' => $taskId])->json('data.status');
            }
            if ($status !== 'SUCCEED') {
                Log::info('TikTok lead task not ready; the next sync retries.', ['advertiser' => $id, 'page_id' => $pageId, 'status' => $status]);
                continue;
            }
            $file = Http::timeout(60)->withHeaders($headers)->get(self::base().'page/lead/task/download/', ['advertiser_id' => $id, 'task_id' => $taskId]);
            if (! $file->successful() || str_starts_with((string) $file->header('Content-Type'), 'application/json') || str_starts_with($file->body(), 'PK')) {
                Log::warning('TikTok lead download not usable (error or zip).', ['advertiser' => $id, 'page_id' => $pageId]);
                continue;
            }
            foreach (self::rows($file->body()) as $row) {
                $leadId = (string) collect($row)->first(fn ($v, $k) => preg_match('/^lead[ _]?id$/i', (string) $k)) ?? '';
                $context = ['page_id' => $pageId, 'page_name' => $form['title'] ?? $form['name'] ?? null,
                    'campaign_name' => collect($row)->first(fn ($v, $k) => preg_match('/^campaign[ _]?name$/i', (string) $k)),
                    'ad_name' => collect($row)->first(fn ($v, $k) => preg_match('/^ad[ _]?name$/i', (string) $k))];
                $answers = collect($row)->reject(fn ($v, $k) => preg_match('/^(lead[ _]?id|campaign|ad[ _]?(group)?|adgroup|advertiser|page|created|create|form)/i', (string) $k))->all();
                $created += app(LeadCapture::class)->storeTiktokLead($advertiser, $leadId, $answers, $context) ? 1 : 0;
            }
        }
        $advertiser->forceFill(['metadata' => array_merge($advertiser->metadata ?? [], ['leads_synced_at' => now()->timestamp])])->save();

        return ['created' => $created, 'forms' => count($forms), 'error' => null];
    }

    /** CSV text → rows keyed by header. */
    private static function rows(string $csv): array
    {
        $csv = preg_replace('/^\xEF\xBB\xBF/', '', $csv);
        $lines = array_values(array_filter(preg_split('/\r\n|\n|\r/', $csv), fn ($l) => trim($l) !== ''));
        if (count($lines) < 2) {
            return [];
        }
        $headers = array_map('trim', str_getcsv(array_shift($lines)));
        $rows = [];
        foreach (array_slice($lines, 0, 5000) as $line) {
            $values = str_getcsv($line);
            if (count($values) === count($headers)) {
                $rows[] = array_combine($headers, $values);
            }
        }

        return $rows;
    }

    /** Show "Lead ads" as connected on the advertiser's TikTok for Business connection. */
    private static function markCapability(SocialAccount $advertiser): void
    {
        $connection = $advertiser->social_connection_id ? SocialConnection::find($advertiser->social_connection_id)
            : SocialConnection::where('user_id', $advertiser->user_id)->where('platform', 'tiktok')->where('step', TikTokDriver::BUSINESS)->latest('id')->first();
        if ($connection && ! in_array('leads', $connection->capabilities ?? [], true)) {
            $connection->forceFill(['capabilities' => array_values(array_unique(array_merge($connection->capabilities ?? [], ['leads'])))])->save();
        }
    }
}
