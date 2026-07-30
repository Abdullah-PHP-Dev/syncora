<?php

namespace App\Services\MessagingServices;

use App\Models\Messaging\Conversation;

/**
 * Platform dispatcher for the unified inbox, mirroring
 * SocialAdManagerService's $platformMap pattern from the ad campaign
 * module - one map is the single source of truth for which service class
 * handles which platform.
 *
 * Webhook controllers talk to their platform's service directly (each
 * webhook URL is already platform-specific, so there's nothing to
 * dispatch), this class is only needed where the platform isn't known
 * until runtime - sending a reply from the admin inbox, where the target
 * platform comes from the conversation being replied to.
 */
class MessagingManagerService
{
    private array $platformMap = [
        'facebook'  => FacebookMessengerService::class,
        'instagram' => InstagramMessengerService::class,
        'whatsapp'  => WhatsAppMessagingService::class,
        'telegram'  => TelegramMessagingService::class,
        'x'         => XMessagingService::class,
    ];

    public function service(string $platform)
    {
        abort_unless(array_key_exists($platform, $this->platformMap), 404);

        return app($this->platformMap[$platform]);
    }

    public function send(Conversation $conversation, array $data)
    {
        return $this->service($conversation->platform)->sendMessage($conversation, $data);
    }
}
