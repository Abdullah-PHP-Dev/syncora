<?php

namespace App\Http\Controllers\Admin\Crm;

use App\Http\Controllers\Controller;
use App\Models\Crm\Activity;
use App\Models\Crm\Contact;
use App\Models\Crm\ContactIdentity;
use App\Models\Messaging\Conversation;
use App\Services\Crm\ContactMatcher;
use App\Services\Crm\CrmContext;
use App\Services\Crm\Timeline;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * The CRM side panel inside the existing Unified Inbox (M-01/M-02). It reads
 * and links conversations; it never sends, edits or re-routes messages.
 */
class CrmInboxController extends Controller
{
    use Presents;

    public function __construct(private CrmContext $context, private ContactMatcher $matcher, private Timeline $timeline) {}

    public function show(Conversation $conversation)
    {
        $this->authorizeConversation($conversation);

        // A handle linked before (e.g. the same WhatsApp number): link this chat too.
        if (! $conversation->crm_contact_id && ($known = $this->matcher->identityFor($conversation, $this->context->workspaceId()))) {
            $conversation->forceFill(['crm_contact_id' => $known->id])->save();
        }

        $contact = $conversation->crm_contact_id ? Contact::with(['owner:id,name', 'company:id,name'])->find($conversation->crm_contact_id) : null;
        if (! $contact) {
            return response()->json([
                'linked' => false,
                'customer' => ['name' => $conversation->customer_name, 'platform' => $conversation->platform, 'external_id' => $conversation->customer_external_id],
                'suggestions' => $this->matcher->suggestionsFor($conversation)->map(fn ($c) => $this->contactRow($c) + ['match' => $c->match]),
            ]);
        }

        $deal = $contact->deals()->where('status', 'open')->with('stage', 'pipeline.stages')->latest()->first();
        $task = $contact->activities()->where('type', 'task')->whereNull('completed_at')->orderByRaw('due_at is null')->orderBy('due_at')->first();
        $lead = $contact->leads()->whereNotIn('status', ['converted', 'unqualified'])->latest()->first();

        return response()->json([
            'linked' => true,
            'contact' => $this->contactRow($contact),
            'deal' => $deal ? $this->dealRow($deal) + ['stages' => $deal->pipeline?->stages->map->only(['id', 'name', 'kind'])] : null,
            'lead' => $lead ? ['id' => $lead->id, 'status' => $lead->status] : null,
            'next_task' => $task ? $this->activityRow($task) : null,
            'lifetime_value' => (float) $contact->deals()->where('status', 'won')->sum('value'),
        ]);
    }

    /** Link to an existing contact, or create one from the conversation. Optionally remember the handle. */
    public function link(Request $request, Conversation $conversation)
    {
        $this->authorizeConversation($conversation);
        $data = $request->validate([
            'contact_id' => ['nullable', Rule::exists('crm_contacts', 'id')->where('workspace_id', $this->context->workspaceId())->whereNull('deleted_at')],
            'first_name' => ['required_without:contact_id', 'nullable', 'string', 'max:80'],
            'last_name' => ['nullable', 'string', 'max:80'], 'phone' => ['nullable', 'string', 'max:40'], 'email' => ['nullable', 'email', 'max:160'],
            'remember' => ['sometimes', 'boolean'], 'as_lead' => ['sometimes', 'boolean'],
        ]);

        $contact = DB::transaction(function () use ($conversation, $data) {
            $actor = $this->context->actorId();
            $contact = isset($data['contact_id']) ? Contact::findOrFail($data['contact_id']) : Contact::create([
                'first_name' => $data['first_name'], 'last_name' => $data['last_name'] ?? null,
                'phone' => $data['phone'] ?? ($conversation->platform === 'whatsapp' ? $conversation->customer_external_id : null),
                'email' => $data['email'] ?? null, 'source' => $conversation->platform, 'preferred_channel' => $conversation->platform,
                'lifecycle' => 'lead', 'owner_id' => $actor, 'created_by' => $actor, 'last_activity_at' => now(),
            ]);
            $conversation->forceFill(['crm_contact_id' => $contact->id])->save();

            if (($data['remember'] ?? true) && $conversation->customer_external_id) {
                ContactIdentity::updateOrCreate(
                    ['workspace_id' => $this->context->workspaceId(), 'platform' => $conversation->platform, 'external_id' => (string) $conversation->customer_external_id],
                    ['contact_id' => $contact->id, 'handle' => $conversation->customer_name]
                );
            }
            if (! empty($data['as_lead']) && ! $contact->leads()->whereNotIn('status', ['converted', 'unqualified'])->exists()) {
                $contact->leads()->create(['owner_id' => $contact->owner_id ?? $actor, 'status' => 'new', 'source' => $conversation->platform,
                    'conversation_id' => $conversation->id, 'created_by' => $actor]);
            }
            $this->timeline->event('conversation_linked', ['contact_id' => $contact->id, 'conversation_id' => $conversation->id], ['platform' => $conversation->platform]);

            return $contact;
        });

        return $this->show($conversation->fresh())->setStatusCode(isset($data['contact_id']) ? 200 : 201);
    }

    public function unlink(Conversation $conversation)
    {
        $this->authorizeConversation($conversation);
        if ($conversation->crm_contact_id) {
            ContactIdentity::where('contact_id', $conversation->crm_contact_id)->where('platform', $conversation->platform)
                ->where('external_id', (string) $conversation->customer_external_id)->delete();
            $conversation->forceFill(['crm_contact_id' => null])->save();
        }

        return $this->show($conversation->fresh());
    }

    /** Quick note or task from the panel, tied to the conversation. */
    public function note(Request $request, Conversation $conversation)
    {
        $this->authorizeConversation($conversation);
        abort_unless($conversation->crm_contact_id, 422);
        $data = $request->validate(['type' => ['required', Rule::in(['note', 'task'])], 'subject' => ['required', 'string', 'max:200'], 'due_at' => ['nullable', 'date']]);
        Activity::create([
            'type' => $data['type'], 'subject' => $data['subject'], 'body' => $data['type'] === 'note' ? $data['subject'] : null,
            'due_at' => $data['due_at'] ?? ($data['type'] === 'task' ? now()->addDay() : null), 'completed_at' => $data['type'] === 'note' ? now() : null,
            'owner_id' => $this->context->actorId(), 'created_by' => $this->context->actorId(),
            'contact_id' => $conversation->crm_contact_id, 'conversation_id' => $conversation->id,
        ]);
        Contact::whereKey($conversation->crm_contact_id)->update(['last_activity_at' => now()]);

        return $this->show($conversation);
    }

    /** Conversations carry no workspace scope of their own: they belong to the account that owns the channel. */
    private function authorizeConversation(Conversation $conversation): void
    {
        abort_unless($conversation->channel()->where('user_id', Auth::id())->exists(), 404);
    }
}
