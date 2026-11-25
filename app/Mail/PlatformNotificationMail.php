<?php

namespace App\Mail;

use App\Models\Notifications\NotificationDelivery;
use App\Models\Notifications\NotificationEvent;
use App\Models\SellerTeamMember;
use App\Models\User;
use App\Models\Workspace;
use App\Services\PlatformEmails\EmailBranding;
use App\Services\PlatformEmails\EmailTemplates;
use App\Services\PlatformEmails\TemplateRenderer;
use App\Services\PlatformEmails\TemplateVariables;
use App\Services\Notifications\Links;
use App\Services\Notifications\NotificationText;
use App\Services\Notifications\RecipientContext;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;

/**
 * One platform notification email (or one grouped email for several events of the
 * same kind). Rendered in the recipient's language and timezone with the shared
 * branded layout (resources/views/emails/notifications/layout.blade.php).
 */
class PlatformNotificationMail extends Mailable
{
    use Queueable;

    public array $layout = [];

    /** 'default' or the administrator template version used (recorded in the email log). */
    public string $templateVersion = 'default';

    private NotificationEvent $lead;

    private ?User $user;

    private array $config;

    /** @param NotificationDelivery[] $deliveries */
    public function __construct(array $deliveries, array $transient = [])
    {
        $first = $deliveries[0];
        $this->lead = $first->notificationEvent;
        $this->user = $first->user;
        $this->config = $this->lead->config();
        $locale = $first->locale ?: RecipientContext::locale($this->user, $this->lead->workspace_id);
        $this->locale($locale);
        $tz = RecipientContext::timezone($this->user, $this->lead->workspace_id);
        $count = count($deliveries);
        $text = NotificationText::render($this->lead->event, $this->lead->payload ?? [], $locale, $tz, $count);
        $ctaUrl = isset($transient['url']) ? Links::onPublicRoot($transient['url']) : ($this->user ? Links::email($this->lead, $this->user) : Links::localized($this->lead->payload['url'] ?? null, $locale));
        if ($count > 1) {
            $ctaUrl = $this->user && ($this->lead->payload['group_url'] ?? null)
                ? Links::localized($this->lead->payload['group_url'], $locale) : $ctaUrl;
        }

        $details = $count === 1 ? $this->details($this->lead, $locale) : [];
        // An administrator's published template for this email and language replaces the
        // built-in wording (single emails; grouped emails keep the built-in group wording).
        // A template only exists per language, so a reader never gets another language's text.
        $custom = $count === 1 ? EmailTemplates::published($this->lead->event, $locale) : null;
        $overrides = [];
        if ($custom) {
            $vars = TemplateVariables::general($this->user, $this->workspaceName(), $ctaUrl, $locale, $first->to_address)
                + TemplateVariables::event($this->lead->payload ?? [], $locale, $tz);
            $rendered = app(TemplateRenderer::class)->render($custom, $vars, $locale, $details);
            $text = array_merge($text, [
                'subject' => $rendered['subject'] ?: $text['subject'], 'preheader' => $rendered['preheader'],
                'headline' => $rendered['headline'] ?: $text['headline'], 'cta' => $rendered['cta'], 'body' => null, 'note' => null,
            ]);
            $overrides = ['blocks_html' => $rendered['blocks_html'], 'secondary' => $rendered['secondary'], 'footer_custom' => $rendered['footer'], 'details' => []];
            $ctaUrl = $rendered['cta_url'] ?? $ctaUrl;
        }
        $this->templateVersion = $custom ? 'v'.$custom['_version'] : 'default';

        $this->layout = self::layoutData($locale, $this->config['tone'], $text, $overrides + [
            'cta_url' => $ctaUrl,
            'details' => $details,
            'items' => $count > 1 ? collect($deliveries)->map(function (NotificationDelivery $d) use ($locale, $tz) {
                $event = $d->notificationEvent;
                $t = NotificationText::render($event->event, $event->payload ?? [], $locale, $tz);

                return ['title' => $t['title'], 'body' => $t['body'], 'url' => $this->user ? Links::email($event, $this->user) : null,
                    'when' => NotificationText::date($event->occurred_at, $locale, $tz)];
            })->all() : [],
            'workspace' => $this->workspaceName(),
            'category' => trans('notifications.categories.'.$this->config['category'], [], $locale),
            'manage_url' => $this->user ? Links::preferences($this->user, $this->config['category']) : null,
            'unsubscribe_url' => $this->unsubscribeUrl(),
        ]);
    }

