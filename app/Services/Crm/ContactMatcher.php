<?php

namespace App\Services\Crm;

use App\Models\Crm\Contact;
use App\Models\Crm\ContactIdentity;
use App\Models\Messaging\Conversation;
use Illuminate\Support\Collection;

/** One person, one record: the phone (last 9 digits) and email are the merge keys. */
class ContactMatcher
{
    public static function phoneKey(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);

        return strlen($digits) >= 7 ? substr($digits, -9) : null;
    }

    /** Existing contacts in this workspace with the same phone or email. */
    public function duplicates(?string $phone, ?string $email, ?int $exceptId = null): Collection
    {
        $key = self::phoneKey($phone);
        $email = $email ? mb_strtolower(trim($email)) : null;
        if (! $key && ! $email) {
            return collect();
        }

        // Every contact in the workspace counts, even ones this teammate can't open.
        return Contact::withoutGlobalScope('visibility')
            ->where(function ($q) use ($key, $email) {
                if ($key) {
                    $q->orWhere('phone_key', $key);
                }
                if ($email) {
                    $q->orWhere('email', $email);
                }
            })
            ->when($exceptId, fn ($q) => $q->whereKeyNot($exceptId))
            ->limit(5)->get(['id', 'first_name', 'last_name', 'phone', 'email', 'lifecycle', 'source', 'created_at']);
    }

    /** The linked contact of a conversation, by an identity saved earlier. */
    public function identityFor(Conversation $conversation, int $workspaceId): ?Contact
    {
        if (! $conversation->customer_external_id) {
            return null;
        }

        return ContactIdentity::forWorkspace($workspaceId)
            ->where('platform', $conversation->platform)
            ->where('external_id', (string) $conversation->customer_external_id)
            ->first()?->contact()->withoutGlobalScopes()->whereNull('deleted_at')->first();
    }

    /** Suggested matches for an unlinked conversation: phone first (WhatsApp ids are numbers), then name. */
    public function suggestionsFor(Conversation $conversation): Collection
    {
        $matches = collect();
        if ($conversation->platform === 'whatsapp' && ($key = self::phoneKey($conversation->customer_external_id))) {
            $matches = Contact::where('phone_key', $key)->limit(3)->get()->each(fn ($c) => $c->setAttribute('match', 'phone'));
        }
        $name = trim((string) $conversation->customer_name);
        if ($name !== '' && $matches->count() < 3) {
            $first = explode(' ', $name)[0];
            $byName = Contact::where(fn ($q) => $q->where('first_name', 'like', $first.'%')->orWhere('arabic_name', 'like', '%'.$name.'%'))
                ->whereNotIn('id', $matches->pluck('id'))->limit(3 - $matches->count())->get()
                ->each(fn ($c) => $c->setAttribute('match', 'name'));
            $matches = $matches->concat($byName);
        }

        return $matches;
    }
}
