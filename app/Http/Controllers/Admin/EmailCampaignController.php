<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmailMarketing\EmailCampaign;
use App\Models\EmailMarketing\EmailList;
use App\Models\EmailMarketing\EmailSegment;
use App\Models\EmailMarketing\EmailTemplate;
use App\Models\EmailMarketing\SenderIdentity;
use App\Services\EmailMarketingServices\EmailMarketingService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * A campaign's subject/body is copied in at creation time from whichever
 * template (if any) was picked - see EmailCampaign::$fillable - so later
 * edits to that template never change a campaign that's scheduled or
 * already sent. Sending/scheduling goes through
 * EmailMarketingService::dispatchCampaign(), which now calls SendGrid's
 * own Single Send schedule endpoint directly - unlike the old Mailgun
 * flow, "Schedule" no longer waits for a local cron to notice
 * scheduled_at has arrived; SendGrid itself holds and fires the send at
 * the chosen time once scheduled here.
 */
class EmailCampaignController extends Controller
{
    public function __construct(protected EmailMarketingService $emailMarketing)
    {
    }

    public function index()
    {
        $campaigns = EmailCampaign::where('user_id', Auth::id())
            ->with('list', 'senderIdentity')
            ->latest()
            ->paginate(20);

        return view('admin.email.campaigns.index', compact('campaigns'));
    }

    public function create()
    {
        $lists = EmailList::where('user_id', Auth::id())->withCount('subscribers')->get();
        $segments = EmailSegment::where('user_id', Auth::id())->get();
        $templates = EmailTemplate::where('user_id', Auth::id())->get();
        $senders = SenderIdentity::where('user_id', Auth::id())->where('status', 'verified')->get();

        return view('admin.email.campaigns.create', compact('lists', 'segments', 'templates', 'senders'));
    }

    public function store(Request $request)
    {
        $validated = $this->validated($request);

        $campaign = EmailCampaign::create([
            'user_id'            => Auth::id(),
            'email_list_id'      => $validated['audience_type'] === 'list' ? $validated['audience_id'] : null,
            'audience_type'      => $validated['audience_type'],
            'audience_id'        => $validated['audience_id'],
            'email_template_id'  => $validated['email_template_id'] ?? null,
            'sender_identity_id' => $validated['sender_identity_id'],
            'name'               => $validated['name'],
            'subject'            => $validated['subject'],
            'preheader'          => $validated['preheader'] ?? null,
            'from_name'          => $validated['from_name'],
            'from_email'         => $validated['from_email'],
            'body'               => $validated['body'],
            'status'             => 'draft',
        ]);

        return $this->afterSave($campaign, $validated['action'], $validated['scheduled_at'] ?? null);
    }

    public function edit(EmailCampaign $campaign)
    {
        abort_unless($campaign->user_id === Auth::id(), 403);
        abort_unless($campaign->isEditable(), 403, 'This campaign has already been sent and can no longer be edited.');

        $lists = EmailList::where('user_id', Auth::id())->withCount('subscribers')->get();
        $segments = EmailSegment::where('user_id', Auth::id())->get();
        $templates = EmailTemplate::where('user_id', Auth::id())->get();
        $senders = SenderIdentity::where('user_id', Auth::id())->where('status', 'verified')->get();

        return view('admin.email.campaigns.edit', compact('campaign', 'lists', 'segments', 'templates', 'senders'));
    }

    public function update(Request $request, EmailCampaign $campaign)
    {
        abort_unless($campaign->user_id === Auth::id(), 403);
        abort_unless($campaign->isEditable(), 403, 'This campaign has already been sent and can no longer be edited.');

        $validated = $this->validated($request);

        $campaign->update([
            'email_list_id'      => $validated['audience_type'] === 'list' ? $validated['audience_id'] : null,
            'audience_type'      => $validated['audience_type'],
            'audience_id'        => $validated['audience_id'],
            'email_template_id'  => $validated['email_template_id'] ?? null,
            'sender_identity_id' => $validated['sender_identity_id'],
            'name'               => $validated['name'],
            'subject'            => $validated['subject'],
            'preheader'          => $validated['preheader'] ?? null,
            'from_name'          => $validated['from_name'],
            'from_email'         => $validated['from_email'],
            'body'               => $validated['body'],
        ]);

        return $this->afterSave($campaign, $validated['action'], $validated['scheduled_at'] ?? null);
    }

