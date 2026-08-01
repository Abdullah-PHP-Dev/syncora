<?php

namespace App\Http\Controllers;

use App\Models\EmailMarketing\EmailSubscriber;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Public, no-auth unsubscribe flow - the link embedded in every campaign
 * email. Deliberately a GET confirmation page + separate POST, rather than
 * unsubscribing immediately on GET: mail-security scanners and some
 * corporate proxies pre-fetch every link in an email, which would
 * silently unsubscribe people who never clicked anything if GET alone did
 * it. The POST route is also what RFC 8058 one-click unsubscribe (the
 * native "Unsubscribe" button Gmail/Yahoo/Outlook show next to the
 * sender, driven by the List-Unsubscribe-Post header set in
 * EmailMarketingService::sendCampaignEmail()) hits directly from the mail
 * provider's own servers - no page ever loads for that path, so it's
 * exempted from CSRF verification (see bootstrap/app.php).
 */
class EmailUnsubscribeController extends Controller
{
    public function show(string $token)
    {
        $subscriber = EmailSubscriber::where('unsubscribe_token', $token)->firstOrFail();

        return view('email-marketing.unsubscribe', ['subscriber' => $subscriber]);
    }

    public function confirm(Request $request, string $token): Response
    {
        $subscriber = EmailSubscriber::where('unsubscribe_token', $token)->firstOrFail();

        if ($subscriber->status === 'subscribed') {
            $subscriber->update(['status' => 'unsubscribed', 'unsubscribed_at' => now()]);
        }

        // One-click requests (RFC 8058) expect a bare 200 with no body,
        // not an HTML page.
        if ($request->input('List-Unsubscribe') === 'One-Click') {
            return response('', 200);
        }

        return response()->view('email-marketing.unsubscribe', [
            'subscriber' => $subscriber,
            'confirmed'  => true,
        ]);
    }
}
