<?php

namespace App\Services\Payments\Gateways;

use Illuminate\Support\Facades\Http;

class TapGateway extends  BaseGateway
{
    private string $baseUrl = 'https://api.tap.company/v2/';

    private string $secretKey;

    public function __construct()
    {
        $this->secretKey = config('services.payment.tap.secret_key');

    }

    /**
     * STEP 1: Create payment charge
     */
    public function pay(array $data): array
    {


        /*$transaction = $data['transaction'];
        $payLoad = $this->prepareData($data);*/
        $des       = 'des';
        // $returnUrl = $data['transaction']->subject->callback;
        $returnUrl = url('callback/payment');//$this->callbackUrl();
        $body = [
            'amount'       => 123, //$payLoad['transaction']['total'],
            'currency'     => 'SAR',
            "threeDSecure" => true,
            "save_card"    => 0, //$payLoad['customerInfo']['save_card'],
            'description'  => $des,
            'redirect_url' => $returnUrl,
            "metadata"     => [
                "udf1" => $des
            ],

            "reference" =>  [
                "transaction" =>  'WA3321', //$payLoad['transaction']['ref_number'],
                "order" => 'WA3321', //$payLoad['transaction']['ref_number']
            ] ,
            "receipt"      => [
                "email" => true,
                "sms"   => true
            ],
            'customer'     => [
                'first_name'  => 'Zahid Madni', //$payLoad['customerInfo']['name'],
                'middle_name'  => 'Zahid Madni', //$payLoad['customerInfo']['name'],
                'last_name'  => 'Zahid Madni', //$payLoad['customerInfo']['name'],
                'email'       => 'muhammadzahidmadni@gmail.com', //$payLoad['customerInfo']['email'],
                "phone"       => [
                    "country_code" => 'SA',
                    "number"       => '00966598166133', //$payLoad['customerInfo']['mobile'] ?? ''
                ]
            ],

            "source"   => [

                "id" => "src_all"
            ],
            "redirect" => [
                "url" => $returnUrl
            ]
        ];





        $response = Http::withHeaders([
                                          'Authorization' => 'Bearer ' . $this->secretKey,
                                          'Content-Type' => 'application/json',
                                      ])->post($this->baseUrl . '/charges', $body);



        if (!$response->successful()) {
            return [
                'status' => 'failed',
                'message' => $response->json()['message'] ?? 'Payment gateway failed'
            ];
        }

        $data = $response->json();


        $tapChargeId = $data['id'] ?? null;
        $redirectUrl = $data['transaction']['url'] ?? null;

        // IMPORTANT: store tap reference in your transaction
        /*$transaction->update([
                                 'status' => 'pending',
                                 'gateway_reference' => $tapChargeId,
                              /*   'gateway_reference' => $tapChargeId,*/
                               /*  'payment_method' => 'tap'*/
                            /* ]);*/

        return [
            'status' => 'pending',
            'redirect_url' => $redirectUrl,
            'transaction_id' => '123456', //$transaction->id,
            'session_id' => $tapChargeId
        ];
    }

    /**
     * STEP 2: Verify payment after callback
     */
    public function verify(array $data): bool
    {
        $tapChargeId = $data['gateway_reference'];
        $response = Http::withHeaders([
                                          'Authorization' => 'Bearer ' . $this->secretKey,
                                      ])->get($this->baseUrl . "/charges/{$tapChargeId}");


        if (!$response->successful()) {
            return false;
        }

        $result = $response->json();

        return isset($result['status']) && $result['status'] === 'CAPTURED';
    }
}
