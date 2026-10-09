<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\SocialAccount;
use Carbon\Carbon;

use App\Services\AdServices\AdsDashboardService;
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
        $data = (new AdsDashboardService(Auth::id()))->build();

        // Campaign links per connected platform (route() can't run inside
        // the service). Connecting accounts lives in the Connection Hub, so
        // the dashboard only links there when nothing is connected yet.
        $data['platforms'] = collect($data['platforms'])->map(function (array $p) {
            $p['campaigns_url'] = $p['connected'] ? route('admin.ads.campaigns.index', ['platform' => $p['platform']]) : null;
            $p['create_url'] = $p['connected'] ? route('admin.ads.campaigns.create_new', ['platform' => $p['platform']]) : null;

            return $p;
        })->all();

        $data['connections_url'] = route('admin.connections.index');

        return view('admin.ads.dashboard', compact('data'));
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
