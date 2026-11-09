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
        // Self-service sign-up already assigns the plan inside its transaction.
        if (!$event->user->hasRole('seller') || $event->user->isTeamMember() || $event->user->subscription()->exists()) {
            return;
        }

        app(SubscriptionService::class)
            ->assignFreeTrial($event->user);
    }
}
