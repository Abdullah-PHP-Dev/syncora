<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Requests\Admin\AdCampaignRequest;
use App\Models\Admin\AdCampaign;
use App\Models\MediaAsset;
use App\Models\SocialAccount;
use App\Models\Admin\PlatformPage;
use App\Models\Country;
use App\Services\AdServices\AdsDashboardService;
use App\Services\AdServices\AdsPerformanceService;
use App\Services\AdServices\FacebookCampaignsPageService;
use App\Services\AdServices\FacebookAiInsightsService;
use App\Services\AdServices\SocialAdManagerService;
use App\Support\AdAccountSelection;
use Illuminate\Support\Facades\Auth;


class AdCampaignController extends Controller
{
    protected $adCampaignModel, $adAccountModel, $platformPageModel, $countryModel, $socialAdManager;

    public function __construct(AdCampaign $adCampaignModel, SocialAccount $adAccountModel, PlatformPage $platformPageModel, Country $countryModel, SocialAdManagerService $socialAdManager)
    {
        set_time_limit(0);
        $this->adCampaignModel = $adCampaignModel;
        $this->adAccountModel = $adAccountModel;
        $this->platformPageModel = $platformPageModel;
        $this->countryModel = $countryModel;
        $this->socialAdManager = $socialAdManager;
    }

    /**
     * Pages connected via this platform's OAuth flow, selectable when
     * attaching a campaign/ad set/ad to a Page - stored in a platform-
     * agnostic table so Instagram/TikTok/X/LinkedIn can reuse the same
     * "pick a page" UI once they populate their own rows.
     */
    /**
     * The ad account this page works with: ?account= (switcher) or
     * ?connected= (just connected via OAuth, see ReturnToOrigin) when it's
     * one of the seller's ad accounts, else the remembered choice - the same
     * one the *AdService will use when the campaign is saved.
     *
     * @return array{0: ?\App\Models\SocialAccount, 1: \Illuminate\Support\Collection}
     */
    private function selectedAdAccount(string $platform): array
    {
        $requested = (int) (request()->query('account') ?: request()->query('connected'));
        if ($requested) {
            AdAccountSelection::select(Auth::id(), $platform, $requested);
        }

        return [AdAccountSelection::resolve(Auth::id(), $platform), AdAccountSelection::options(Auth::id(), $platform)];
    }

    private function platformPages(string $platform)
    {
        return $this->platformPageModel
            ->where('platform', $platform)
            ->where('user_id', Auth::id())
            ->orderBy('name')
            ->get();
    }
    /**
     * Instagram has no dedicated Blade files of its own - it shares
     * Facebook's entire ad set/creative shape (same FacebookAdService,
     * same targeting spec built in storeAdGroup()/updateAdGroup()), so
     * its campaign views reuse Facebook's folder outright rather than
     * duplicating ~3,000 lines of identical markup. $platform itself
     * still flows through unchanged everywhere else (routes, hidden
     * fields, DB rows all still say 'instagram') - only the view path
     * is aliased, the same "share the underlying flow, keep the
     * platform key distinct" shape this controller already uses for
     * YouTube's SocialAccount lookup below.
     */
    private function viewPlatform(string $platform): string
    {
        return $platform === 'instagram' ? 'facebook' : $platform;
    }

