<?php

namespace App\Services\AdServices;

use App\Models\Admin\Ad;
use App\Models\Admin\AdAdGroup;
use App\Models\Admin\AdCampaign;
use App\Models\Admin\AdCreative;
use App\Support\AdAccountSelection;
use App\Support\Connections\OpenAiAds;
use Carbon\Carbon;
use Illuminate\Http\Client\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * ChatGPT Ads (OpenAI Ads API, https://developers.openai.com/ads).
 *
 * Connecting is an API key on the Connection Hub (OpenAiDriver), not an
 * OAuth redirect. Campaigns follow the API's mandatory hierarchy:
 * campaign (lifetime budget) > ad group (bidding) > ad (chat_card
 * creative: title, body, target URL, uploaded image). New ads go through
 * OpenAI's review (review_status in_review -> approved / rejected).
 * Money is sent in micros (1 unit = 1,000,000); reporting returns major
 * units.
 */
class OpenAiAdService
{
    protected $account;

    public function __construct()
    {
        $this->account = Auth::check() ? AdAccountSelection::resolve((int) Auth::id(), 'openai') : null;
    }

    /** No OAuth: send the user to the key form on the Hub's ChatGPT Ads card. */
    public function redirect($platform, $state)
    {
        return redirect()->to(route('admin.connections.index') . '#openai');
    }

    public function callback($platform, $state = null)
    {
        return redirect()->to(route('admin.connections.index') . '#openai');
    }

    // ------------------------------------------------------------------
    // Create
    // ------------------------------------------------------------------

    /**
     * Upload the image, then campaign > ad group > ad. A failure after the
     * campaign exists archives it on OpenAI so nothing half-built runs.
     */
    public function store($platform, $request)
    {
        if ($error = $this->accountError()) {
            return $this->fail($error);
        }

        $status = ($request['launch'] ?? 'paused') === 'active' ? 'active' : 'paused';

        $fileId = $this->uploadImage($request['image'] ?? null, $request['image_url'] ?? null);
        if (is_array($fileId)) {
            return $this->fail($fileId['error']);
        }

        $campaignBody = array_filter([
            'name' => $request['name'],
            'status' => $status,
            'start_time' => ! empty($request['start_time']) ? Carbon::parse($request['start_time'])->startOfDay()->timestamp : null,
            'end_time' => ! empty($request['end_time']) ? Carbon::parse($request['end_time'])->endOfDay()->timestamp : null,
            'budget' => ['lifetime_spend_limit_micros' => self::micros($request['budget'])],
            'bidding_type' => $request['bidding_type'] ?? 'clicks',
        ], fn ($v) => $v !== null);

        $campaign = $this->api()->post('/campaigns', $campaignBody);
        if (! $campaign->successful() || ! $campaign->json('id')) {
            return $this->fail(OpenAiAds::errorMessage($campaign, 'OpenAI did not create the campaign.'));
        }
        $campaignId = $campaign->json('id');

        $adGroup = $this->api()->post('/ad_groups', [
            'campaign_id' => $campaignId,
            'name' => $request['ad_group_name'] ?? ($request['name'] . ' - Ad group'),
            'status' => 'active',
            'bidding_config' => [
                'billing_event_type' => $request['billing_event'] ?? 'click',
                'max_bid_micros' => self::micros($request['max_bid']),
            ],
        ]);
        if (! $adGroup->successful() || ! $adGroup->json('id')) {
            return $this->rollback($campaignId, OpenAiAds::errorMessage($adGroup, 'OpenAI did not create the ad group.'));
        }

        $ad = $this->api()->post('/ads', [
            'ad_group_id' => $adGroup->json('id'),
            'name' => $request['ad_name'] ?? Str::limit($request['title'], 60, ''),
            'status' => 'active',
            'creative' => [
                'type' => 'chat_card',
                'title' => $request['title'],
                'body' => $request['body'],
                'target_url' => $request['target_link'],
                'file_id' => $fileId,
            ],
        ]);
        if (! $ad->successful() || ! $ad->json('id')) {
            return $this->rollback($campaignId, OpenAiAds::errorMessage($ad, 'OpenAI did not create the ad.'));
        }

        $local = $this->saveLocally($campaign, $adGroup, $ad, $request);

        return response()->json([
            'success' => true,
            'message' => 'Campaign created. OpenAI reviews new ads before they run (status: ' . str_replace('_', ' ', $ad->json('review_status') ?? 'in review') . ').',
            'campaign_id' => $local->id,
            'redirect_url' => route('admin.ads.campaigns.index', ['platform' => 'openai']),
        ]);
    }

    // ------------------------------------------------------------------
    // Manage
    // ------------------------------------------------------------------

    /** ACTIVE / PAUSED from the campaigns page. */
    public function updateStatus($id, $status)
    {
        if ($error = $this->accountError()) {
            return $this->fail($error);
        }

        $campaign = $this->localCampaign($id);
        if (! $campaign) {
            return $this->fail('Campaign not found.', 404);
        }

        $action = strtoupper($status) === 'ACTIVE' ? 'activate' : 'pause';
        $response = $this->api()->post("/campaigns/{$campaign->ad_campaign_id}/{$action}");

        if (! $response->successful()) {
            return $this->fail(OpenAiAds::errorMessage($response, 'OpenAI did not change the campaign status.'));
        }

        $campaign->update(['status' => $action === 'activate' ? 'ACTIVE' : 'PAUSED']);

        return $this->done($action === 'activate' ? 'Campaign activated.' : 'Campaign paused.');
    }

    /** Archive on OpenAI (the API has no delete), then remove it here. */
    public function destroy($platform, $id)
    {
        if ($error = $this->accountError()) {
            return $this->fail($error);
        }

        $campaign = $this->localCampaign($id);
        if (! $campaign) {
            return $this->fail('Campaign not found.', 404);
        }

        $response = $this->api()->post("/campaigns/{$campaign->ad_campaign_id}/archive");

        if (! $response->successful() && $response->status() !== 404) {
            return $this->fail(OpenAiAds::errorMessage($response, 'OpenAI did not archive the campaign.'));
        }

        Ad::where('ad_campaign_id', $campaign->id)->delete();
        AdCreative::where('ad_campaign_id', $campaign->id)->delete();
        AdAdGroup::where('ad_campaign_id', $campaign->id)->delete();
        $campaign->delete();

        return $this->done('Campaign archived on ChatGPT Ads and removed here.');
    }

    /** Editing isn't offered yet - pause/activate/archive cover the common changes. */
    public function update($platform, $id, $request)
    {
        return $this->fail('Editing ChatGPT Ads campaigns is not available here yet - change them in ads.openai.com, then use Sync Now.');
    }

    /** "Sync Now": every campaign on the ad account into ad_campaigns. */
    public function syncCampaigns(): array
    {
        if ($error = $this->accountError()) {
            return ['success' => false, 'error' => $error];
        }

        $synced = 0;
        $created = 0;
        $after = null;

        for ($page = 0; $page < 20; $page++) {
            $response = $this->api()->get('/campaigns', array_filter(['limit' => 100, 'after' => $after]));

            if (! $response->successful()) {
                return ['success' => false, 'error' => OpenAiAds::errorMessage($response, 'Could not read campaigns from ChatGPT Ads.')];
            }

            $items = $response->json('data') ?? (array_is_list((array) $response->json()) ? $response->json() : []);

            foreach ($items as $item) {
                if (empty($item['id'])) {
                    continue;
                }

                $row = AdCampaign::updateOrCreate(
                    ['ad_campaign_id' => $item['id'], 'platform' => 'openai', 'user_id' => Auth::id()],
                    [
                        'social_account_id' => $this->account->id,
                        'name' => $item['name'] ?? 'ChatGPT Ads campaign',
                        'status' => strtoupper($item['status'] ?? 'unknown'),
                        'budget_mode' => 'lifetime_budget',
                        'budget' => isset($item['budget']['lifetime_spend_limit_micros']) ? $item['budget']['lifetime_spend_limit_micros'] / 1_000_000 : null,
                        'bidding_strategy' => $item['bidding_type'] ?? null,
                        'start_time' => isset($item['start_time']) ? Carbon::createFromTimestamp($item['start_time']) : null,
                        'end_time' => isset($item['end_time']) ? Carbon::createFromTimestamp($item['end_time']) : null,
                    ]
                );
                $synced++;
                $created += $row->wasRecentlyCreated ? 1 : 0;
            }

            $after = ($response->json('has_more') && $items) ? (end($items)['id'] ?? null) : null;
            if (! $after) {
                break;
            }
        }

        return ['success' => true, 'synced' => $synced, 'created' => $created, 'updated' => $synced - $created, 'message' => "Synced {$synced} campaign(s) from ChatGPT Ads."];
    }

    /**
     * Last-30-days performance per campaign (GET /ad_account/insights,
     * aggregation_level=campaign, one period total). Spend / CPC are in the
     * account's currency, major units.
     */
    public function insights(int $days = 30): array
    {
        if ($error = $this->accountError()) {
            return ['success' => false, 'error' => $error];
        }

        $fields = ['campaign_id', 'impressions', 'clicks', 'spend', 'ctr', 'cpc'];
        $range = json_encode(['type' => 'date_range', 'since' => now()->subDays($days - 1)->toDateString(), 'until' => now()->toDateString(), 'timezone' => config('app.timezone', 'UTC')]);
        // The API documents repeated fields[] / time_ranges[] parameters.
        $query = 'aggregation_level=campaign&time_granularity=none&time_ranges[]=' . rawurlencode($range)
            . implode('', array_map(fn ($f) => '&fields[]=' . $f, $fields));

        $response = $this->api()->get('/ad_account/insights?' . $query);

        if (! $response->successful()) {
            return ['success' => false, 'error' => OpenAiAds::errorMessage($response, 'Could not load ChatGPT Ads reporting.')];
        }

        $rows = collect($response->json('data') ?? []);
        $local = AdCampaign::where('platform', 'openai')->where('user_id', Auth::id())->pluck('id', 'ad_campaign_id');

        $byCampaign = $rows->filter(fn ($r) => isset($r['campaign_id']))->mapWithKeys(fn ($r) => [
            (string) ($local[$r['campaign_id']] ?? $r['campaign_id']) => [
                'impressions' => (int) ($r['impressions'] ?? 0),
                'clicks' => (int) ($r['clicks'] ?? 0),
                'spend' => (float) ($r['spend'] ?? 0),
                'ctr' => isset($r['ctr']) ? (float) $r['ctr'] : null,
                'cpc' => isset($r['cpc']) ? (float) $r['cpc'] : null,
            ],
        ]);

        $impressions = $byCampaign->sum('impressions');
        $clicks = $byCampaign->sum('clicks');
        $spend = $byCampaign->sum('spend');

        return [
            'success' => true,
            'days' => $days,
            'currency' => $this->account->adDetails->currency ?? ($this->account->metadata['currency'] ?? null),
            'totals' => [
                'impressions' => $impressions,
                'clicks' => $clicks,
                'spend' => round($spend, 2),
                'ctr' => $impressions ? round($clicks / $impressions, 4) : null,
                'cpc' => $clicks ? round($spend / $clicks, 2) : null,
            ],
            'campaigns' => $byCampaign,
        ];
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function api()
    {
        return OpenAiAds::client((string) $this->account->access_token);
    }

    private function accountError(): ?string
    {
        if (! $this->account || ! $this->account->access_token) {
            return 'Connect a ChatGPT Ads account in Connections first.';
        }

        return null;
    }

    public static function micros($amount): int
    {
        return (int) round(((float) $amount) * 1_000_000);
    }

    /** The image file (or URL) -> an OpenAI file_id, or ['error' => ...]. */
    private function uploadImage(?UploadedFile $file, ?string $url): string|array
    {
        if ($file) {
            // The upload endpoint takes a public image URL: put the file on
            // the app's media storage first.
            $path = Storage::disk('r2')->putFile('uploads/openai-ads', $file);
            if (! $path) {
                return ['error' => 'Could not store the ad image. Please try again.'];
            }
            $url = Storage::disk('r2')->url($path);
        }

        if (! $url) {
            return ['error' => 'Add an image for the ad.'];
        }

        $response = $this->api()->post('/upload', ['image_url' => $url]);
        $fileId = $response->json('file_id') ?? $response->json('id');

        if (! $response->successful() || ! $fileId) {
            return ['error' => OpenAiAds::errorMessage($response, 'OpenAI did not accept the ad image.')];
        }

        return (string) $fileId;
    }

    private function rollback(string $campaignId, string $error)
    {
        try {
            $this->api()->post("/campaigns/{$campaignId}/archive");
        } catch (\Throwable $e) {
            Log::warning('ChatGPT Ads: could not archive a half-created campaign.', ['campaign' => $campaignId, 'error' => $e->getMessage()]);
        }

        return $this->fail($error);
    }

    private function saveLocally(Response $campaign, Response $adGroup, Response $ad, $request): AdCampaign
    {
        $base = ['platform' => 'openai', 'user_id' => Auth::id(), 'social_account_id' => $this->account->id];

        $local = AdCampaign::create($base + [
            'ad_campaign_id' => $campaign->json('id'),
            'name' => $request['name'],
            'status' => strtoupper($campaign->json('status') ?? 'paused'),
            'budget_mode' => 'lifetime_budget',
            'budget' => (float) $request['budget'],
            'bidding_strategy' => $request['bidding_type'] ?? 'clicks',
            'start_time' => $request['start_time'] ?? null,
            'end_time' => $request['end_time'] ?? null,
        ]);

        $group = AdAdGroup::create($base + [
            'ad_campaign_id' => $local->id,
            'ad_adgroup_id' => $adGroup->json('id'),
            'name' => $adGroup->json('name') ?? ($request['name'] . ' - Ad group'),
            'billing_event' => $request['billing_event'] ?? 'click',
            'bid_price' => $request['max_bid'],
            'status' => true,
        ]);

        $creative = AdCreative::create($base + [
            'ad_campaign_id' => $local->id,
            'ad_adgroup_id' => $group->id,
            'name' => $request['title'],
            'headline' => $request['title'],
            'message' => $request['body'],
            'url' => $request['target_link'],
            'type' => 'chat_card',
        ]);

        Ad::create($base + [
            'ad_campaign_id' => $local->id,
            'ad_adgroup_id' => $group->id,
            'ad_creative_id' => $creative->id,
            'ad_id' => $ad->json('id'),
            'name' => $ad->json('name') ?? $request['title'],
            'type' => 'chat_card',
            'status' => strtoupper($ad->json('review_status') ?? 'in_review'),
            'text' => $request['body'],
        ]);

        return $local;
    }

    private function localCampaign($id): ?AdCampaign
    {
        return AdCampaign::where('platform', 'openai')->where('user_id', Auth::id())->find($id);
    }

    private function fail(string $message, int $status = 422)
    {
        if (! request()->expectsJson() && request()->isMethod('delete')) {
            return redirect()->route('admin.ads.campaigns.index', ['platform' => 'openai'])->with('error', $message);
        }

        return response()->json(['success' => false, 'message' => $message, 'error' => $message], $status);
    }

    /** JSON for the page's AJAX calls; a redirect back for plain form posts (Delete). */
    private function done(string $message)
    {
        if (! request()->expectsJson()) {
            return redirect()->route('admin.ads.campaigns.index', ['platform' => 'openai'])->with('success', $message);
        }

        return response()->json(['success' => true, 'message' => $message]);
    }
}
