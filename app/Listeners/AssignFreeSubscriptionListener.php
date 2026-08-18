<?php

namespace App\Listeners;

use App\Events\UserRegistered;
use App\Services\SubscriptionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class AssignFreeSubscriptionListener
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
    public function handle(UserRegistered $event): void
    {
        app(SubscriptionService::class)
            ->assignFreeTrial($event->user);
    }
}
