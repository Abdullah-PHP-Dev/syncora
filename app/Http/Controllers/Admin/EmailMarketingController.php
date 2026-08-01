<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmailMarketing\EmailCampaign;
use App\Models\EmailMarketing\EmailList;
use App\Models\EmailMarketing\EmailSubscriber;
use App\Services\EmailMarketingServices\EmailMarketingService;
use Illuminate\Support\Facades\Auth;

class EmailMarketingController extends Controller
{
    public function __construct(protected EmailMarketingService $emailMarketing)
    {
    }

    public function dashboard()
    {
        $userId = Auth::id();

        $totalSubscribers = EmailSubscriber::where('user_id', $userId)->where('status', 'subscribed')->count();
        $totalLists = EmailList::where('user_id', $userId)->count();
        $sentCampaigns = EmailCampaign::where('user_id', $userId)->where('status', 'sent')->get();

        $avgOpenRate = $sentCampaigns->isNotEmpty()
            ? round($sentCampaigns->avg(fn ($c) => $c->openRate()), 1)
            : 0.0;

        $recentCampaigns = EmailCampaign::where('user_id', $userId)->with('list')->latest()->take(6)->get();

        return view('admin.email.dashboard', [
            'isConfigured'     => $this->emailMarketing->isConfigured(),
            'totalSubscribers' => $totalSubscribers,
            'totalLists'       => $totalLists,
            'totalSent'        => $sentCampaigns->count(),
            'avgOpenRate'      => $avgOpenRate,
            'recentCampaigns'  => $recentCampaigns,
        ]);
    }
}
