<?php

namespace App\Services\PlatformEmails;

use App\Models\User;
use App\Services\Notifications\NotificationText;

/** Builds the variable values one email is rendered with (general + the event's own data). */
class TemplateVariables
{
    public static function general(?User $user, ?string $workspace, ?string $actionUrl, string $locale, ?string $email = null): array
    {
        $brand = EmailBranding::get();

        return [
            'customer_name' => (string) ($user?->name ?? ''),
            'customer_email' => (string) ($user?->email ?? $email ?? ''),
            'workspace_name' => (string) ($workspace ?? ''),
            'application_name' => (string) $brand['app_name'],
            'application_url' => (string) $brand['website_url'],
            'support_email' => (string) $brand['support_email'],
            'current_date' => now()->locale($locale)->translatedFormat('j M Y'),
            'current_year' => now()->format('Y'),
            'action_url' => (string) ($actionUrl ?? ''),
        ];
    }

    /** Event data, formatted for the reader (dates in their timezone, names in their language). */
    public static function event(array $payload, string $locale, string $timezone): array
    {
        return NotificationText::params($payload, $locale, $timezone);
    }
}
