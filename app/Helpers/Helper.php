<?php

use App\Models\Admin\AdminSetting;

if (! function_exists('setting')) {

    /**
     * Get setting value
     */
    function adminSetting(string $key, $default = null)
    {
        $setting = AdminSetting::where('key', $key)->first();

        if (! $setting) {
            return $default;
        }

        return json_decode($setting->value, true) ?? $setting->value;
    }
}

if (! function_exists('set_setting')) {

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

if (! function_exists('delete_setting')) {

    /**
     * Delete setting
     */
    function delete_adminSetting(string $key): bool
    {
        return AdminSetting::where('key', $key)->delete();
    }
}