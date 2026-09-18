<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmailMarketing\EmailSegment;
use App\Models\EmailMarketing\EmailSubaccount;
use App\Services\EmailMarketingServices\SendGridClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * SendGrid's Marketing Segments (query-based dynamic contact groups) -
 * unlike EmailList, a segment's membership isn't stored locally at all;
 * SendGrid evaluates the query itself. query_json is stored purely for
 * display (see the migration's own comment) - the create call just
 * forwards it to SendGrid as-is, no local validation of the query syntax
 * (SendGrid's own SGQL), matching "don't invent API behavior" from the
 * project's own guardrails.
 */
class EmailSegmentController extends Controller
{
    public function index()
    {
        $segments = EmailSegment::where('user_id', Auth::id())->latest()->get();

        return view('admin.email.segments.index', compact('segments'));
    }

    public function store(Request $request, SendGridClient $client)
    {
        $validated = $request->validate([
            'name'       => ['required', 'string', 'max:255'],
            'query_json' => ['required', 'string', 'max:2000'],
        ]);

        $subaccount = EmailSubaccount::where('user_id', Auth::id())->where('status', 'active')->first();

        if (!$subaccount) {
            return back()->with('error', 'Complete Email Marketing setup before creating segments.');
        }

        $response = $client->asSubaccount($subaccount->apiKeyValue(), $subaccount->region)
            ->post('marketing/segments/2.0', [
                'name'  => $validated['name'],
                'query_dsl' => $validated['query_json'],
            ]);

        if (!$response['success']) {
            return back()->with('error', $response['error'] ?? 'Failed to create segment on SendGrid.')->withInput();
        }

        EmailSegment::create([
            'user_id'             => Auth::id(),
            'sendgrid_segment_id' => $response['data']['id'] ?? null,
            'name'                => $validated['name'],
            'query_json'          => $validated['query_json'],
        ]);

        return back()->with('success', 'Segment created.');
    }

    public function destroy(EmailSegment $segment, SendGridClient $client)
    {
        abort_unless($segment->user_id === Auth::id(), 403);

        $subaccount = EmailSubaccount::where('user_id', Auth::id())->first();

        if ($subaccount?->isActive() && $segment->sendgrid_segment_id) {
            $client->asSubaccount($subaccount->apiKeyValue(), $subaccount->region)
                ->delete("marketing/segments/2.0/{$segment->sendgrid_segment_id}");
        }

        $segment->delete();

        return back()->with('success', 'Segment deleted.');
    }
}