    public function show(EmailCampaign $campaign)
    {
        abort_unless($campaign->user_id === Auth::id(), 403);

        $events = $campaign->events()->latest('event_at')->paginate(50);

        return view('admin.email.campaigns.show', compact('campaign', 'events'));
    }

    public function destroy(EmailCampaign $campaign)
    {
        abort_unless($campaign->user_id === Auth::id(), 403);

        if (in_array($campaign->status, ['scheduled', 'sending'], true)) {
            $this->emailMarketing->cancelCampaign($campaign);
        }

        $campaign->delete();

        return redirect()->route('admin.email.campaigns.index')->with('success', 'Campaign deleted.');
    }

    /**
     * Explicit "Send Now" action for a draft/scheduled campaign that's
     * already been saved - separate from store()/update() so a campaign
     * can be sent from the index list too, not only right after composing
     * it.
     */
    public function sendNow(EmailCampaign $campaign)
    {
        abort_unless($campaign->user_id === Auth::id(), 403);

        $result = $this->emailMarketing->dispatchCampaign($campaign);

        if (!($result['success'] ?? false)) {
            return back()->with('error', $result['error'] ?? 'Failed to send campaign.');
        }

        return redirect()->route('admin.email.campaigns.index')->with('success', 'Campaign sent.');
    }

    /**
     * Read-only pre-flight checklist, used by the Review & Send step
     * before showing the Send Now/Schedule buttons at all - a direct
     * implementation of the spec's "cannot send until every item passes"
     * requirement.
     */
    public function preflight(EmailCampaign $campaign)
    {
        abort_unless($campaign->user_id === Auth::id(), 403);

        return response()->json($this->emailMarketing->preflight($campaign));
    }

    private function afterSave(EmailCampaign $campaign, string $action, ?string $scheduledAt)
    {
        if ($action === 'save_draft') {
            $this->emailMarketing->saveDraft($campaign);

            return redirect()->route('admin.email.campaigns.index')->with('success', 'Campaign saved as draft.');
        }

        $sendAt = $action === 'schedule' ? Carbon::parse($scheduledAt) : null;
        $result = $this->emailMarketing->dispatchCampaign($campaign, $sendAt);

        if (!($result['success'] ?? false)) {
            return redirect()->route('admin.email.campaigns.edit', $campaign)
                ->with('error', $result['error'] ?? 'Failed to send campaign. It has been saved as a draft.');
        }

        return redirect()->route('admin.email.campaigns.index')->with(
            'success',
            $action === 'schedule' ? 'Campaign scheduled.' : 'Campaign sent.'
        );
    }

    private function validated(Request $request): array
    {
        $userId = Auth::id();

        $validated = $request->validate([
            'name'               => ['required', 'string', 'max:255'],
            'audience_type'      => ['required', Rule::in(['list', 'segment'])],
            'audience_id'        => ['required', 'integer'],
            'sender_identity_id' => ['required', Rule::exists('sender_identities', 'id')->where('user_id', $userId)->where('status', 'verified')],
            'email_template_id'  => ['nullable', Rule::exists('email_templates', 'id')->where('user_id', $userId)],
            'subject'            => ['required', 'string', 'max:255'],
            'preheader'          => ['nullable', 'string', 'max:255'],
            'from_name'          => ['required', 'string', 'max:255'],
            'from_email'         => ['required', 'email', 'max:255'],
            'body'               => ['required', 'string'],
            'action'             => ['required', Rule::in(['save_draft', 'schedule', 'send_now'])],
            'scheduled_at'       => ['required_if:action,schedule', 'nullable', 'date', 'after:now'],
        ]);

        $table = $validated['audience_type'] === 'segment' ? 'email_segments' : 'email_lists';
        $exists = ($validated['audience_type'] === 'segment' ? EmailSegment::query() : EmailList::query())
            ->where('id', $validated['audience_id'])
            ->where('user_id', $userId)
            ->exists();

        abort_unless($exists, 422, "Selected audience does not exist in {$table} for this account.");

        return $validated;
    }
}
