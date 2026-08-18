<?php

namespace App\Listeners;

use App\Events\UserRegistered;
use App\Services\ShopProvisionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class CreateShopForUser
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    /*public function handle(UserRegistered $event)
    {
        app(ShopProvisionService::class)
            ->createForUser($event->user);
    }*/
}
