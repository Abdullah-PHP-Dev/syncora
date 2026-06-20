<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Requests\AdCampaignRequest;
use App\Models\Admin\AdCampaign;
use App\Models\Admin\AdAccount;


class AdCampaignController extends Controller
{
    protected $adCampaignModel, $adAccountModel;

    public function __construct(AdCampaign $adCampaignModel, AdAccount $adAccountModel)
    {
        $this->adCampaignModel = $adCampaignModel;
        $this->adAccountModel = $adAccountModel;
    }
    /**
     * Display a listing of the resource.
     */
    public function index($platform)
    {
        $campaigns = $this->adCampaignModel->where('platform', $platform)->where('start_time', '<=', now())->where('end_time', '>=', now())->paginate(50);
        
        return view('admin.ads.'.$platform.'.campaigns.index', compact('campaigns', 'platform'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create($platform)
    {
        $account = $this->adAccountModel->where('platform', $platform)->first();
        return view('admin.ads.'.$platform.'.campaigns.create', compact('platform', 'account'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store($platform, AdCampaignRequest $request)
    {
        //
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
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
