<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmailMarketing\EmailCampaign;
use App\Models\EmailMarketing\EmailList;
use App\Models\EmailMarketing\EmailSegment;
use App\Models\EmailMarketing\EmailSubaccount;
use App\Models\EmailMarketing\EmailSubscriber;
use App\Models\EmailMarketing\EmailTemplate;
use App\Models\EmailMarketing\SenderIdentity;
use App\Services\EmailMarketingServices\EmailMarketingService;
use App\Services\EmailMarketingServices\SendGridSuppressionService;
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

    public function create(Request $request, SendGridSuppressionService $suppression)
    {
        $lists = EmailList::where('user_id', Auth::id())->withCount('subscribers')->get();
        $segments = EmailSegment::where('user_id', Auth::id())->get();
        $templates = EmailTemplate::where('user_id', Auth::id())->get();
        $senders = SenderIdentity::where('user_id', Auth::id())->where('status', 'verified')->get();

        // "Use Template" from the templates gallery links here with
        // ?template={id} - only honored when it's a real template this
        // seller actually owns, never trusted blindly from the query string.
        $preselectedTemplateId = $templates->contains('id', (int) $request->query('template'))
            ? (int) $request->query('template')
            : null;

        $totalSubscribers = EmailSubscriber::where('user_id', Auth::id())->where('status', 'subscribed')->count();
        [$suppressionGroups, $suppressionGroupsError] = $this->fetchSuppressionGroups($suppression);

        return view('admin.email.campaigns.create', compact(
            'lists', 'segments', 'templates', 'senders', 'preselectedTemplateId', 'totalSubscribers',
            'suppressionGroups', 'suppressionGroupsError'
        ));
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
            'campaign_type'      => $validated['campaign_type'],
            'suppression_group_id' => $validated['suppression_group_id'] ?? null,
        ]);

        return $this->afterSave($campaign, $validated['action'], $validated['scheduled_at'] ?? null);
    }

    public function edit(EmailCampaign $campaign, SendGridSuppressionService $suppression)
    {
        abort_unless($campaign->user_id === Auth::id(), 403);
        abort_unless($campaign->isEditable(), 403, 'This campaign has already been sent and can no longer be edited.');

        $lists = EmailList::where('user_id', Auth::id())->withCount('subscribers')->get();
        $segments = EmailSegment::where('user_id', Auth::id())->get();
        $templates = EmailTemplate::where('user_id', Auth::id())->get();
        $senders = SenderIdentity::where('user_id', Auth::id())->where('status', 'verified')->get();
        $totalSubscribers = EmailSubscriber::where('user_id', Auth::id())->where('status', 'subscribed')->count();
        [$suppressionGroups, $suppressionGroupsError] = $this->fetchSuppressionGroups($suppression);

        return view('admin.email.campaigns.edit', compact(
            'campaign', 'lists', 'segments', 'templates', 'senders', 'totalSubscribers',
            'suppressionGroups', 'suppressionGroupsError'
        ));
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
            'campaign_type'      => $validated['campaign_type'],
            'suppression_group_id' => $validated['suppression_group_id'] ?? null,
        ]);
  
        return $this->afterSave($campaign, $validated['action'], $validated['scheduled_at'] ?? null);
    }

    public function show(EmailCampaign $campaign)
    {
        abort_unless($campaign->user_id === Auth::id(), 403);

        $campaign->load('senderIdentity', 'template');

        $events = $campaign->events()->latest('event_at')->paginate(50, ['*'], 'events_page');
        $recentEvents = $campaign->events()->latest('event_at')->take(6)->get();

        // Segment membership lives in SendGrid, not locally (see
        // audienceRecipientCount()'s own note in SendGridCampaignService) -
        // the Recipients tab can only list real rows for a list audience.
        $audience = $campaign->audience();
        $recipients = $audience instanceof EmailList
            ? $audience->subscribers()->where('status', 'subscribed')->orderBy('email')->paginate(50, ['*'], 'recipients_page')
            : null;

        [$chartLabels, $chartDelivered, $chartOpened, $chartClicked] = $this->engagementSeries($campaign);

        return view('admin.email.campaigns.show', compact(
            'campaign', 'events', 'recentEvents', 'audience', 'recipients',
            'chartLabels', 'chartDelivered', 'chartOpened', 'chartClicked'
        ));
    }

    /**
     * Daily delivered/opened/clicked counts sourced from this campaign's
     * own EmailEvent rows, from its send date to today (capped at 30 days
     * for chart readability) - mirrors
     * EmailMarketingController::dailyEventSeries()'s gap-filling approach
     * but scoped to one campaign instead of a user-wide date range. A
     * campaign that hasn't sent yet has nothing to chart, so it returns
     * empty series rather than a row of misleading zeros.
     */
    private function engagementSeries(EmailCampaign $campaign): array
    {
        if (!$campaign->sent_at) {
            return [[], [], [], []];
        }

        $start = $campaign->sent_at->copy()->startOfDay();
        $end = now()->startOfDay();
        if ($start->diffInDays($end) > 29) {
            $start = $end->copy()->subDays(29);
        }
        $days = $start->diffInDays($end) + 1;

        $rows = $campaign->events()
            ->where('event_at', '>=', $start)
            ->selectRaw('DATE(event_at) as day, event_type, COUNT(*) as c')
            ->groupBy('day', 'event_type')
            ->get()
            ->groupBy('day');

        $labels = $delivered = $opened = $clicked = [];

        for ($i = 0; $i < $days; $i++) {
            $date = $start->copy()->addDays($i);
            $dayRows = $rows->get($date->format('Y-m-d'), collect());
            $labels[] = $date->format('M j');
            $delivered[] = (int) ($dayRows->firstWhere('event_type', 'delivered')->c ?? 0);
            $opened[] = (int) ($dayRows->firstWhere('event_type', 'open')->c ?? 0);
            $clicked[] = (int) ($dayRows->firstWhere('event_type', 'click')->c ?? 0);
        }

        return [$labels, $delivered, $opened, $clicked];
    }

    /**
     * Clones a campaign as a brand-new draft - name, content, audience,
     * sender and settings carry over; anything about a specific send
     * (SendGrid single-send id, schedule, delivery counters) starts fresh
     * since this is a new, never-sent campaign.
     */
    public function duplicate(EmailCampaign $campaign)
    {
        abort_unless($campaign->user_id === Auth::id(), 403);

        $copy = $campaign->replicate();
        $copy->name = $campaign->name . ' (Copy)';
        $copy->status = 'draft';
        $copy->sendgrid_single_send_id = null;
        $copy->scheduled_at = null;
        $copy->sent_at = null;
        $copy->total_recipients = 0;
        $copy->sent_count = 0;
        $copy->delivered_count = 0;
        $copy->opened_count = 0;
        $copy->clicked_count = 0;
        $copy->bounced_count = 0;
        $copy->complained_count = 0;
        $copy->unsubscribed_count = 0;
        $copy->failed_count = 0;
        $copy->error_message = null;
        $copy->save();

        return redirect()->route('admin.email.campaigns.edit', $copy)->with('success', 'Campaign duplicated as a new draft.');
    }

    /**
     * Real CSV export of every recorded event for this campaign - not a
     * decorative button. Streamed rather than built as one big string, so
     * a campaign with a large recipient list doesn't have to hold the
     * whole export in memory at once.
     */
    public function exportReport(EmailCampaign $campaign): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        abort_unless($campaign->user_id === Auth::id(), 403);

        $filename = 'campaign-' . $campaign->id . '-report-' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($campaign) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Event', 'Email', 'Timestamp', 'URL', 'Reason']);

            $campaign->events()->orderBy('event_at')->chunk(500, function ($chunk) use ($handle) {
                foreach ($chunk as $event) {
                    fputcsv($handle, [
                        $event->event_type,
                        $event->recipient_email,
                        $event->event_at?->toDateTimeString(),
                        $event->url,
                        $event->reason,
                    ]);
                }
            });

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
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

    /**
     * AJAX endpoint backing the wizard's "Create New Group" action - a
     * marketing send has nowhere to go if a seller has zero SendGrid
     * suppression groups yet, so this is a real create call, not a
     * dead-end pointing them at SendGrid's own dashboard.
     */
    public function storeSuppressionGroup(Request $request, SendGridSuppressionService $suppression)
    {
        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $subaccount = EmailSubaccount::where('user_id', Auth::id())->first();

        if (!$subaccount) {
            return response()->json(['success' => false, 'message' => 'Complete Email Marketing setup before creating an unsubscribe group.'], 422);
        }

        $result = $suppression->createGroup($subaccount, $validated['name'], $validated['description'] ?? null);

        if (!$result['success']) {
            return response()->json(['success' => false, 'message' => $result['error']], 422);
        }

        return response()->json(['success' => true, 'group' => $result['group']]);
    }

    /**
     * [$groups, $error] - $groups is always a real array (never
     * fabricated placeholders) and $error carries the real reason when
     * the fetch failed (no subaccount yet, subaccount inactive, or a
     * genuine SendGrid API error), so the view can tell "you have none"
     * apart from "we couldn't check".
     */
    private function fetchSuppressionGroups(SendGridSuppressionService $suppression): array
    {
        $subaccount = EmailSubaccount::where('user_id', Auth::id())->first();

        if (!$subaccount) {
            return [[], 'Complete Email Marketing setup before creating a campaign.'];
        }

        $result = $suppression->listGroups($subaccount);

        return [$result['groups'], $result['success'] ? null : $result['error']];
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
            // 'automated'/'drip' deliberately excluded - see the
            // campaign_type migration's docblock for why those aren't
            // real, selectable values yet.
            'campaign_type'      => ['required', Rule::in(['one_time', 'newsletter'])],
            // Only required to actually send/schedule - a draft can be
            // saved before a group's been picked, but
            // SendGridCampaignService::preflight()'s "Unsubscribe group
            // selected" check blocks Send Now/Schedule without one
            // regardless of what's enforced here, so this is real
            // client-facing validation, not the only safety net.
            'suppression_group_id' => [
                Rule::requiredIf(fn () => in_array($request->input('action'), ['send_now', 'schedule'], true)),
                'nullable', 'integer',
            ],
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
