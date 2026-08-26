<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Requests\Admin\AdCampaignRequest;
use App\Models\Admin\AdCampaign;
use App\Models\SocialAccount;
use App\Models\Admin\PlatformPage;
use App\Models\Country;
use App\Services\AdServices\SocialAdManagerService;
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
        // An open-ended campaign (no end date) stores a NULL end_time, and
        // `end_time >= now()` is never true for NULL - so LinkedIn campaigns
        // created without an end date (end_time is nullable in
        // AdCampaignRequest::getLinkedinRules(), and LinkedIn campaign groups
        // genuinely can run indefinitely) saved fine but never appeared in
        // this listing. Treat NULL as "still running".
        $campaigns = $this->adCampaignModel->where('platform', $platform)
            ->where(function ($query) {
                $query->whereNull('end_time')->orWhere('end_time', '>=', now());
            })
            ->orderBy('id', 'desc')
            ->paginate(50);

        return view('admin.ads.' . $this->viewPlatform($platform) . '.campaigns.index', compact('campaigns', 'platform'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create($platform)
    {
        // YouTube Demand Gen campaigns run through the same Google Ads
        // customer as Search campaigns - there's no separate "YouTube Ads
        // account" - so account-linked status is read off the 'google' row.
        $account = $this->adAccountModel->where('has_ads_permission', true)->where('platform', $platform === 'youtube' ? 'google' : $platform)->first();
        $countries = $this->countryModel->all();
        $platformPages = $this->platformPages($platform);

        return view('admin.ads.' . $this->viewPlatform($platform) . '.campaigns.create', compact('platform', 'account', 'countries', 'platformPages'));
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
        $account = $this->adAccountModel->where('has_ads_permission', true)->where('platform', 'facebook')->first();
        $instagramAccount = $this->adAccountModel->where('has_ads_permission', true)->where('platform', 'instagram')->where('user_id', Auth::id())->first();

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
        $request = $request->validated();

        return $this->socialAdManager->store($platform, $request);
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
        $account = $this->adAccountModel->where('has_ads_permission', true)->where('platform', $platform === 'youtube' ? 'google' : $platform)->first();
        $countries = $this->countryModel->all();
        $campaign = $this->adCampaignModel->with(['socialAccount', 'adGroups', 'adGroups.creatives', 'adGroups.creatives.media', 'ads'])->find($id);
        $platformPages = $this->platformPages($platform);

       return view('admin.ads.' . $this->viewPlatform($platform) . '.campaigns.edit', compact('platform', 'account', 'countries', 'campaign', 'platformPages'));
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