    /** Shared by every platform email (also the branded reset / verify / digest mail). */
    public static function layoutData(string $locale, string $tone, array $text, array $extra = []): array
    {
        $tones = ['success' => '#16A34A', 'info' => '#2563EB', 'warning' => '#D97706', 'action' => '#7C3AED', 'critical' => '#DC2626', 'report' => '#0F172A'];
        $brand = EmailBranding::get();
        $brand['copyright'] = app(TemplateRenderer::class)->plain((string) $brand['copyright'], ['current_year' => date('Y'), 'application_name' => $brand['app_name']]);

        return array_merge([
            'brand' => $brand, 'blocks_html' => null, 'footer_custom' => null, 'test' => false,
            'locale' => $locale, 'rtl' => $locale === 'ar', 'tone' => $tone, 'color' => $tones[$tone] ?? $tones['info'],
            'chip' => trans('notifications.tones.'.$tone, [], $locale),
            'subject' => $text['subject'] ?? '', 'preheader' => $text['preheader'] ?? '', 'headline' => $text['headline'] ?? '',
            'body' => $text['body'] ?? null, 'cta' => $text['cta'] ?? null, 'note' => $text['note'] ?? null,
            'details' => [], 'items' => [], 'sections' => [], 'workspace' => null, 'category' => null,
            'manage_url' => null, 'unsubscribe_url' => null, 'cta_url' => null, 'secondary' => null,
        ], $extra);
    }

    public function envelope(): Envelope
    {
        $subject = $this->layout['subject'];
        if ($this->layout['workspace'] && $this->user && $this->worksInSeveral()) {
            $subject .= ' — '.$this->layout['workspace'];
        }

        return self::brandedEnvelope($subject);
    }

    /** Sender details from the administrator's email branding (config is the fallback). */
    public static function brandedEnvelope(string $subject): Envelope
    {
        $brand = EmailBranding::get();

        return new Envelope(from: new Address($brand['sender_email'], $brand['sender_name']), replyTo: [new Address($brand['reply_to'])], subject: $subject);
    }

    public function content(): Content
    {
        return new Content(html: 'emails.notifications.layout', text: 'emails.notifications.layout-text', with: $this->layout);
    }

    public function headers(): Headers
    {
        $headers = ['X-Entity-Ref-ID' => 'slz-'.$this->lead->id];
        if ($this->layout['unsubscribe_url']) {
            $headers['List-Unsubscribe'] = '<'.$this->layout['unsubscribe_url'].'>';
            $headers['List-Unsubscribe-Post'] = 'List-Unsubscribe=One-Click';
        }

        return new Headers(text: $headers);
    }

    /** Optional mail only: one-click unsubscribe never appears on security or other mandatory mail. */
    private function unsubscribeUrl(): ?string
    {
        return $this->user && ! $this->config['mandatory'] ? Links::unsubscribe($this->lead, $this->user) : null;
    }

    private function details(NotificationEvent $event, string $locale): array
    {
        return collect($event->payload['params'] ?? [])
            ->only(['platform', 'account', 'reason', 'plan', 'amount', 'ticket', 'campaign', 'source', 'channel'])
            ->filter(fn ($v) => $v !== null && $v !== '')
            ->map(fn ($v, $k) => [trans('notifications.details.'.$k, [], $locale), (string) $v])->values()->all();
    }

    private function workspaceName(): ?string
    {
        $id = $this->lead->workspace_id;
        if (! $id) {
            return null;
        }

        return rescue(fn () => Workspace::where('account_id', $id)->value('name'), null, false) ?? User::whereKey($id)->value('name');
    }

    private function worksInSeveral(): bool
    {
        return rescue(fn () => Workspace::where('owner_id', $this->user->id)->count() > 1
            || SellerTeamMember::where('user_id', $this->user->id)->whereNotNull('accepted_at')->exists(), false, false);
    }
}
