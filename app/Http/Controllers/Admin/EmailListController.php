<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmailMarketing\EmailList;
use App\Models\EmailMarketing\EmailSubaccount;
use App\Services\EmailMarketingServices\SendGridListService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Contact lists - a list is just a named group of subscribers a campaign
 * is sent to. Subscriber management within a list lives in
 * EmailSubscriberController, kept separate since a list's own CRUD
 * (rename/delete) and its member management are different concerns with
 * different views.
 */
class EmailListController extends Controller
{
    public function index()
    {
        $lists = EmailList::where('user_id', Auth::id())
            ->withCount('subscribers')
            ->latest()
            ->get();

        return view('admin.email.lists.index', compact('lists'));
    }

    public function store(Request $request, SendGridListService $sendGridLists)
    {
        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $list = EmailList::create([
            'user_id'     => Auth::id(),
            'name'        => $validated['name'],
            'description' => $validated['description'] ?? null,
        ]);

        // Best-effort: the local list is the source of truth for this
        // app's own UI regardless of whether SendGrid sync succeeds right
        // now - same "save locally first, sync externally in an outer
        // try/catch" pattern PostAccountController uses after connecting
        // a social account. A list without a SendGrid id yet just can't
        // be picked as a campaign audience until synced (surfaced in the
        // campaign builder, not silently broken).
        $subaccount = EmailSubaccount::where('user_id', Auth::id())->where('status', 'active')->first();

        if ($subaccount) {
            try {
                $sendGridLists->sync($subaccount, $list);
            } catch (\Throwable $e) {
                Log::warning('SendGrid list sync failed after create.', ['list_id' => $list->id, 'error' => $e->getMessage()]);
            }
        }

        return back()->with('success', 'List created.');
    }

    public function update(Request $request, EmailList $list)
    {
        abort_unless($list->user_id === Auth::id(), 403);

        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $list->update($validated);

        return back()->with('success', 'List updated.');
    }

    public function destroy(EmailList $list)
    {
        abort_unless($list->user_id === Auth::id(), 403);

        $list->delete();

        return back()->with('success', 'List deleted.');
    }
}
