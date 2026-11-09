<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MediaAsset;
use App\Models\PostCategory;
use App\Models\SocialAccount;
use App\Models\Workspace;
use App\Services\PackageAccessService;
use App\Services\PackageUsageService;
use App\Services\WorkspaceService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class PackageUsageController extends Controller
{
    public function __invoke(Request $request, PackageAccessService $access, PackageUsageService $usage)
    {
        $user = $request->user();
        $subscription = $access->subscription($user);
        $workspaces = app(WorkspaceService::class);
        // Capacities: what exists right now (per workspace account).
        $capacities = [
            'team_members' => app(\App\Services\SellerTeamService::class)->seats($user),
            'publishing_accounts' => SocialAccount::where('user_id', $user->id)->where('has_posting_permission', true)->count(),
            'advertising_accounts' => SocialAccount::where('user_id', $user->id)->where('has_ads_permission', true)->count(),
            'content_categories' => PostCategory::where('user_id', $user->id)->count(),
            'media_storage_gb' => round(MediaAsset::withTrashed()->where('user_id', $user->id)->sum('size') / 1024 ** 3, 3),
        ];
        // Allowances: what was used this period (shared across the owner's workspaces).
        $allowances = [];
        foreach (['scheduled_published_posts', 'ai_credits', 'hashtag_generation'] as $key) {
            $allowances[$key] = $subscription ? $usage->used($subscription, $key) : 0;
        }
        $period = data_get($subscription?->bundle?->meta, 'allowance_period', 'monthly');
        $periodStart = $subscription ? $usage->periodStart($subscription, $period) : null;
        $items = collect($capacities + $allowances)->map(fn ($used, $key) => [
            'key' => $key,
            'kind' => array_key_exists($key, $allowances) ? 'allowance' : 'capacity',
            'name' => config('package_reference.features.'.$key.'.name_'.app()->getLocale(), $key),
            'used' => $used, 'limit' => $subscription ? $usage->limit($subscription, $key) : 0,
        ]);
        if ($workspaces->canOwn($user)) {
            $items->put('workspaces', [
                'key' => 'workspaces', 'kind' => 'capacity', 'name' => __('package_access.page.workspaces'),
                'used' => Workspace::where('owner_id', $user->id)->count(), 'limit' => $workspaces->limit($user),
            ]);
        }
        $data = [
            'active' => $access->active($subscription),
            'package' => $subscription?->bundle?->name,
            'status' => $subscription?->status,
            'started_at' => $subscription?->start_date?->toDateString(),
            'expires_at' => $subscription?->end_date?->toDateString(),
            'period_start' => $periodStart,
            // When monthly allowances reset next (never after the subscription ends).
            'period_end' => $periodStart && $period === 'monthly'
                ? Carbon::parse($periodStart)->addMonthNoOverflow()->min($subscription->end_date ?? now()->addYears(10))->toDateString()
                : $subscription?->end_date?->toDateString(),
            'allowance_period' => $period,
            'usage' => $items->all(),
        ];
        if ($request->expectsJson()) {
            return response()->json($data);
        }

        return view('admin.subscription.usage', compact('data'));
    }
}
