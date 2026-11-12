<?php

namespace App\Services\Crm;

use App\Models\Crm\Lead;
use App\Models\Crm\Setting;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\Connections\ConnectionService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Leads that arrive without anyone typing them (S-10):
 *  - Meta Lead Ads: the Page "leadgen" webhook (same app callback the Comments
 *    webhook already receives) → Graph lead → CRM lead on the Page's workspace.
 *  - Website / form tools: POST to the workspace's secret capture URL.
 * Each submission is stored once (crm_leads.external_lead_id), the person is
 * matched on phone/email first, and automations run like for any new lead.
 */
class LeadCapture
{
    /** Handle every leadgen change in a Page webhook payload. Never throws. */
    public function handleMetaWebhook(array $payload): int
    {
        if (($payload['object'] ?? null) !== 'page') {
            return 0;
        }
        $created = 0;
        foreach ($payload['entry'] ?? [] as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                if (($change['field'] ?? null) !== 'leadgen' || empty($change['value']['leadgen_id'])) {
                    continue;
                }
                try {
                    $created += $this->captureMetaLead($change['value']) ? 1 : 0;
                } catch (\Throwable $e) {
                    Log::warning('CRM Meta lead capture failed.', ['leadgen_id' => $change['value']['leadgen_id'], 'error' => $e->getMessage()]);
                }
            }
        }

