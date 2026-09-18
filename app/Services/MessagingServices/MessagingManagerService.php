<?php

namespace App\Services\MessagingServices;

use App\Models\Messaging\Conversation;
use App\Models\Messaging\Message;

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
        'facebook'    => FacebookMessengerService::class,
        'instagram'   => InstagramMessengerService::class,
        'whatsapp'    => WhatsAppMessagingService::class,
        'telegram'    => TelegramMessagingService::class,
        'x'           => XMessagingService::class,
        'line'        => LineMessagingService::class,
        'zalo'        => ZaloMessagingService::class,
        'discord'     => DiscordMessagingService::class,
        'slack'       => SlackMessagingService::class,
        'teams'       => TeamsMessagingService::class,
        'google_chat' => GoogleChatMessagingService::class,
        'matrix'      => MatrixMessagingService::class,
        'tiktok'      => TiktokMessagingService::class,
    ];

    /**
     * Only these platforms' APIs let a bot edit or delete a message it
     * already sent - confirmed against each platform's own docs, not
     * assumed. Facebook/Instagram/WhatsApp/X/LINE/Zalo have no such
     * endpoint at all (some only expose an inbound "the *customer*
     * unsent their message" webhook event, which is a different thing).
     * Kept as two separate lists rather than one, since a platform
     * supporting only one of the two isn't hypothetical - Telegram's own
     * delete has a 48-hour window edit doesn't share, so a future
     * platform could just as easily support one without the other.
     */
    private array $editCapablePlatforms = ['telegram', 'discord', 'slack', 'teams', 'google_chat', 'matrix'];
    private array $deleteCapablePlatforms = ['telegram', 'discord', 'slack', 'teams', 'google_chat', 'matrix'];

    public function service(string $platform)
    {
        abort_unless(array_key_exists($platform, $this->platformMap), 404);

        return app($this->platformMap[$platform]);
    }

    public function send(Conversation $conversation, array $data)
    {
        return $this->service($conversation->platform)->sendMessage($conversation, $data);
    }

    public function supportsEdit(string $platform): bool
    {
        return in_array($platform, $this->editCapablePlatforms, true);
    }

    public function supportsDelete(string $platform): bool
    {
        return in_array($platform, $this->deleteCapablePlatforms, true);
    }

    /**
     * Exposed so the inbox UI can build its edit/delete affordances from
     * the exact same list this class enforces server-side, rather than a
     * second hand-maintained copy - a hand-maintained platform list going
     * stale in one place while this one grew is exactly what already
     * happened once in this module's frontend (the inbox's platform
     * filter pills).
     */
    public function editCapablePlatforms(): array
    {
        return $this->editCapablePlatforms;
    }

    public function deleteCapablePlatforms(): array
    {
        return $this->deleteCapablePlatforms;
    }

    public function editMessage(Message $message, string $newBody)
    {
        $platform = $message->conversation->platform;

        if (!$this->supportsEdit($platform)) {
            return ['success' => false, 'error' => ucfirst($platform) . ' does not support editing a sent message.'];
        }

        return $this->service($platform)->editMessage($message, $newBody);
    }

    public function deleteMessage(Message $message)
    {
        $platform = $message->conversation->platform;

        if (!$this->supportsDelete($platform)) {
            return ['success' => false, 'error' => ucfirst($platform) . ' does not support deleting a sent message.'];
        }

        return $this->service($platform)->deleteMessage($message);
    }
}
