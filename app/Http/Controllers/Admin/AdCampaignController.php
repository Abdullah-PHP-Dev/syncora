<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Requests\Admin\AdCampaignRequest;
use App\Models\Admin\AdCampaign;
use App\Models\Admin\AdAccount;
use App\Models\Country;
use App\Services\AdServices\SocialAdManagerService;


class AdCampaignController extends Controller
{
    protected $adCampaignModel, $adAccountModel, $countryModel, $socialAdManager;

    public function __construct(AdCampaign $adCampaignModel, AdAccount $adAccountModel, Country $countryModel, SocialAdManagerService $socialAdManager)
    {
        set_time_limit(0);
        $this->adCampaignModel = $adCampaignModel;
        $this->adAccountModel = $adAccountModel;
        $this->countryModel = $countryModel;
        $this->socialAdManager = $socialAdManager;
    }
    /**
     * Display a listing of the resource.
     */
    public function index($platform)
    {
        $campaigns = $this->adCampaignModel->where('platform', $platform)->where('end_time', '>=', now())->orderBy('id', 'desc')->paginate(50);
    
        return view('admin.ads.' . $platform . '.campaigns.index', compact('campaigns', 'platform'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create($platform)
    {
        // YouTube Demand Gen campaigns run through the same Google Ads
        // customer as Search campaigns - there's no separate "YouTube Ads
        // account" - so account-linked status is read off the 'google' row.
        $account = $this->adAccountModel->where('platform', $platform === 'youtube' ? 'google' : $platform)->first();
        $countries = $this->countryModel->all();

        return view('admin.ads.' . $platform . '.campaigns.create', compact('platform', 'account', 'countries'));
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
        $account = $this->adAccountModel->where('platform', $platform === 'youtube' ? 'google' : $platform)->first();
        $countries = $this->countryModel->all();
        $campaign = $this->adCampaignModel->with(['adAccount', 'adGroups', 'adGroups.creatives', 'adGroups.creatives.media', 'ads'])->find($id);
    
       return view('admin.ads.' . $platform . '.campaigns.edit', compact('platform', 'account', 'countries', 'campaign'));
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