        return $created;
    }

    private function captureMetaLead(array $value): bool
    {
        $pageId = (string) ($value['page_id'] ?? '');
        // Every workspace that has this Page connected (normally exactly one: AccountOwnership).
        $page = SocialAccount::where('platform', 'facebook')->where('platform_account_id', $pageId)->first();
        if (! $page) {
            Log::info('CRM Meta lead for an unconnected Page.', ['page_id' => $pageId]);

            return false;
        }
        $token = $this->metaToken($page);
        if (! $token || $this->exists($page->user_id, 'meta:'.$value['leadgen_id'])) {
            return false; // Meta retries webhooks.
        }

        $response = Http::timeout(15)->get('https://graph.facebook.com/v25.0/'.$value['leadgen_id'], [
            'fields' => 'id,created_time,field_data,ad_id,ad_name,adset_name,campaign_id,campaign_name,form_id,platform',
            'access_token' => $token,
        ]);
        if (! $response->successful()) {
            Log::warning('CRM Meta lead fetch failed.', ['leadgen_id' => $value['leadgen_id'], 'status' => $response->status(), 'error' => $response->json('error.message')]);

            return false;
        }

        return $this->storeMetaLead($page, $response->json() + ['id' => $value['leadgen_id']]);
    }

    /** A Meta lead (Graph lead object) → CRM lead on the Page's workspace. Shared by webhook and backfill. */
    public function storeMetaLead(SocialAccount $page, array $lead): bool
    {
        $workspace = User::find($page->user_id);
        if (! $workspace || empty($lead['id']) || $this->exists($workspace->id, 'meta:'.$lead['id'])) {
            return false;
        }
        $answers = collect($lead['field_data'] ?? [])->mapWithKeys(fn ($f) => [mb_strtolower($f['name'] ?? '') => $f['values'][0] ?? null]);

        return $this->store($workspace, $answers->all(), [
            'source' => ($lead['platform'] ?? '') === 'ig' ? 'instagram' : 'facebook', 'campaign' => $lead['campaign_name'] ?? null,
            'external_lead_id' => 'meta:'.$lead['id'],
            'meta' => array_filter(['provider' => 'meta', 'page_id' => $page->platform_account_id, 'form_id' => $lead['form_id'] ?? null,
                'ad_id' => $lead['ad_id'] ?? null, 'ad_name' => $lead['ad_name'] ?? null, 'created_time' => $lead['created_time'] ?? null]),
        ], 'Meta lead');
    }

    /** A TikTok Instant Form lead (webhook entry or CSV row) → CRM lead on the advertiser's workspace. */
    public function storeTiktokLead(SocialAccount $advertiser, string $leadId, array $answers, array $context = []): bool
    {
        $workspace = User::find($advertiser->user_id);
        if (! $workspace || $leadId === '' || $this->exists($workspace->id, 'tiktok:'.$leadId)) {
            return false;
        }

        return $this->store($workspace, array_change_key_case($answers), [
            'source' => 'tiktok', 'campaign' => $context['campaign_name'] ?? null, 'external_lead_id' => 'tiktok:'.$leadId,
            'meta' => array_filter(['provider' => 'tiktok', 'advertiser_id' => $advertiser->platform_account_id] + array_intersect_key($context,
                array_flip(['page_id', 'page_name', 'ad_id', 'ad_name', 'adgroup_name', 'campaign_id', 'create_time']))),
        ], 'TikTok lead');
    }

    private function store(User $workspace, array $answers, array $attributes, string $fallbackName): bool
    {
        $answers = collect($answers)->mapWithKeys(fn ($v, $k) => [str_replace([' ', '-'], '_', mb_strtolower(trim((string) $k))) => is_scalar($v) ? trim((string) $v) : null]);
        $pick = fn (array $keys) => collect($keys)->map(fn ($k) => $answers[$k] ?? null)->first(fn ($v) => filled($v));
        $name = $pick(['full_name', 'name', 'your_name']) ?: trim($pick(['first_name']).' '.$pick(['last_name']));
        $known = ['full_name', 'name', 'your_name', 'first_name', 'last_name', 'phone_number', 'phone', 'mobile', 'mobile_number', 'email', 'email_address'];

        try {
            return $this->asWorkspace($workspace, fn () => (bool) app(LeadService::class)->create($attributes + [
                'name' => $name ?: $fallbackName, 'phone' => $pick(['phone_number', 'phone', 'mobile', 'mobile_number']), 'email' => $pick(['email', 'email_address']),
                'interest' => $answers->except($known)->filter(fn ($v) => filled($v))->map(fn ($v, $k) => str_replace('_', ' ', $k).': '.$v)->implode("\n") ?: null,
                'owner_id' => null,
            ]));
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('CRM lead not saved: plan limit or invalid data.', ['workspace' => $workspace->id, 'external' => $attributes['external_lead_id'], 'error' => $e->getMessage()]);

            return false;
        } catch (\Illuminate\Database\UniqueConstraintViolationException) {
            return false; // the same lead arrived twice at the same moment
        }
    }

    private function exists(int $workspaceId, string $external): bool
    {
        return Lead::forWorkspace($workspaceId)->where('external_lead_id', $external)->exists();
    }

    private function metaToken(SocialAccount $page): ?string
    {
        $connections = app(ConnectionService::class);

        return $connections->tokenFor($page, 'leads') ?? $connections->tokenFor($page, 'ads') ?? $connections->tokenFor($page, 'posting') ?? $page->asset_token ?? $page->access_token;
    }

    /** Website / form capture. Returns the lead id. */
    public function captureForm(string $token, array $data): ?int
    {
        $setting = Setting::withoutGlobalScopes()->where('capture_token', $token)->first();
        $workspace = $setting ? User::find($setting->workspace_id) : null;
        if (! $workspace) {
            return null;
        }
        $external = isset($data['submission_id']) ? 'form:'.mb_substr((string) $data['submission_id'], 0, 90) : null;
        if ($external && Lead::forWorkspace($workspace->id)->where('external_lead_id', $external)->exists()) {
            return Lead::forWorkspace($workspace->id)->where('external_lead_id', $external)->value('id');
        }

        return $this->asWorkspace($workspace, fn () => app(LeadService::class)->create([
            'name' => $data['name'], 'phone' => $data['phone'] ?? null, 'email' => $data['email'] ?? null,
            'source' => $data['source'] ?? 'website', 'campaign' => $data['campaign'] ?? null, 'interest' => $data['message'] ?? null,
            'estimated_value' => $data['value'] ?? null, 'external_lead_id' => $external, 'owner_id' => null,
        ])->id);
    }

    /** Run as the workspace account, like a request inside it (scopes, stamping, automations). */
    private function asWorkspace(User $workspace, callable $callback): mixed
    {
        $previous = Auth::user();
        Auth::setUser($workspace);
        try {
            return $callback();
        } finally {
            $previous ? Auth::setUser($previous) : Auth::forgetUser();
        }
    }
}
