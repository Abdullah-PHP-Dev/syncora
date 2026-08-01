<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmailMarketing\EmailCampaign;
use App\Models\EmailMarketing\EmailList;
use App\Models\EmailMarketing\EmailTemplate;
use App\Services\EmailMarketingServices\EmailMarketingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * A campaign's subject/body is copied in at creation time from whichever
 * template (if any) was picked - see EmailCampaign::$fillable - so later
 * edits to that template never change a campaign that's scheduled or
 * already sent. Sending itself (immediate or once a schedule is due) goes
 * through EmailMarketingService::dispatchCampaign(), which is also what
 * SendScheduledEmailCampaigns calls - this controller never talks to
 * Mailgun directly.
 */
class EmailCampaignController extends Controller
{
    public function __construct(protected EmailMarketingService $emailMarketing)
    {
    }

    public function index()
    {
        $campaigns = EmailCampaign::where('user_id', Auth::id())
            ->with('list')
            ->latest()
            ->paginate(20);

        return view('admin.email.campaigns.index', compact('campaigns'));
    }

    public function create()
    {
        $lists = EmailList::where('user_id', Auth::id())->withCount('subscribers')->get();
        $templates = EmailTemplate::where('user_id', Auth::id())->get();

        return view('admin.email.campaigns.create', compact('lists', 'templates'));
    }

    public function store(Request $request)
    {
        $validated = $this->validated($request);

        $campaign = EmailCampaign::create([
            'user_id'           => Auth::id(),
            'email_list_id'     => $validated['email_list_id'],
            'email_template_id' => $validated['email_template_id'] ?? null,
            'name'              => $validated['name'],
            'subject'           => $validated['subject'],
            'from_name'         => $validated['from_name'],
            'from_email'        => $validated['from_email'],
            'body'              => $validated['body'],
            'status'            => $validated['action'] === 'schedule' ? 'scheduled' : 'draft',
            'scheduled_at'      => $validated['action'] === 'schedule' ? $validated['scheduled_at'] : null,
        ]);

        return $this->afterSave($campaign, $validated['action']);
    }

    public function edit(EmailCampaign $campaign)
    {
        abort_unless($campaign->user_id === Auth::id(), 403);
        abort_unless($campaign->isEditable(), 403, 'This campaign has already been sent and can no longer be edited.');

        $lists = EmailList::where('user_id', Auth::id())->withCount('subscribers')->get();
        $templates = EmailTemplate::where('user_id', Auth::id())->get();

        return view('admin.email.campaigns.edit', compact('campaign', 'lists', 'templates'));
    }

    public function update(Request $request, EmailCampaign $campaign)
    {
        abort_unless($campaign->user_id === Auth::id(), 403);
        abort_unless($campaign->isEditable(), 403, 'This campaign has already been sent and can no longer be edited.');

        $validated = $this->validated($request);

        $campaign->update([
            'email_list_id'     => $validated['email_list_id'],
            'email_template_id' => $validated['email_template_id'] ?? null,
            'name'              => $validated['name'],
            'subject'           => $validated['subject'],
            'from_name'         => $validated['from_name'],
            'from_email'        => $validated['from_email'],
            'body'              => $validated['body'],
            'status'            => $validated['action'] === 'schedule' ? 'scheduled' : 'draft',
            'scheduled_at'      => $validated['action'] === 'schedule' ? $validated['scheduled_at'] : null,
        ]);

        return $this->afterSave($campaign, $validated['action']);
    }

    public function show(EmailCampaign $campaign)
    {
        abort_unless($campaign->user_id === Auth::id(), 403);

        $sends = $campaign->sends()->with('subscriber')->latest()->paginate(50);

        return view('admin.email.campaigns.show', compact('campaign', 'sends'));
    }

    public function destroy(EmailCampaign $campaign)
    {
        abort_unless($campaign->user_id === Auth::id(), 403);

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

        return redirect()->route('admin.email.campaigns.index')->with('success', 'Campaign is sending.');
    }

    private function afterSave(EmailCampaign $campaign, string $action)
    {
        if ($action === 'send_now') {
            $result = $this->emailMarketing->dispatchCampaign($campaign);

            if (!($result['success'] ?? false)) {
                return redirect()->route('admin.email.campaigns.edit', $campaign)
                    ->with('error', $result['error'] ?? 'Failed to send campaign. It has been saved as a draft.');
            }

            return redirect()->route('admin.email.campaigns.index')->with('success', 'Campaign is sending.');
        }

        return redirect()->route('admin.email.campaigns.index')->with(
            'success',
            $action === 'schedule' ? 'Campaign scheduled.' : 'Campaign saved as draft.'
        );
    }

    private function validated(Request $request): array
    {
        $userId = Auth::id();

        return $request->validate([
            'name'              => ['required', 'string', 'max:255'],
            'email_list_id'     => ['required', Rule::exists('email_lists', 'id')->where('user_id', $userId)],
            'email_template_id' => ['nullable', Rule::exists('email_templates', 'id')->where('user_id', $userId)],
            'subject'           => ['required', 'string', 'max:255'],
            'from_name'         => ['required', 'string', 'max:255'],
            'from_email'        => ['required', 'email', 'max:255'],
            'body'              => ['required', 'string'],
            'action'            => ['required', Rule::in(['save_draft', 'schedule', 'send_now'])],
            'scheduled_at'      => ['required_if:action,schedule', 'nullable', 'date', 'after:now'],
        ]);
    }
}
