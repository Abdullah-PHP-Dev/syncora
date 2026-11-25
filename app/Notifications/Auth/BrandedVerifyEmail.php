<?php

namespace App\Notifications\Auth;

use App\Mail\BrandedPlatformMail;
use App\Services\Auth\EmailVerification;
use App\Services\Notifications\RecipientContext;
use App\Services\PlatformEmails\EmailBranding;
use App\Services\PlatformEmails\EmailTemplates;
use App\Services\PlatformEmails\TemplateMailBuilder;
use App\Services\PlatformEmails\TemplateRegistry;
use App\Services\PlatformEmails\TemplateValidator;
use App\Services\PlatformEmails\TemplateVariables;
use Illuminate\Notifications\Notification;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;

/**
 * SEC-01: the verification email - a "Verify My Email" link and a six-digit code.
 *
 * Content comes from the administrator's published template `auth.verify_email` (Email Templates),
 * else the built-in default. A published version missing the code or the link is never sent (the
 * default is used instead), so editing a template can't make verification impossible.
 * The code and token exist only in this email; the database keeps hashes (EmailVerification).
 */
class BrandedVerifyEmail extends Notification
{
    public function __construct(private string $code, private string $token) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        $locale = RecipientContext::locale($notifiable);
        $url = rescue(fn () => LaravelLocalization::getLocalizedURL($locale, route('verification.link', ['token' => $this->token]), [], true), null, false)
            ?: route('verification.link', ['token' => $this->token]);
        $brand = EmailBranding::get();

        $vars = TemplateVariables::general($notifiable, null, $url, $locale) + [
            'verification_url' => $url,
            'verification_otp' => $this->code,
            'otp_expiry' => (string) EmailVerification::CODE_MINUTES,
            'expire_minutes' => (string) EmailVerification::LINK_MINUTES,
            'company_name' => (string) $brand['app_name'],
            'company_logo' => (string) $brand['logo_url'],
        ];

        $content = EmailTemplates::published('auth.verify_email', $locale);
        if (! $content || TemplateValidator::missingRequired('auth.verify_email', $content) !== []) {
            $content = app(TemplateRegistry::class)->defaults('auth.verify_email', $locale);
        }
        $layout = app(TemplateMailBuilder::class)->layout('auth.verify_email', $locale, $content, $vars);

        return (new BrandedPlatformMail($layout, $locale))->to($notifiable->email);
    }
}
