<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class ApiService
{
    protected $httpClient;

    public function __construct()
    {
        set_time_limit(0);

        $this->httpClient = Http::class;
    }
    /**
     * Send GET request
     */
    public function get(string $endpoint,array $headers = [],array $payload = [],string $type = '') {
        return $this->sendRequest('GET',$endpoint,$headers,$payload,$type);
    }
    /**
     * Send POST request
     */
    public function post(string $endpoint,array $headers = [],array $payload = [],string $type = '', array $files = []) {
        return $this->sendRequest('POST',$endpoint,$headers,$payload,$type, $files);
    }
    /**
     * Send PUT request
     */
    public function put(string $endpoint,array $headers = [],array $payload = [],string $type = '') {
        return $this->sendRequest('PUT', $endpoint, $headers,$payload,$type);
    }
    /**
     * Send PATCH request
     */
    public function patch(string $endpoint,array $headers = [],array $payload = [],string $type = '') {
        return $this->sendRequest('PATCH',$endpoint,$headers,$payload,$type);
    }
    /**
     * Send DELETE request
     */
    public function delete(string $endpoint, array $headers = [],array $payload = [], string $type = ''
    ) {
        return $this->sendRequest('DELETE',$endpoint,$headers,$payload,$type);
    }
    /**
     * Main API Handler
     */
    protected function sendRequest(string $method,string $endpoint,array $headers = [],array $payload = [],string $type = '', array $files = []) {
        try {
            $client = $this->httpClient::timeout(300)->withHeaders($headers);
            /**
             * Request Body Type
             */ 
         
            switch ($type) {
                case 'json':
                    $client = $client->asJson();
                    break;
                case 'form':
                    $client = $client->asForm();
                    break;
                case 'multipart':
                    foreach ($files as $file) {
                        $client = $client->attach(
                            $file['name'],
                            fopen($file['media_file'], 'r'),
                            $file['file_name']
                        );
                    }
                    break;
                default:
                    $client = $client->asJson();
                    break;
            }
            /**
             * HTTP Method
             */
            $response = match(strtoupper($method)) {
                'GET' => $client->get(
                    $endpoint,
                    $payload
                ),
                'POST' => $client->post(
                    $endpoint,
                    $payload
                ),
                'PUT' => $client->put(
                    $endpoint,
                    $payload
                ),
                'PATCH' => $client->patch(
                    $endpoint,
                    $payload
                ),


                'DELETE' => $client->delete(
                    $endpoint,
                    $payload
                ),
                default => throw new Exception(
                    "Invalid HTTP method"
                )
            };
            /**
             * Response
             */
           
            return [
                'success' => $response->successful(),
                'status' => $response->status(),
                'data' => $response->json(),
                'body' => $response->body()
            ];
        } catch(Exception $e) {
            Log::error('API Service Error', [
                'url'=>$endpoint,
                'method'=>$method,
                'error'=>$e->getMessage()
            ]);
            return [
                'success'=>false,
                'error'=>$e->getMessage()
            ];
        }
    }

    /**
     * Create update Model
    */
    public function success(array $data, $condition, $model)
    {
        if (empty($condition)) {
            $record = $model::create($data);
        } else {
            $record = $model::updateOrCreate($condition, $data);
        }
        
        return [
            'success' => true,
            'data'    => $record
        ];
    }
}