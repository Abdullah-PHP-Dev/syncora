<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmailMarketing\EmailList;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        EmailList::create([
            'user_id'     => Auth::id(),
            'name'        => $validated['name'],
            'description' => $validated['description'] ?? null,
        ]);

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
