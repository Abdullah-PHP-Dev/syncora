<?php

namespace App\Services;

use App\Models\Shop;
use App\Models\ShopSetting;
use Illuminate\Support\Str;

class ShopProvisionService
{
    /**
     * Create a new class instance.
     */
    public function __construct()
    {
        //
    }

    public function createForUser($user)
    {

        $shop = Shop::firstOrCreate(
            ['user_id' => $user->id],
            [
                'name' => $user->name . "'s Shop",
                'slug' => Str::slug($user->name) . '-' . $user->id . '-' . uniqid(),
            ]
        );

        ShopSetting::firstOrCreate(
            ['shop_id' => $shop->id],
            [
                'currency' => 'SAR',
            ]
        );

        return $shop;
    }
}
