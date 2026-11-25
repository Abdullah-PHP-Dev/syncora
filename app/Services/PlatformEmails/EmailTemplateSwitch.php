<?php

namespace App\Services\PlatformEmails;

use App\Models\PlatformEmails\PlatformEmailTemplateSetting;
use Illuminate\Support\Facades\Cache;

/**
 * Administrator on/off per email event. Disabling stops the email only: the bell item,
 * other channels and the business action that caused it carry on unchanged.
 */
class EmailTemplateSwitch
{
    private const CACHE = 'email_template_switch';

    public static function enabled(string $key): bool
    {
        if (in_array($key, TemplateRegistry::LOCKED, true)) {
            return true;
        }
        $disabled = rescue(fn () => Cache::remember(self::CACHE, 300, fn () => PlatformEmailTemplateSetting::where('enabled', false)->pluck('key')->all()), [], false);

        return ! in_array($key, $disabled, true);
    }

    public static function set(string $key, bool $enabled, ?int $by): void
    {
        abort_if(in_array($key, TemplateRegistry::LOCKED, true) && ! $enabled, 422, __('platform_emails.locked'));
        PlatformEmailTemplateSetting::updateOrCreate(['key' => $key], ['enabled' => $enabled, 'updated_by' => $by]);
        Cache::forget(self::CACHE);
    }
}
