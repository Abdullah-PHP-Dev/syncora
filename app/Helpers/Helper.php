<?php

use App\Models\Admin\AdminSetting;


function lang()
{
	return LaravelLocalization::getCurrentLocale();
}


if (! function_exists('adminSetting')) {

    /**
     * Get setting value.
     *
     * set_adminSetting() below only ever runs json_encode() on arrays/
     * objects - every scalar (string, "true"/"false", a bare number) is
     * stored as the raw value, unquoted. Unconditionally json_decode()ing
     * on the way back out therefore silently mis-parses any scalar that
     * happens to also be valid JSON on its own: a Slack OAuth client_id
     * like "11910619340784.11910624460400" is syntactically a JSON
     * number, so it decoded to a float and lost everything past ~17
     * significant digits ("11910619340784.12"), which is exactly why
     * Slack's authorize screen said "Invalid client_id parameter" even
     * though the stored value was correct. Only attempting the decode
     * when the value actually looks like the JSON array/object
     * set_adminSetting() produces keeps that case working while leaving
     * every plain scalar - numeric-looking or not - untouched.
     */
    function adminSetting(string $key, $default = null)
    {
        $setting = AdminSetting::where('key', $key)->first();

        if (! $setting) {
            return $default;
        }

        $value = $setting->value;
        $trimmed = ltrim((string) $value);

        if ($trimmed !== '' && ($trimmed[0] === '[' || $trimmed[0] === '{')) {
            $decoded = json_decode($value, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }

        return $value;
    }
}

if (! function_exists('set_adminSetting')) {

    /**
     * Create or update setting
     */
    function set_adminSetting(string $key, $value): bool
    {
        AdminSetting::updateOrCreate(
            ['key' => $key],
            [
                'value' => is_array($value) || is_object($value)
                    ? json_encode($value)
                    : $value
            ]
        );

        return true;
    }
}

if (! function_exists('deleteAdminSetting')) {

    /**
     * Delete setting
     */
    function deleteAdminSetting(string $key): bool
    {
        return AdminSetting::where('key', $key)->delete();
    }
}
