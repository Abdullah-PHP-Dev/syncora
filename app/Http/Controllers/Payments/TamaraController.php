<?php

namespace App\Http\Controllers\Payments;

use App\Http\Controllers\Controller;
use App\Models\TamaraOrder;
use App\Services\Payments\TamaraPayment;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\Request;
use Throwable;

class TamaraController extends Controller
{
    public function webhook(Request $request, TamaraPayment $payment)
    {
        $secret = config('services.payment.tamara.notification_token');
        $token = $request->bearerToken() ?: $request->query('tamaraToken');
        abort_unless(is_string($secret) && $secret !== '' && is_string($token), 401);
        try {
            JWT::decode($token, new Key($secret, 'HS256'));
        } catch (Throwable $e) {
            abort(401, 'Invalid Tamara notification signature.');
        }
        $data = $request->validate(['order_id' => ['required', 'string', 'max:255']]);
        $order = TamaraOrder::where('gateway_order_id', $data['order_id'])->first();
        // A notification may arrive before the checkout response is persisted; request a retry.
        if (!$order) {
            return response()->json(['message' => 'Order not yet available.'], 503);
        }
        try {
            $payment->synchronize($order);
        } catch (Throwable $e) {
            return response()->json(['message' => 'Payment confirmation temporarily unavailable.'], 503);
        }
        return response()->json(['success' => true]);
    }

    public function returned(Request $request, TamaraOrder $order, TamaraPayment $payment)
    {
        abort_unless((int) $request->user()->id === (int) $order->user_id, 403);
        try {
            $payment->synchronize($order);
        } catch (Throwable $e) {
            // The webhook and scheduled reconciliation can finish a delayed confirmation.
        }
        $order->refresh();
        return response()->view('payments.tamara-result', [
            'order' => $order, 'result' => $request->query('result'),
        ])->header('Cache-Control', 'no-store');
    }
}
