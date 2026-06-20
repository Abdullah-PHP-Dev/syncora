<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Requests\AdCampaignRequest;
use App\Models\Admin\AdCampaign;

class AdCampaignController extends Controller
{
    protected $adCampaignModel;

    public function __construct(AdCampaign $adCampaignModel)
    {
        $this->adCampaignModel = $adCampaignModel;
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
        return view('admin.ads.'.$platform.'.campaigns.create', compact('platform'));
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
