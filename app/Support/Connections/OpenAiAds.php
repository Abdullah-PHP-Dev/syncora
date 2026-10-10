<?php

namespace App\Support\Connections;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * OpenAI Ads API (ChatGPT ads) - https://developers.openai.com/ads.
 * Bearer auth with an Ads API key; each key is scoped to ONE ad account
 * (created in Ads Manager > Settings at ads.openai.com). There is no OAuth:
 * the key is the credential, stored encrypted like other tokens.
 */
class OpenAiAds
{
    public const DEFAULT_BASE = 'https://api.ads.openai.com/v1';

    public static function baseUrl(): string
    {
        return rtrim((string) (adminSetting('ads.openai.base_url') ?: self::DEFAULT_BASE), '/');
    }

    public static function client(string $apiKey): PendingRequest
    {
        return Http::withToken($apiKey)->acceptJson()->timeout(30)->baseUrl(self::baseUrl());
    }

    /** GET /ad_account - confirms the key and returns the account it belongs to. */
    public static function adAccount(string $apiKey): Response
    {
        return self::client($apiKey)->get('/ad_account');
    }

    /** The API's own error message, for showing to the user. */
    public static function errorMessage(Response $response, string $fallback = 'OpenAI Ads API request failed.'): string
    {
        $message = $response->json('error.message') ?? $response->json('message') ?? $response->json('detail');

        return is_string($message) && $message !== '' ? $message : $fallback . ' (HTTP ' . $response->status() . ')';
    }
}
