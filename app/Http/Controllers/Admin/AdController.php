<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SocialAccount;
use Carbon\Carbon;

use App\Services\AdServices\SocialAdManagerService;

class AdController extends Controller
{
    protected $adAccountModel;

    public function __construct(SocialAccount $adAccountModel)
    {
        $this->adAccountModel = $adAccountModel;
    }

    public function dashboard()
    {
        $accounts = $this->adAccountModel->where('has_ads_permission', true)
            ->whereNotNull('access_token')
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

    // redirects() (plural) used to live here - a second, unrouted
    // implementation of the same OAuth-redirect job as redirect() above,
    // built entirely from env('APP_DOMAIN')/hardcoded '/admin/social/
    // auth/{platform}/callback' path strings rather than route(). Removed:
    // it wasn't bound to any route (confirmed against routes/web.php) and
    // called $this->buildBaseString(), a method that doesn't exist
    // anywhere in this class - it would have fatal-errored the moment
    // anything actually invoked it. The live path for every Ads platform
    // is redirect() -> SocialAdManagerService -> each platform's own
    // AdService, all of which build their callback URL via
    // route('admin.ads.platform.callback', $platform).
}
