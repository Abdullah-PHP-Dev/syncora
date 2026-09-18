<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmailMarketing\EmailSubaccount;
use App\Models\EmailMarketing\SenderIdentity;
use App\Models\EmailMarketing\VerifiedDomain;
use App\Services\EmailMarketingServices\SendGridDomainService;
use App\Services\EmailMarketingServices\SendGridSenderService;
use App\Services\EmailMarketingServices\SendGridSubaccountService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Setup wizard: Subaccount -> Domain -> DNS -> Sender -> Ready. Each step
 * renders from real infrastructure state (EmailSubaccount/VerifiedDomain/
 * SenderIdentity status columns), never a single "email_enabled" flag -
 * refreshing mid-setup or resubmitting a step is always safe because of
 * this (see each service's own idempotency handling), so this is plain
 * synchronous Blade forms rather than a JS stepper with its own client-
 * side state to keep in sync.
 */
class EmailSetupController extends Controller
{
    public function index()
    {
        $userId = Auth::id();

        $subaccount = EmailSubaccount::where('user_id', $userId)->first();
        $domain = $subaccount ? VerifiedDomain::where('user_id', $userId)->with('dnsRecords')->latest()->first() : null;
        // All senders, not just one - screenshot 6's Sender Management
        // table lists every identity a seller has created, not only the
        // most recent. $sender (singular, the latest) is kept for the
        // "add the first one" empty-state form.
        $senders = $subaccount ? SenderIdentity::where('user_id', $userId)->latest()->get() : collect();
        $sender = $senders->first();

        $ready = $subaccount?->isActive() && $domain?->isVerified() && $senders->contains(fn ($s) => $s->isVerified());

        return view('admin.email.setup.index', compact('subaccount', 'domain', 'sender', 'senders', 'ready'));
    }

    public function provisionSubaccount(SendGridSubaccountService $service)
    {
        $result = $service->provision(Auth::user());

        return back()->with($result['success'] ? 'success' : 'error', $result['success']
            ? 'SendGrid subaccount ready.'
            : ($result['error'] ?? 'Failed to provision SendGrid subaccount.'));
    }

    public function authenticateDomain(Request $request, SendGridDomainService $service)
    {
        $validated = $request->validate(['domain' => ['required', 'string', 'max:255']]);

        $subaccount = EmailSubaccount::where('user_id', Auth::id())->firstOrFail();

        $result = $service->authenticate($subaccount, $validated['domain']);

        return back()->with($result['success'] ? 'success' : 'error', $result['success']
            ? 'Domain added - add the DNS records below, then verify.'
            : ($result['error'] ?? 'Failed to authenticate domain.'));
    }

    public function verifyDomain(VerifiedDomain $domain, SendGridDomainService $service)
    {
        abort_unless($domain->user_id === Auth::id(), 403);

        $result = $service->verify($domain);

        if (!$result['success']) {
            return back()->with('error', $result['error']);
        }

        return back()->with(
            $result['valid'] ? 'success' : 'error',
            $result['valid'] ? 'Domain verified.' : 'DNS records not detected yet - they can take up to 48 hours to propagate.'
        );
    }

    public function createSender(Request $request, SendGridSenderService $service)
    {
        $validated = $request->validate([
            'nickname'   => ['required', 'string', 'max:255'],
            'from_name'  => ['required', 'string', 'max:255'],
            'from_email' => ['required', 'email', 'max:255'],
            'reply_to'   => ['nullable', 'email', 'max:255'],
            'address'    => ['required', 'string', 'max:255'],
            'address_2'  => ['nullable', 'string', 'max:255'],
            'city'       => ['required', 'string', 'max:255'],
            'state'      => ['nullable', 'string', 'max:255'],
            'zip'        => ['nullable', 'string', 'max:32'],
            'country'    => ['required', 'string', 'max:255'],
        ]);

        $subaccount = EmailSubaccount::where('user_id', Auth::id())->firstOrFail();

        $result = $service->create($subaccount, $validated);

        return back()->with($result['success'] ? 'success' : 'error', $result['success']
            ? 'Verification email sent to ' . $validated['from_email'] . ' - click the link, then refresh status below.'
            : ($result['error'] ?? 'Failed to create sender identity.'));
    }

    public function refreshSenderStatus(SenderIdentity $sender, SendGridSenderService $service)
    {
        abort_unless($sender->user_id === Auth::id(), 403);

        $result = $service->refreshStatus($sender);

        if (!$result['success']) {
            return back()->with('error', $result['error']);
        }

        return back()->with(
            $result['verified'] ? 'success' : 'error',
            $result['verified'] ? 'Sender verified.' : 'Not verified yet - check the inbox for the verification email.'
        );
    }

    public function resendSenderVerification(SenderIdentity $sender, SendGridSenderService $service)
    {
        abort_unless($sender->user_id === Auth::id(), 403);

        $result = $service->resendVerification($sender);

        return back()->with($result['success'] ? 'success' : 'error', $result['success']
            ? 'Verification email resent.'
            : ($result['error'] ?? 'Failed to resend verification email.'));
    }
}
