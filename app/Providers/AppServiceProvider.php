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
        // CRM: a message from a linked customer refreshes their last activity and lead score.
        \App\Models\Messaging\Message::created(function ($message) {
            try {
                $contactId = \Illuminate\Support\Facades\DB::table('conversations')->where('id', $message->conversation_id)->value('crm_contact_id');
                if ($contactId && ($contact = \App\Models\Crm\Contact::withoutGlobalScopes()->find($contactId))) {
                    $contact->forceFill(['last_activity_at' => now()])->saveQuietly();
                    app(\App\Services\Crm\LeadScorer::class)->rescore($contact);
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('CRM rescore on message skipped.', ['error' => $e->getMessage()]);
            }
        });
    }
}
