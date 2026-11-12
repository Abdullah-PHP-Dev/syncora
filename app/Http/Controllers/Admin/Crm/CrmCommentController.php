<?php

namespace App\Http\Controllers\Admin\Crm;

use App\Http\Controllers\Controller;
use App\Models\Crm\Contact;
use App\Models\Crm\ContactIdentity;
use App\Models\PostComment;
use App\Services\Crm\CrmContext;
use App\Services\Crm\LeadService;
use App\Services\Crm\Timeline;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/** M-03: a comment becomes a lead (or opens the commenter's existing contact). */
class CrmCommentController extends Controller
{
    public function __construct(private CrmContext $context, private LeadService $leads, private Timeline $timeline) {}

    public function lead(PostComment $comment)
    {
        abort_unless((int) $comment->user_id === (int) Auth::id(), 404);
        $platform = $comment->platform;
        $handle = $comment->user_platform_id ?: ($comment->user_name ? 'name:'.$comment->user_name : null);
        abort_unless($handle, 422);

        $contact = DB::transaction(function () use ($comment, $platform, $handle) {
            $identity = ContactIdentity::where('platform', $platform)->where('external_id', $handle)->first();
            $contact = $identity ? Contact::withoutGlobalScope('visibility')->find($identity->contact_id) : null;
            if (! $contact) {
                [$first, $last] = array_pad(explode(' ', trim($comment->user_name ?: 'Commenter'), 2), 2, null);
                $contact = Contact::create([
                    'first_name' => mb_substr($first, 0, 80), 'last_name' => $last ? mb_substr($last, 0, 80) : null, 'email' => $comment->user_email,
                    'source' => $platform, 'preferred_channel' => $platform, 'lifecycle' => 'lead',
                    'owner_id' => $this->context->actorId(), 'created_by' => $this->context->actorId(), 'last_activity_at' => now(),
                ]);
                ContactIdentity::create(['contact_id' => $contact->id, 'platform' => $platform, 'external_id' => $handle, 'handle' => $comment->user_name]);
            }
            if (! $contact->leads()->whereNotIn('status', ['converted', 'unqualified'])->exists()) {
                $this->leads->create(['contact_id' => $contact->id, 'source' => $platform, 'interest' => mb_substr((string) $comment->content, 0, 2000), 'follow_up' => true]);
            }
            $this->timeline->event('comment_to_lead', ['contact_id' => $contact->id], ['comment' => mb_substr((string) $comment->content, 0, 300), 'platform' => $platform]);

            return $contact;
        });

        return response()->json(['contact_id' => $contact->id, 'url' => route('admin.crm.contacts.show', $contact)]);
    }
}
