<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Recover confirmations missed during a webhook or browser-return outage.
\Illuminate\Support\Facades\Artisan::command('payments:reconcile-tamara', function () {
    $payment = app(\App\Services\Payments\TamaraPayment::class);
    \App\Models\TamaraOrder::whereNull('paid_at')->whereNotNull('gateway_order_id')
        ->where('environment', 'sandbox')
        ->whereIn('status', ['pending', 'new', 'approved', 'authorised', 'fully_captured'])
        ->where('created_at', '>=', now()->subDays(30))
        ->eachById(function ($order) use ($payment) {
            try {
                $payment->synchronize($order);
            } catch (\Throwable $e) {
                $this->warn('Confirmation pending for order '.$order->id);
            }
        }, 100);
})->purpose('Reconcile pending Tamara sandbox subscription payments');

\Illuminate\Support\Facades\Schedule::command('payments:reconcile-tamara')->everyFiveMinutes()->withoutOverlapping();

// CRM: time-based automation triggers (lead/deal idle). Event triggers run inline.
\Illuminate\Support\Facades\Artisan::command('crm:run-automations', function () {
    $this->info('Automation runs: '.app(\App\Services\Crm\AutomationEngine::class)->runIdle());
})->purpose('Run time-based CRM automations (idle leads and deals)');

\Illuminate\Support\Facades\Schedule::command('crm:run-automations')->hourlyAt(40)->withoutOverlapping();

// CRM: backfill lead ads missed by webhooks (Meta forms, TikTok Instant Forms).
\Illuminate\Support\Facades\Artisan::command('crm:sync-leads {--workspace=}', function () {
    $accounts = \App\Models\SocialAccount::whereIn('platform', ['facebook', 'tiktok'])
        ->when($this->option('workspace'), fn ($q, $w) => $q->where('user_id', $w))->get();
    foreach ($accounts as $account) {
        try {
            $result = $account->platform === 'facebook'
                ? (\App\Services\Crm\MetaLeads::granted($account) ? \App\Services\Crm\MetaLeads::sync($account) : null)
                : (($account->metadata['crm_lead_subscription_id'] ?? null) ? \App\Services\Crm\TikTokLeads::sync($account) : null);
            if ($result) {
                $this->line($account->platform.' '.$account->platform_account_id.': '.$result['created'].' new'.($result['error'] ? ' ('.$result['error'].')' : ''));
            }
        } catch (\Throwable $e) {
            $this->warn($account->platform.' '.$account->platform_account_id.': '.$e->getMessage());
        }
    }
})->purpose('Backfill Meta and TikTok lead ads into the CRM');

\Illuminate\Support\Facades\Schedule::command('crm:sync-leads')->everyThreeHours()->withoutOverlapping(60);
