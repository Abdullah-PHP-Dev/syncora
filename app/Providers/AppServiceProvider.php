<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Meta "Require App Secret": sign every Graph API call (see the class).
        \Illuminate\Support\Facades\Http::globalRequestMiddleware(new \App\Support\Http\MetaAppSecretProof());

        // CRM: link new Inbox conversations from known handles (see the class).
        \App\Models\Messaging\Conversation::created(fn ($conversation) => app(\App\Services\Crm\LinkNewConversation::class)($conversation));
    }
}
