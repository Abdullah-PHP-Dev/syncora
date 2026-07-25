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
        $account = $this->adAccountModel->where('platform', $platform)->first();
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
        $account = $this->adAccountModel->where('platform', $platform)->first();
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
}
