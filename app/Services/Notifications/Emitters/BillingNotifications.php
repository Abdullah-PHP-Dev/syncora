<?php

namespace App\Services\Notifications\Emitters;

use App\Models\WalletTransaction;
use App\Services\Notifications\Notify;
use Illuminate\Support\Facades\DB;

/**
 * BIL-06/07/08/09 from verified transaction state (never from the browser): a receipt only
 * after fulfilment, a failure only when the gateway or reconciliation rejected it.
 * Amounts are exactly what was recorded; no tax lines are invented.
 */
class BillingNotifications
{
    public static function transactionUpdated(WalletTransaction $tx): void
    {
        if ($tx->category !== 'subscription' || ! $tx->seller_id) {
            return;
        }
        $meta = $tx->meta ?? [];
        $params = [
            'plan' => (string) ($meta['bundle_name'] ?? '—'),
            'amount' => Support::money($tx->amount, $meta['currency'] ?? null),
            'receipt' => (string) $tx->transaction_no,
        ];
        $url = Support::url('admin.subscription.plans');

        if ($tx->wasChanged('fulfilled_at') && $tx->fulfilled_at) {
            $end = DB::table('subscriptions')->where('user_id', $tx->seller_id)->orderByDesc('id')->value('end_date');
            Notify::event('billing.payment_succeeded', [
                'workspace' => $tx->seller_id, 'subject' => $tx, 'params' => $params, 'dates' => ['period_end' => $end],
                'url' => $url, 'dedup' => 'fulfilled',
            ]);
        }
        if ($tx->wasChanged('status') && $tx->status === 'rejected') {
            Notify::event('billing.payment_failed', [
                'workspace' => $tx->seller_id, 'subject' => $tx, 'params' => $params + ['reason' => Support::excerpt($tx->remarks, 160)],
                'url' => $url, 'dedup' => 'rejected',
            ]);
        }
    }
}
