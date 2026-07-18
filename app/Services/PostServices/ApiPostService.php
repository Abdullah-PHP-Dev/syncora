<?php
namespace App\Services\PostServices;

use Illuminate\Support\Facades\Http;

class ApiPostService
{
    public function request($method, $url, $headers = [], $body = [], $type = 'json')
    {
        $client = Http::withOptions([
            'timeout' => 300,
            'connect_timeout' => 60,
        ]);
        
        if (!empty($headers)) {
            $client = $client->withHeaders($headers);
        }
        if ($type === 'form') {
            $client = $client->asForm();
        }
        
        if ($method == 'post') {
            return $client->$method($url, $body);
        }

        return $client->$method($url, $body);
    }
}