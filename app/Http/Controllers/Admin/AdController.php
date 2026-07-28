<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Admin\AdAccount;
use App\Services\AdServices\SocialAdManagerService;

class AdController extends Controller
{
    protected $adAccountModel;

    public function __construct(AdAccount $adAccountModel)
    {
        $this->adAccountModel = $adAccountModel;
    }

    public function dashboard()
    {
        $accounts = $this->adAccountModel->whereNotNull('access_token')
            ->where('expires_at', '>', now())
            ->get()
            ->groupBy('platform');

        $platforms = ['facebook','instagram','google','youtube','tiktok','snapchat','x','linkedin'];

        $connected = [];

        foreach ($platforms as $platform) {
            $connected[$platform] = $accounts->get($platform, collect())->count();
        }

        return view('admin.ads.dashboard', compact('accounts', 'connected' ));
    }
    
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        
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
    public function redirect(
        string $platform,
        SocialAdManagerService $manager
    ) {
    
        return $manager->redirect($platform);
    
    }

    public function callback(
        string $platform,
        SocialAdManagerService $manager
    ) {
    
        return $manager->callback($platform);
    
    }

}
