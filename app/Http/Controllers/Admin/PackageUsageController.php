<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MediaAsset;
use App\Models\PostCategory;
use App\Models\SocialAccount;
use App\Services\PackageAccessService;
use App\Services\PackageUsageService;
use Illuminate\Http\Request;

class PackageUsageController extends Controller
{
    public function __invoke(Request $request, PackageAccessService $access, PackageUsageService $usage)
    {
        $user = $request->user();
        $subscription = $access->subscription($user);
        $capacities = [
            'team_members' => app(\App\Services\SellerTeamService::class)->seats($user),
            'publishing_accounts' => SocialAccount::where('user_id', $user->id)->where('has_posting_permission', true)->count(),
            'advertising_accounts' => SocialAccount::where('user_id', $user->id)->where('has_ads_permission', true)->count(),
            'content_categories' => PostCategory::where('user_id', $user->id)->count(),
            'media_storage_gb' => round(MediaAsset::withTrashed()->where('user_id', $user->id)->sum('size') / 1024 ** 3, 3),
        ];
        $allowances = [];
        foreach (['scheduled_published_posts', 'ai_credits', 'hashtag_generation'] as $key) {
            $allowances[$key] = $subscription ? $usage->used($subscription, $key) : 0;
        }
        $data = [
            'active' => $access->active($subscription),
            'package' => $subscription?->bundle?->name,
            'expires_at' => $subscription?->end_date?->toDateString(),
            'period_start' => $subscription ? $usage->periodStart($subscription, data_get($subscription->bundle?->meta, 'allowance_period', 'monthly')) : null,
            'allowance_period' => data_get($subscription?->bundle?->meta, 'allowance_period', 'monthly'),
            'usage' => collect($capacities + $allowances)->map(fn ($used, $key) => [
                'name' => config('package_reference.features.'.$key.'.name_'.app()->getLocale(), $key),
                'used' => $used, 'limit' => $subscription ? $usage->limit($subscription, $key) : 0,
            ])->all(),
        ];
        if ($request->expectsJson()) {
            return response()->json($data);
        }

        return view('admin.subscription.usage', compact('data'));
    }
}
