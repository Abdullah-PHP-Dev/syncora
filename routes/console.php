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
