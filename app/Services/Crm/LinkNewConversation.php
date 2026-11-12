<?php

namespace App\Services\Crm;

use App\Models\Messaging\Conversation;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * A new Inbox conversation from a handle the CRM already knows (a contact's
 * saved WhatsApp number, IG id…) is linked to that contact as it arrives.
 * Runs on the Conversation model's "created" event, so the messaging code is
 * untouched, and it never lets a CRM problem break message intake.
 */
class LinkNewConversation
{
    public function __construct(private ContactMatcher $matcher) {}

    public function __invoke(Conversation $conversation): void
    {
        try {
            if ($conversation->crm_contact_id || ! $conversation->customer_external_id || ! Schema::hasTable('crm_contact_identities')) {
                return;
            }
            $workspaceId = (int) $conversation->channel()->value('user_id');
            if (! $workspaceId) {
                return;
            }
            $contact = $this->matcher->identityFor($conversation, $workspaceId);
            // Optional (CRM settings): every new conversation becomes a contact.
            if (! $contact && CrmSettings::for($workspaceId)['auto_create_contacts'] && \App\Models\User::find($workspaceId)) {
                $contact = \Illuminate\Support\Facades\DB::transaction(function () use ($conversation, $workspaceId) {
                    [$first, $last] = array_pad(explode(' ', trim($conversation->customer_name ?: (string) $conversation->customer_external_id), 2), 2, null);
                    $owner = \App\Models\User::find($workspaceId);
                    $contact = \App\Models\Crm\Contact::create([
                        'workspace_id' => $workspaceId, 'first_name' => mb_substr($first ?: '?', 0, 80), 'last_name' => $last ? mb_substr($last, 0, 80) : null,
                        'phone' => $conversation->platform === 'whatsapp' ? $conversation->customer_external_id : null,
                        'source' => $conversation->platform, 'preferred_channel' => $conversation->platform, 'lifecycle' => 'lead',
                        'owner_id' => $owner->workspace_owner_id ?: $owner->id, 'last_activity_at' => now(),
                    ]);
                    \App\Models\Crm\ContactIdentity::create(['workspace_id' => $workspaceId, 'contact_id' => $contact->id, 'platform' => $conversation->platform,
                        'external_id' => (string) $conversation->customer_external_id, 'handle' => $conversation->customer_name]);

                    return $contact;
                });
            }
            if ($contact) {
                $conversation->forceFill(['crm_contact_id' => $contact->id])->saveQuietly();
            }
        } catch (\Throwable $e) {
            Log::warning('CRM auto-link skipped.', ['conversation' => $conversation->id, 'error' => $e->getMessage()]);
        }
    }
}
