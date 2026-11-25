<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * A platform email built from already rendered text, in the shared branded layout.
 * Used for the account emails Laravel sends itself (password reset, email verification)
 * and for digests.
 */
class BrandedPlatformMail extends Mailable
{
    use Queueable;

    public function __construct(public array $layout, string $locale)
    {
        $this->locale($locale);
    }

    public function envelope(): Envelope
    {
        return PlatformNotificationMail::brandedEnvelope($this->layout['subject']);
    }

    public function content(): Content
    {
        return new Content(html: 'emails.notifications.layout', text: 'emails.notifications.layout-text', with: $this->layout);
    }
}
