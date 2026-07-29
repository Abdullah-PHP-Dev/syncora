<?php

use App\Models\Admin\AdminSetting;

if (! function_exists('adminSetting')) {

    /**
     * Get setting value
     */
    function adminSetting(string $key, $default = null)
    {
        $setting = AdminSetting::where('key', $key)->first();

        if (! $setting) {
            return $default;
        }

        $decoded = json_decode($setting->value, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $setting->value;
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
            ['value' => json_encode($value)]
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
