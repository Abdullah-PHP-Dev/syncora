<?php

namespace App\Support\Gemini;

use Illuminate\Http\Client\RequestException;

/**
 * Shared Http::retry() policy for every Gemini call in this app -
 * extracted out of PostController::generateAiContent() (where this was
 * first built and live-verified against real 429/503 responses) so the
 * new AI Copilot embedding calls don't duplicate/drift from the same
 * logic. See PostController::geminiRetryDelayMs()'s original docblock
 * for the full history: verified against Laravel's actual vendored
 * PendingRequest/RequestException source, and against real Gemini error
 * bodies (429 quota exhaustion, 503 transient overload) captured live
 * this session.
 *
 * Callers MUST still pair this with set_time_limit() before the
 * Http::retry() call and a capped per-attempt Http::timeout() - PHP's own
 * max_execution_time is a whole-request wall-clock budget, separate from
 * and not paused by Http::timeout() or retry()'s between-attempt sleeps
 * (this exact gap caused a real 500 instead of a graceful error earlier
 * this session - see PostController::generateAiContent()'s docblock).
 */
class RetryPolicy
{
    public static function isRetryable($exception): bool
    {
        return $exception instanceof RequestException
            && in_array($exception->getCode(), [429, 503], true);
    }

    /**
     * Capped at 12s - see PostController::geminiRetryDelayMs()'s
     * docblock for why an uncapped wait on Gemini's own suggested
     * retryDelay is unsafe to trust unbounded.
     */
    public static function delayMs(int $attempt, $exception): int
    {
        $delayMs = (2 ** $attempt) * 1000;

        if ($exception instanceof RequestException) {
            $details = $exception->response->json('error.details') ?? [];

            foreach ($details as $detail) {
                if (($detail['@type'] ?? null) === 'type.googleapis.com/google.rpc.RetryInfo' && !empty($detail['retryDelay'])) {
                    $delayMs = (int) ceil(((float) rtrim($detail['retryDelay'], 's')) * 1000);
                    break;
                }
            }
        }

        return min($delayMs, 12000);
    }
}
