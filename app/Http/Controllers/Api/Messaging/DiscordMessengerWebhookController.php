<?php

namespace App\Http\Controllers\Api\Messaging;

use App\Http\Controllers\Controller;
use App\Services\MessagingServices\DiscordMessagingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\PostComment;

class DiscordMessengerWebhookController extends Controller
{
    public function __construct(
        protected DiscordMessagingService $discordService,
    ) {
    }

    /**
     * Handles incoming Discord Webhooks and Interactions.
     * Validates the Ed25519 signature and acknowledges PING events.
     */
    public function receive(Request $request)
    {
        PostComment::updateOrCreate(
            ['platform' => 'discord', 'comment_id' => 'werewr'],
            [
                'content'           => json_encode($request->all()),
                'sender_type'       => 'customer',
                'user_id'           => 1,
                'user_name'         => 'Anonymous',
                'post_id'           => 152,
                'post_account_id'   => 21,
                'parent_comment_id' => '',
                'is_reply'          => false,
            ]
        );
        $signature = $request->header('X-Signature-Ed25519');
        $timestamp = $request->header('X-Signature-Timestamp');
        $body = $request->getContent();
        $publicKey = adminSetting('chats.discord.public_key') 
            ?? config('services.discord.public_key');

        // 1. Validate mandatory Ed25519 signature headers
        if (!$signature || !$timestamp || !$this->verifySignature($body, $signature, $timestamp, $publicKey)) {
            Log::warning('Discord webhook signature verification failed', ['ip' => $request->ip()]);

            return response()->json(['error' => 'Invalid request signature'], 401);
        }

        $payload = json_decode($body, true) ?? [];

        Log::info('Discord webhook payload received', ['payload' => $payload]);

        // 2. Respond to Discord PING / Verification Challenge (Type 1)
        if (isset($payload['type']) && (int)$payload['type'] === 1) {
            return response()->json(['type' => 1], 200);
        }

        // 3. Process incoming Discord events / messages
        $this->discordService->handleWebhookPayload($payload);

        return response()->json(['status' => 'success'], 200);
    }

    /**
     * Validates Discord Ed25519 Signature using sodium extension/library
     */
    private function verifySignature(string $body, string $signature, string $timestamp, ?string $publicKey): bool
    {
        if (empty($publicKey)) {
            Log::error('Discord Public Key is missing in app configuration.');
            return false;
        }

        try {
            return sodium_crypto_sign_verify_detached(
                sodium_hex2bin($signature),
                $timestamp . $body,
                sodium_hex2bin($publicKey)
            );
        } catch (\Throwable $e) {
            Log::error('Discord Signature Verification Exception', ['error' => $e->getMessage()]);
            return false;
        }
    }
}