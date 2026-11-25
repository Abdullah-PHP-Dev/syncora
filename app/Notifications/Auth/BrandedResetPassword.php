<?php

namespace App\Notifications\Auth;

use App\Mail\BrandedPlatformMail;
use App\Mail\PlatformNotificationMail;
use App\Services\Notifications\RecipientContext;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Bus\Queueable;

/** SEC-03: replaces Laravel's unbranded English-only reset email. Sent immediately when requested. */
class BrandedResetPassword extends ResetPassword
{
    use Queueable;


    public function toMail($notifiable)
    {
        $locale = RecipientContext::locale($notifiable);
        $minutes = (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60);
        $t = fn ($k, $p = []) => trans('notifications.auth.'.$k, $p, $locale);
        $url = \Mcamara\LaravelLocalization\Facades\LaravelLocalization::getLocalizedURL($locale,
            url(route('password.reset', ['token' => $this->token, 'email' => $notifiable->getEmailForPasswordReset()], false)), [], true);

        // An administrator's published template (Email Templates) replaces the built-in wording.
        if ($custom = \App\Services\PlatformEmails\EmailTemplates::published('auth.password_reset', $locale)) {
            $vars = \App\Services\PlatformEmails\TemplateVariables::general($notifiable, null, $url, $locale) + ['expire_minutes' => (string) $minutes];
            $layout = app(\App\Services\PlatformEmails\TemplateMailBuilder::class)->layout('auth.password_reset', $locale, $custom, $vars);
            $layout['cta_url'] ??= $url;

            return (new BrandedPlatformMail($layout, $locale))->to($notifiable->email);
        }

        return (new BrandedPlatformMail(PlatformNotificationMail::layoutData($locale, 'critical', [
            'subject' => $t('reset_subject'), 'preheader' => $t('reset_preheader'), 'headline' => $t('reset_headline'),
            'body' => $t('reset_body', ['email' => $notifiable->email]), 'cta' => $t('reset_cta'), 'note' => $t('reset_note', ['minutes' => $minutes]),
        ], ['cta_url' => $url, 'category' => trans('notifications.categories.security', [], $locale)]), $locale))
            ->to($notifiable->email);
    }
}
