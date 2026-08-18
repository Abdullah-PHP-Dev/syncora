<?php

namespace App\Providers;

use App\Events\NewTicketMessage;
use App\Events\SlaBreached;
use App\Events\TicketAssigned;
use App\Events\TicketCreated;
use App\Events\UserRegistered;
use App\Listeners\AssignFreeSubscriptionListener;
use App\Listeners\AssignSellerRoleListener;
use App\Listeners\BroadcastChatMessage;
use App\Listeners\BroadcastTicketUpdate;
use App\Listeners\CreateShopForUser;
use App\Listeners\EscalateTicketPriority;
use App\Listeners\NotifyAdmins;
use App\Listeners\NotifyAssignedAgent;
use App\Listeners\NotifyDepartmentAgents;
use App\Listeners\StartSlaTimer;
use App\Listeners\UpdateUnreadCounters;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        UserRegistered::class => [
            AssignSellerRoleListener::class,
            AssignFreeSubscriptionListener::class,

        ],

    ];
}
