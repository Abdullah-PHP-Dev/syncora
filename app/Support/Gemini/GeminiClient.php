<?php

namespace App\Support\Gemini;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Structured (JSON-schema) Gemini call, same model, key and retry policy
 * as the post composer's AI (PostController::generateAiContent). The key
 * never leaves the server.
 */
class GeminiClient
{
    public const MODEL = 'gemini-3.6-flash';

    /**
     * @return array{success: true, data: array}|array{success: false, message: string}
     */
    public static function json(string $prompt, array $schema): array
    {
        $apiKey = adminSetting('gemini_api_key');
        if (empty($apiKey)) {
            return ['success' => false, 'message' => 'AI isn’t configured yet - the Gemini API key is missing in admin settings.'];
        }

        // Retries can outlast PHP's default 30s budget (see PostController::generateAiContent).
        @set_time_limit(120);

        $response = Http::timeout(20)
            ->retry(3, fn ($attempt, $exception) => RetryPolicy::delayMs($attempt, $exception), fn ($exception) => RetryPolicy::isRetryable($exception), throw: false)
            ->post('https://generativelanguage.googleapis.com/v1beta/models/' . self::MODEL . ':generateContent?key=' . $apiKey, [
                'contents' => [['role' => 'user', 'parts' => [['text' => $prompt]]]],
                'generationConfig' => ['response_mime_type' => 'application/json', 'response_schema' => $schema, 'temperature' => 0.3],
            ]);

        if (!$response->successful()) {
            Log::warning('Gemini structured call failed.', ['status' => $response->status(), 'body' => mb_substr($response->body(), 0, 1000)]);

            return ['success' => false, 'message' => 'The AI service didn’t respond - please try again in a moment.'];
        }

        // The text part is itself a JSON string (response_mime_type), so decode it again.
        $raw = $response->json('candidates.0.content.parts.0.text');
        $data = $raw ? json_decode($raw, true) : null;

        if (!is_array($data)) {
            Log::warning('Gemini structured call returned an unexpected shape.', ['raw' => mb_substr((string) $raw, 0, 1000)]);

            return ['success' => false, 'message' => 'The AI returned an unexpected answer - please try again.'];
        }

        return ['success' => true, 'data' => $data];
    }
}
