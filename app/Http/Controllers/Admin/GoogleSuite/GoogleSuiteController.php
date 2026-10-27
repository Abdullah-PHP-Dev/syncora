<?php

namespace App\Http\Controllers\Admin\GoogleSuite;

use App\Http\Controllers\Controller;
use App\Services\Connections\Drivers\GoogleDriver;
use App\Services\GoogleSuite\GoogleSuiteAuth;
use App\Services\GoogleSuite\GoogleSuitePresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Google Suite pages (Analytics & Insights). Each is a server route that
 * mounts the google-suite Vue app on one screen; data loads from
 * GoogleSuiteDataController so a slow Google call never blocks the page.
 */
class GoogleSuiteController extends Controller
{
    public function __construct(private GoogleSuitePresenter $presenter)
    {
    }

    public function overview(Request $request): View
    {
        return $this->screen($request, 'overview');
    }

    public function integrations(Request $request): View
    {
        return $this->screen($request, 'integrations');
    }

    public function setup(Request $request): View
    {
        return $this->screen($request, 'setup');
    }

    /** Start the read-only Google consent (owner only - UseSellerWorkspace blocks *.connect). */
    public function connect(): RedirectResponse
    {
        return app(GoogleSuiteAuth::class)->redirect();
    }

    /** Where the OAuth callback lands: shows what Google confirmed, nothing assumed. */
    public function status(Request $request): View|RedirectResponse
    {
        $result = $request->session()->get('google_oauth');

        return $this->screen($request, 'status', ['oauth' => $result]);
    }

    public function properties(Request $request): View
    {
        return $this->screen($request, 'properties');
    }

    public function analytics(Request $request): View
    {
        return $this->screen($request, 'analytics');
    }

    public function searchConsole(Request $request): View
    {
        return $this->screen($request, 'search_console');
    }

    public function advertising(Request $request): View
    {
        return $this->screen($request, 'advertising');
    }

    public function websitePerformance(Request $request): View
    {
        return $this->screen($request, 'pagespeed');
    }

    public function businessProfile(Request $request): View
    {
        return $this->screen($request, 'business_profile');
    }

    public function tagManager(Request $request): View
    {
        return $this->screen($request, 'tag_manager');
    }

    public function reports(Request $request): View
    {
        return $this->screen($request, 'reports');
    }

    public function preferences(Request $request): View
    {
        return $this->screen($request, 'preferences');
    }

    public function health(Request $request): View
    {
        return $this->screen($request, 'health');
    }

    public function settings(Request $request, string $service): View
    {
        return $this->screen($request, 'settings', ['service' => $service === 'search-console' ? 'search_console' : 'ga4']);
    }

    /** Sidebar "Google Ads": campaigns live in Ads Manager. */
    public function ads(): RedirectResponse
    {
        return redirect()->route('admin.ads.campaigns.index', ['platform' => 'google']);
    }

    private function screen(Request $request, string $screen, array $extra = []): View
    {
        return view('admin.google.suite', [
            'screen' => $screen,
            'boot' => $this->presenter->boot($request, $screen, $extra + ['step' => GoogleDriver::ANALYTICS]),
        ]);
    }
}