    /**
     * Display a listing of the resource.
     */
    public function index($platform)
    {
        // Meta platforms: one ad account, Instagram narrowed to Instagram placements.
        if (in_array($platform, ['facebook', 'instagram'], true)) {
            return $this->metaIndex($platform);
        }

        $data = (new AdsDashboardService(Auth::id()))->forPlatform($platform);

        // URLs the Vue dashboard needs - resolved here so the service
        // stays free of route()/request context.
        $data['urls'] = [
            'create'    => route('admin.ads.campaigns.create', ['platform' => $platform]),
            'sync'      => route('admin.ads.campaigns.sync', ['platform' => $platform]),
            'connect'   => route('admin.ads.redirect', $platform),
            'dashboard' => route('admin.ads.dashboard'),
            'edit'      => route('admin.ads.campaigns.edit', ['platform' => $platform, 'campaign' => 'CAMPAIGN_ID']),
            'destroy'   => route('admin.ads.campaigns.destroy', ['platform' => $platform, 'campaign' => 'CAMPAIGN_ID']),
            'connections' => route('admin.connections.index'),
            // Live spend and results where the platform reports them (AdsPerformanceService).
            'performance' => in_array($platform, ['google', 'youtube', 'openai'], true)
                ? route('admin.ads.campaigns.performance', ['platform' => $platform]) : null,
        ];
        $data['can_sync'] = in_array($platform, ['facebook', 'openai'], true);

        // Export and scheduled email reports, as on the Meta pages (AdReportController).
        $data['urls'] += [
            'export'  => route('admin.ads.campaigns.export', ['platform' => $platform]),
            'reports' => route('admin.ads.reports.store', ['platform' => $platform]),
            'report'  => route('admin.ads.reports.update', ['platform' => $platform, 'id' => 0]),
            'upgrade' => route('admin.subscription.select'),
        ];
        $account = $data['connected'] ? \App\Support\AdAccountSelection::resolve(Auth::id(), $platform) : null;
        $data['reports'] = $account
            ? \App\Models\AdReportSchedule::where('user_id', Auth::id())->where('social_account_id', $account->id)->where('platform', $platform)->latest('id')->get()
                ->map(fn ($r) => \App\Http\Controllers\Admin\AdReportController::present($r))->all()
            : [];
        $data['recipient_email'] = \App\Http\Controllers\Admin\AdReportController::loginEmail(request());
        $data['can_export'] = app(\App\Services\PackageAccessService::class)->allows(Auth::user(), 'data_export');

        // ChatGPT Ads: no in-app edit form yet
        // (pause / activate / archive cover it - see OpenAiAdService).
        if ($platform === 'openai') {
            $data['urls']['status'] = route('admin.ads.campaigns.status', ['platform' => $platform, 'id' => 'CAMPAIGN_ID']);
            $data['urls']['edit'] = null;
        }

        return view('admin.ads.campaigns.dashboard', ['data' => $data, 'platform' => $platform, 'messages' => __('ads_campaigns')]);
    }

    /**
     * Facebook campaigns page: everything for the selected ad account
     * (?account= switches it, ownership checked by AdAccountSelection).
     * Spend and results load from the insights endpoint afterwards.
     */
    private function metaIndex(string $platform)
    {
        $this->selectedAdAccount('facebook'); // Instagram ads run on the Facebook ad account
        $member = request()->attributes->get('workspace_member');
        $memberCanWrite = ! $member || ($member->permissions['advertising'] ?? 'none') === 'write';

        $data = (new FacebookCampaignsPageService(Auth::id(), $memberCanWrite, $platform))->build();
        // Scheduled reports go to whoever is signed in; export needs the Data export module.
        $data['reports'] = $data['account']
            ? \App\Models\AdReportSchedule::where('user_id', Auth::id())->where('social_account_id', $data['account']['id'])->where('platform', $platform)->latest('id')->get()
                ->map(fn ($r) => \App\Http\Controllers\Admin\AdReportController::present($r))->all()
            : [];
        $data['recipient_email'] = \App\Http\Controllers\Admin\AdReportController::loginEmail(request());
        $data['can_export'] = app(\App\Services\PackageAccessService::class)->allows(Auth::user(), 'data_export');
        $data['urls'] = [
            'create'      => route('admin.ads.campaigns.create', ['platform' => $platform]),
            'sync'        => route('admin.ads.campaigns.sync', ['platform' => $platform]),
            'insights'    => route('admin.ads.campaigns.insights', ['platform' => $platform]),
            'ai_insights' => route('admin.ads.campaigns.ai-insights', ['platform' => $platform]),
            'ai_generate' => route('admin.ads.campaigns.ai-insights.generate', ['platform' => $platform]),
            'usage'       => route('admin.subscription.usage'),
            'export'      => route('admin.ads.campaigns.export', ['platform' => $platform]),
            'reports'     => route('admin.ads.reports.store', ['platform' => $platform]),
            'report'      => route('admin.ads.reports.update', ['platform' => $platform, 'id' => 0]),
            'upgrade'     => route('admin.subscription.select'),
            'status'      => route('admin.ads.campaigns.status', ['platform' => $platform, 'id' => 'CAMPAIGN_ID']),
            'duplicate'   => route('admin.ads.campaigns.duplicate', ['platform' => $platform, 'id' => 'CAMPAIGN_ID']),
            'edit'        => route('admin.ads.campaigns.edit', ['platform' => $platform, 'campaign' => 'CAMPAIGN_ID']),
            'destroy'     => route('admin.ads.campaigns.destroy', ['platform' => $platform, 'campaign' => 'CAMPAIGN_ID']),
            'switch'      => route('admin.ads.campaigns.index', ['platform' => $platform]) . '?account=ACCOUNT_ID',
            'connections' => route('admin.connections.index'),
            'dashboard'   => route('admin.ads.dashboard'),
            'meta'        => 'https://adsmanager.facebook.com/adsmanager/manage/campaigns?act=',
            'ai_copilot'  => \Illuminate\Support\Facades\Route::has('admin.ai-copilot.analytics.index') ? route('admin.ai-copilot.analytics.index') : null,
        ];

        return view('admin.ads.campaigns.facebook', [
            'data' => $data,
            'messages' => __('ads_campaigns'),
            'title' => __('ads_campaigns.page_title_' . $platform),
        ]);
    }

