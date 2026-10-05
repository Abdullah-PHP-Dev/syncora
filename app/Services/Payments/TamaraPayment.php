<?php

namespace App\Services\Payments;

use App\Libs\Api;
use App\Models\Subscription;
use App\Models\SubscriptionCycle;
use App\Models\TamaraOrder;
use App\Models\User;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use RuntimeException;
use Throwable;

class TamaraPayment implements PaymentInterface
{
    private function client(): PendingRequest
    {
        $token = config('services.payment.tamara.api_token');
        if (!$token) {
            throw new RuntimeException('Tamara sandbox credentials have not been configured.');
        }

        return Http::baseUrl( config('services.payment.tamara.base_url'))
            ->withToken($token)->acceptJson()->asJson()->connectTimeout(10)->timeout(30);
    }

    public function pay(array $data): array
    {





        $order = null;
        try {

            $client = $this->client();

	        $amount = (float) "500";
	        $vat              = ($amount * (15/100));
	        $amount = round($amount, 2);

	        $order['id'] = 1234456;
	        $body     = [
		        "total_amount"       => [
			        "amount"   => $amount,
			        "currency" => "SAR"
		        ],
		        "shipping_amount"    => [
			        "amount"   => 0,
			        "currency" => "SAR"
		        ],
		        "tax_amount"         => [
			        "amount"   => $vat,
			        "currency" => "SAR"
		        ],
		        "order_reference_id" => $order['id'],
		        "items"              => [
			        [
				        "name"         => "Bundle_type_",
				        "type"         => "Digital",
				        "reference_id" => $order['id'],
				        "sku"          => $order['id'],
				        "quantity"     => 1,
				        "total_amount" => [
					        "amount"   => $amount,
					        "currency" => "SAR"
				        ]
			        ]
		        ],
		        "consumer"           => [
			        "email"        => 'muhammadzahidmadni@gmail.com',
			        "first_name"   => 'Zahid Madni',
			        "last_name"    => 'Zahid Madni',
			        "phone_number" => '0598166133'
		        ],
		        "country_code"       => "SA",
		        "description"        => "Subscription Purchase",
		        "merchant_url"       => [
			        "cancel"       => route('tamara.checkout-status'),
			        "failure"      => route('tamara.checkout-status'),
			        "success"      => route('tamara.checkout-status'),
			        "notification" => route('tamara.checkout-status')
		        ],
		        "payment_type"       => "PAY_BY_INSTALMENTS",
		        "instalments"        => 3,
		        "shipping_address"   => [
			        "city"         => "Riyadh",
			        "country_code" => "SA",
			        "first_name"   => 'Zahid Madni',
			        "last_name"    => 'Zahid Madni',
			        "line1"        => "online",
		        ],
		        "platform"           => "Social Eaz",
		        "locale"             => "ar_SA",

	        ];



            $response = $client->post('/checkout', $body);


            $result = $response->json();
            $url = $result['checkout_url'] ?? '';




          /*  if (!$response->successful() || ($result['status'] ?? null) !== 'new' || empty($result['order_id']) ||
                parse_url($url, PHP_URL_SCHEME) !== 'https' ||
                !is_string($host) || !($host === 'tamara.co' || str_ends_with($host, '.tamara.co'))) {
                $order->update(['status' => 'failed']);
                return ['status' => 'failed', 'message' => 'Tamara could not create checkout. Please check your details or choose another payment method.'];
            }*/
           /* $order->update(['gateway_order_id' => $result['order_id'], 'checkout_id' => $result['checkout_id'] ?? null,
                'checkout_url' => $url, 'status' => 'new', 'items' => $body['items']]);*/

            return ['status' => 'pending', 'redirect_url' => $url, 'transaction_id' => $order['id']];
        } catch (Throwable $e) {

            return ['status' => 'failed', 'message' => config('services.payment.tamara.api_token')
                ? 'Unable to connect to Tamara. Please try again later.'
                : 'Tamara sandbox credentials have not been configured.'];
        }
    }

    public function verify(array $data): bool
    {
        $id = $data['gateway_reference'] ?? null;
        if (!is_string($id) || $id === '') {
            return false;
        }
        $order = TamaraOrder::where('gateway_order_id', $id)->first();

        return $order ? $this->synchronize($order) : false;
    }

    public function synchronize(TamaraOrder $order): bool
    {
        return DB::transaction(function () use ($order) {
            $order = TamaraOrder::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($order->paid_at) {
                return true;
            }
            if (!$order->gateway_order_id || $order->environment !== 'sandbox') {
                return false;
            }
            $client = $this->client();
            $remote = $this->details($client, $order);
            $status = $remote['status'];
            if (in_array($status, ['declined', 'expired', 'canceled', 'cancelled'], true)) {
                $order->update(['status' => $status]);
                return false;
            }
            if ($status === 'approved') {
                $client->post('/orders/'.$order->gateway_order_id.'/authorise')->throw();
                $remote = $this->details($client, $order);
                $status = $remote['status'];
            }
            if ($status === 'authorised') {
                $money = ['amount' => (float) $order->amount, 'currency' => $order->currency];
                $zero = ['amount' => 0, 'currency' => $order->currency];
                $capture = $client->post('/payments/capture', [
                    'order_id' => $order->gateway_order_id, 'total_amount' => $money,
                    'items' => $order->items, 'shipping_amount' => $zero, 'tax_amount' => $zero,
                    'discount_amount' => $zero,
                    'shipping_info' => ['shipped_at' => now()->toIso8601String(), 'shipping_company' => 'Digital delivery'],
                ])->throw()->json();
                $order->capture_id = $capture['capture_id'] ?? null;
                $remote = $this->details($client, $order);
                $status = $remote['status'];
            }
            $order->status = $status;
            $order->save();
            if ($status !== 'fully_captured') {
                return false;
            }
            // Also lock the user: separate checkout attempts for one user must not race.
            User::whereKey($order->user_id)->lockForUpdate()->firstOrFail();
            $start = now();
            $months = $order->cycle === 'yearly' ? 12 : 1;
            $end = $start->copy()->addMonthsNoOverflow($months);
            $subscription = Subscription::updateOrCreate(['user_id' => $order->user_id], [
                'bundle_id' => $order->bundle_id, 'bundle_name' => $order->bundle_name,
                'billing_period' => $months, 'start_date' => $start, 'end_date' => $end,
                'status' => 'active', 'is_active' => true,
            ]);
            SubscriptionCycle::create([
                'subscription_id' => $subscription->id, 'user_id' => $order->user_id,
                'bundle_id' => $order->bundle_id, 'start_date' => $start, 'end_date' => $end,
                'type' => 'purchase', 'status' => 'active',
            ]);
            $order->update(['paid_at' => now()]);
            return true;
        });
    }

    private function details(PendingRequest $client, TamaraOrder $order): array
    {
        $remote = $client->get('/orders/'.$order->gateway_order_id)->throw()->json();
        if (($remote['order_id'] ?? null) !== $order->gateway_order_id ||
            ($remote['order_reference_id'] ?? null) !== $order->id ||
            ($remote['total_amount']['currency'] ?? null) !== $order->currency ||
            !isset($remote['total_amount']['amount']) ||
            (int) round((float) $remote['total_amount']['amount'] * 100) !== (int) round((float) $order->amount * 100) ||
            !is_string($remote['status'] ?? null)) {
            throw new RuntimeException('Tamara order does not match the stored payment.');
        }
        return $remote;
    }
}
