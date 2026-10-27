<?php

namespace App\Http\Controllers\Payments;

use App\Http\Controllers\Controller;
use App\Models\WalletTransaction;
use App\Services\Payments\PaymentCallbackService;
use App\Services\Payments\PaymentCallbackUrl;
use Illuminate\Http\Request;
use Throwable;

class PaymentCallbackController extends Controller
{
    public function __invoke(Request $request, PaymentCallbackService $payments)
    {
        // Use our URL's correlation values, never the provider's POST body.
        $id = $request->query('transaction');

        $token = $request->query('token');
        if (! is_scalar($id) || ! ctype_digit((string) $id) || ! is_string($token) || strlen($token) !== 64) {
            return response()->view('payments.result', ['status' => 'invalid', 'refreshUrl' => null], 400)
                ->withHeaders($this->headers());
        }
        $transaction = WalletTransaction::find($id);

        abort_unless($transaction && is_string($transaction->meta['callback_token'] ?? null) &&
            hash_equals($transaction->meta['callback_token'], $token), 403);
        abort_unless($transaction->category === 'subscription', 422);
        try {
            $transaction = $payments->synchronize($transaction);
        } catch (Throwable $exception) {
            report($exception);
            // Timeouts and mismatches do not prove payment failure.
            $transaction->refresh();
        }
        $status = $transaction->fulfilled_at ? 'paid' : ($transaction->status === 'rejected' ? 'failed' : 'pending');
        if ($request->query('notification') === '1') {
            // Retry early notifications and temporarily unavailable confirmations.
            return response()->json(['status' => $status], $status === 'pending' ? 503 : 200, $this->headers());
        }
        $notice = match ($status) {
            'paid' => ['type' => 'success', 'title' => __('subscriptions.payment_success_title'),
                'message' => __('subscriptions.payment_success')],
            'failed' => ['type' => 'error', 'title' => __('subscriptions.payment_failed_title'),
                'message' => __('subscriptions.payment_failed')],
            default => ['type' => 'warning', 'title' => __('subscriptions.payment_pending_title'),
                'message' => __('subscriptions.payment_pending'),
                'retry' => PaymentCallbackUrl::forTransaction($transaction),
                'retry_label' => __('subscriptions.check_payment')],
        };

        return redirect()->route('admin.subscription.select', [], $request->isMethod('post') ? 303 : 302)
            ->with('payment_notice', $notice)->withHeaders($this->headers());
    }

    private function headers(): array
    {
        return ['Cache-Control' => 'no-store', 'Referrer-Policy' => 'no-referrer', 'X-Robots-Tag' => 'noindex, nofollow'];
    }
}