    /** Last AI insights for the selected account and period (no AI credit used). */
    public function aiInsights($platform, Request $request, FacebookAiInsightsService $ai)
    {
        abort_unless(in_array($platform, ['facebook', 'instagram'], true), 404);
        $days = (int) ($request->validate(['days' => ['nullable', 'integer', 'in:7,14,30,90']])['days'] ?? 30);
        $account = AdAccountSelection::resolve(Auth::id(), 'facebook');

        return response()->json(['success' => true, 'data' => $account ? $ai->cached($account->id, $days, app()->getLocale(), $platform) : null]);
    }

    /** Generate AI insights - one AI credit, reserved and refunded on failure by EnsurePackageFeature. */
    public function generateAiInsights($platform, Request $request, FacebookAiInsightsService $ai)
    {
        abort_unless(in_array($platform, ['facebook', 'instagram'], true), 404);
        $days = (int) ($request->validate(['days' => ['nullable', 'integer', 'in:7,14,30,90']])['days'] ?? 30);
        $account = AdAccountSelection::resolve(Auth::id(), 'facebook');
        if (! $account) {
            return response()->json(['success' => false, 'message' => 'Connect a Facebook ad account first.'], 422);
        }

        $result = $ai->generate($this->socialAdManager->service($platform), $account->id, Auth::id(), $days, app()->getLocale(), $platform);

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    /** Copy a campaign on the platform (Facebook: Meta /copies, created paused). */
    public function duplicate($platform, string $id)
    {
        $service = $this->socialAdManager->service($platform);
        abort_unless(method_exists($service, 'duplicate'), 404);

        return $service->duplicate($id);
    }

    /**
     * "Sync Now" - pulls fresh campaigns from the connected platform via
     * the existing per-platform ad service (see
     * SocialAdManagerService::syncCampaigns). JSON, called by the
     * dashboard's Sync Now button.
     */
    public function sync($platform)
    {
        $result = $this->socialAdManager->syncCampaigns($platform);

        return response()->json($result, ($result['success'] ?? false) ? 200 : 422);
    }

    /** Performance for the campaigns page (platforms whose service reports it). */
    /** Live spend and results for the platform campaigns pages (Google, YouTube, ChatGPT Ads). */
    public function performance($platform, Request $request)
    {
        $validated = $request->validate(['days' => ['nullable', 'integer', 'in:' . implode(',', AdsPerformanceService::RANGES)]]);

        return response()->json((new AdsPerformanceService(Auth::id()))->forPlatform($platform, (int) ($validated['days'] ?? 30)));
    }

    public function insights($platform, Request $request)
    {
        $service = $this->socialAdManager->service($platform);

        if (! method_exists($service, 'insights')) {
            return response()->json(['success' => false, 'error' => 'Reporting is not available for this platform yet.'], 404);
        }

        $validated = $request->validate(['days' => ['nullable', 'integer', 'in:7,14,30,90'], 'fresh' => ['nullable', 'boolean']]);
        $days = (int) ($validated['days'] ?? 30);

        $result = $platform === 'facebook' ? $service->insights($days, $request->boolean('fresh')) : $service->insights($days);

        return response()->json($result, ($result['success'] ?? false) ? 200 : 422);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create($platform)
    {
        // YouTube Demand Gen campaigns run through the same Google Ads
        // customer as Search campaigns - there's no separate "YouTube Ads
        // account" - so account-linked status is read off the 'google' row.
        [$account, $adAccounts] = $this->selectedAdAccount($platform);
        $account?->loadMissing('adDetails');
        $countries = $this->countryModel->all();
        $platformPages = $this->platformPages($platform);

        // "Promote" from Content Publishing: the seller's own post to use as
        // this campaign's creative (see ads.partials.promote-banner).
        $promotePost = request()->integer('post')
            ? \App\Models\Post::with('media')->where('user_id', Auth::id())->find(request()->integer('post'))
            : null;

        // Facebook: the campaign builder (FacebookCampaignBuilder - create or
        // reuse campaign/ad set/creative). The previous form stays at
        // ?legacy=1 while the builder rolls out.
        if ($platform === 'facebook' && ! request()->boolean('legacy')) {
            return view('admin.ads.facebook.campaigns.builder', compact('platform', 'account', 'adAccounts', 'promotePost'));
        }

        // Instagram: the same builder in Instagram mode. Instagram ads run on
        // the Meta (Facebook) ad account, so that's the account selected here.
        if ($platform === 'instagram' && ! request()->boolean('legacy')) {
            [$account, $adAccounts] = $this->selectedAdAccount('facebook');

            return view('admin.ads.facebook.campaigns.builder', compact('platform', 'account', 'adAccounts', 'promotePost'));
        }

        // TikTok: the campaign builder (TiktokCampaignBuilder); the previous
        // form stays at ?legacy=1.
        if ($platform === 'tiktok' && ! request()->boolean('legacy')) {
            return view('admin.ads.tiktok.campaigns.builder', compact('platform', 'account', 'adAccounts', 'promotePost'));
        }

        // X: the campaign builder (XCampaignBuilder); the previous form
        // stays at ?legacy=1.
        if ($platform === 'x' && ! request()->boolean('legacy')) {
            return view('admin.ads.x.campaigns.builder', compact('platform', 'account', 'adAccounts', 'promotePost'));
        }

        // Snapchat: the campaign builder (SnapchatCampaignBuilder); the
        // previous form stays at ?legacy=1.
        if ($platform === 'snapchat' && ! request()->boolean('legacy')) {
            return view('admin.ads.snapchat.campaigns.builder', compact('platform', 'account', 'adAccounts', 'promotePost'));
        }

        // Google + YouTube: the campaign builder (GoogleCampaignBuilder - Search,
        // Display, Performance Max; YouTube = Demand Gen on the same Google Ads
        // account); the previous forms stay at ?legacy=1.
        if (in_array($platform, ['google', 'youtube'], true) && ! request()->boolean('legacy')) {
            return view('admin.ads.google.campaigns.builder', compact('platform', 'account', 'adAccounts', 'promotePost'));
        }

        return view('admin.ads.' . $this->viewPlatform($platform) . '.campaigns.create', compact('platform', 'account', 'adAccounts', 'countries', 'platformPages', 'promotePost'));
    }

    /**
     * Vue-powered redesign of the Facebook campaign builder, matching a
     * supplied Meta Ads Manager mockup - a parallel prototype to create()
     * for side-by-side comparison, not a replacement, so it's routed and
     * named separately (see routes/web.php) rather than swapped into the
     * existing view() call above. Submits to the same store() action and
     * AdCampaignRequest::getFacebookRules() as create.blade.php, so it's
     * a fully working page, not a static mock. Facebook Pages and the
     * connected Instagram account are fetched together here (unlike
     * create(), which only loads whichever platform the route segment
     * says) since this design shows both placement selects on one screen -
     * see FacebookAdService::storeCreative()'s docblock for why Instagram
     * placement is a single connected account, not a per-page picker.
     */
    public function createNew($platform)
    {
        // Scoped to the signed-in seller - this used to load the first
        // Facebook ad account in the whole table, i.e. possibly another
        // seller's.
        [$account, $adAccounts] = $this->selectedAdAccount('facebook');
        $instagramAccount = $this->adAccountModel->usableFor('ads')->where('platform', 'instagram')->where('user_id', Auth::id())->first();

        // Mapped to plain arrays here rather than inside the view's
        // @json() calls - an fn() => [...] arrow-closure array literal as
        // a @json() argument trips Blade's directive-parenthesis matcher
        // (it truncates the expression early), so the view only ever
        // @json()s an already-built variable.
        $countriesData = $this->countryModel->all()->map(fn ($c) => ['id' => $c->id, 'name' => $c->name])->values()->all();
        $pagesData = $this->platformPages('facebook')->map(fn ($p) => ['id' => $p->page_id, 'name' => $p->name, 'username' => $p->username, 'picture' => $p->picture])->values()->all();
        $instagramAccountData = $instagramAccount ? ['name' => $instagramAccount->name, 'username' => $instagramAccount->username ?? null] : null;

        return view('admin.ads.facebook.campaigns.create-new', [
            'platform'             => $platform,
            'account'              => $account,
            'adAccounts'           => $adAccounts,
            'platform'             => $platform,
            'countriesData'        => $countriesData,
            'pagesData'            => $pagesData,
            'instagramAccountData' => $instagramAccountData,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store($platform, AdCampaignRequest $request)
    {
        // Media Gallery items the form's media[] files came from (the
        // picker copies each gallery file into the form's own file input,
        // so the platform services upload them exactly like a local pick).
        // Only the current user's own assets are ever linked.
        $galleryIds = MediaAsset::ownedBy(Auth::id())
            ->whereIn('id', array_map('intval', (array) $request->input('media_asset_ids', [])))
            ->pluck('id');
        $startedAt = now()->subSecond();

        $response = $this->socialAdManager->store($platform, $request->validated());

        // Services return a JsonResponse without the new campaign's id -
        // the campaign this request just created is this user's newest.
        $succeeded = match (true) {
            $response instanceof \Illuminate\Http\JsonResponse => ($response->getData(true)['success'] ?? false) === true,
            is_array($response) => ($response['success'] ?? false) === true,
            default => false,
        };

        if ($galleryIds->isNotEmpty() && $succeeded) {
            $this->adCampaignModel->where('user_id', Auth::id())
                ->where('created_at', '>=', $startedAt)
                ->latest('id')
                ->first()
                ?->mediaAssets()
                ->syncWithoutDetaching($galleryIds);
        }

        return $response;
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($platform, string $id)
    {
        $account = $this->adAccountModel->where('user_id', Auth::id())->usableFor('ads')->where('platform', $platform === 'youtube' ? 'google' : $platform)->with('adDetails')->first();
        $countries = $this->countryModel->all();
        $campaign = $this->adCampaignModel->with(['socialAccount', 'adGroups', 'adGroups.creatives', 'adGroups.creatives.media', 'ads'])->findOrFail($id);
        // Facebook / Instagram: the campaign builder in edit mode (same design
        // as the create page); the previous edit form stays at ?legacy=1.
        if (in_array($platform, ['facebook', 'instagram'], true) && ! request()->boolean('legacy')) {
            [$account, $adAccounts] = $this->selectedAdAccount('facebook');
            $edit = \App\Services\AdServices\FacebookCampaignBuilder::editData($campaign);
            $promotePost = null;

            return view('admin.ads.facebook.campaigns.builder', compact('platform', 'account', 'adAccounts', 'promotePost', 'edit'));
        }

        if (in_array($platform, ['google', 'youtube'], true)) {
            $this->restoreGoogleCreative($campaign);

            // The campaign builder in edit mode (same design as the create page);
            // the previous edit form stays at ?legacy=1.
            if (! request()->boolean('legacy')) {
                [$account, $adAccounts] = $this->selectedAdAccount($platform);
                $edit = \App\Services\AdServices\GoogleCampaignBuilder::editData($campaign);
                $promotePost = null;

                return view('admin.ads.google.campaigns.builder', compact('platform', 'account', 'adAccounts', 'promotePost', 'edit'));
            }
        }
        $platformPages = $this->platformPages($platform);

       return view('admin.ads.' . $this->viewPlatform($platform) . '.campaigns.edit', compact('platform', 'account', 'countries', 'campaign', 'platformPages'));
    }

    /**
     * Campaigns from the Google/YouTube builder before 2026-10-22 were saved
     * without the ad-creative row the edit page and update() read the ad copy
     * from. Rebuild it (locally) from the saved ad so those campaigns open.
     */
    private function restoreGoogleCreative(\App\Models\Admin\AdCampaign $campaign): void
    {
        $group = $campaign->adGroups->first();
        $ad = $campaign->ads->first();
        if (! $group || ! $ad || $group->creatives->isNotEmpty()) {
            return;
        }

        $urls = json_decode($ad->final_urls ?? '[]', true) ?: [];
        \App\Models\Admin\AdCreative::create([
            'ad_creative_id' => $ad->ad_id, 'ad_adgroup_id' => $group->id, 'ad_campaign_id' => $campaign->id,
            'user_id' => $campaign->user_id, 'social_account_id' => $campaign->social_account_id, 'platform' => $campaign->platform,
            'name' => $campaign->name, 'type' => $ad->type, 'url' => $urls[0] ?? null, 'final_urls' => $ad->final_urls,
            'headlines' => $ad->headlines,
            'descriptions' => json_encode(array_values(array_filter(preg_split('/\r\n|\r|\n/', (string) $ad->text)))),
        ]);
        $campaign->load(['adGroups', 'adGroups.creatives', 'adGroups.creatives.media']);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update($platform, AdCampaignRequest $request, string $id)
    {
        $request = $request->validated();

        return $this->socialAdManager->update($platform, $id, $request);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($platform, string $id)
    {
        // Facebook: only the seller's own campaigns, and only with a role that may change them.
        if (in_array($platform, ['facebook', 'instagram'], true)) {
            $campaign = AdCampaign::with('socialAccount')->where('user_id', Auth::id())->whereIn('platform', ['facebook', 'instagram'])->find($id);
            abort_unless($campaign, 404);
            if ($blocker = \App\Services\AdServices\FacebookAdService::writeBlocker($campaign->socialAccount)) {
                return request()->expectsJson()
                    ? response()->json(['success' => false, 'message' => $blocker], 403)
                    : redirect()->route('admin.ads.campaigns.index', ['platform' => $platform])->with('error', $blocker);
            }
        }

        return $this->socialAdManager->destroy($platform, $id);
    }

    /**
     * Pause or reactivate a campaign (and its dependent adgroup/ad) without
     * deleting it.
     */
    public function updateStatus($platform, Request $request, string $id)
    {
        $request->validate([
            'status' => ['required', 'in:ACTIVE,PAUSED'],
        ]);

        return $this->socialAdManager->updateStatus($platform, $id, $request->input('status'));
    }

    /**
     * TikTok-only (see TiktokAdService::getIdentities()'s docblock) -
     * called directly rather than through SocialAdManagerService's
     * platform dispatch since getIdentities() isn't part of the uniform
     * redirect/callback/store/update/destroy contract every other
     * platform's service implements.
     */
    public function identities($platform)
    {
        if ($platform !== 'tiktok') {
            return response()->json(['success' => false, 'error' => 'Identity lookup is only available for TikTok.'], 404);
        }

        return response()->json(app(\App\Services\AdServices\TiktokAdService::class)->getIdentities());
    }
}
