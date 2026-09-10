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

        // Per-platform connect URLs - route() can't be called inside the
        // service without pulling the container in, and the connect target
        // differs by whether the platform is already connected.
        $data['platforms'] = collect($data['platforms'])->map(function (array $p) {
            $p['connect_url'] = $p['connected']
                ? route('admin.ads.campaigns.index', ['platform' => $p['platform']])
                : route('admin.ads.redirect', $p['platform']);

            return $p;
        })->all();

        // $connected kept for the shared <x-social-connect-modal> the view
        // still renders (1 = connected, 0 = not) - same shape dashboard()
        // passed before this became a Vue page.
        $connected = collect($data['platforms'])
            ->mapWithKeys(fn ($p) => [$p['platform'] => $p['connected'] ? 1 : 0])
            ->all();

        return view('admin.ads.dashboard', compact('data', 'connected'));
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
