<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmailMarketing\EmailList;
use App\Models\EmailMarketing\EmailSubscriber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Manages membership of a single list (routes are nested under
 * admin/email/lists/{list}/subscribers) - an EmailSubscriber is a contact
 * scoped to the whole account (unique per user+email, see the
 * email_subscribers migration), not to one list, so adding an email that
 * already exists elsewhere in this account attaches the existing contact
 * (and its current subscribed/unsubscribed/bounced status) rather than
 * creating a duplicate.
 */
class EmailSubscriberController extends Controller
{
    public function index(Request $request, EmailList $list)
    {
        abort_unless($list->user_id === Auth::id(), 403);

        $subscribers = $list->subscribers()
            ->when($request->filled('search'), fn ($q) => $q->where(function ($q) use ($request) {
                $q->where('email', 'like', '%' . $request->query('search') . '%')
                  ->orWhere('name', 'like', '%' . $request->query('search') . '%');
            }))
            ->orderByDesc('email_list_subscriber.created_at')
            ->paginate(50)
            ->withQueryString();

        return view('admin.email.lists.subscribers', compact('list', 'subscribers'));
    }

    public function store(Request $request, EmailList $list)
    {
        abort_unless($list->user_id === Auth::id(), 403);

        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'name'  => ['nullable', 'string', 'max:255'],
        ]);

        $subscriber = EmailSubscriber::firstOrCreate(
            ['user_id' => Auth::id(), 'email' => $validated['email']],
            ['name' => $validated['name'] ?? null, 'status' => 'subscribed']
        );

        $list->subscribers()->syncWithoutDetaching([$subscriber->id]);

        return back()->with('success', 'Subscriber added.');
    }

    /**
     * Expects a CSV with an "email" column and an optional "name" column
     * (header row required). Rows with an invalid/missing email are
     * skipped and counted, not fatal to the whole import - a large list
     * from an export elsewhere in the wild will always have a few bad
     * rows, and losing the other 999 good ones over that would be worse.
     */
    public function import(Request $request, EmailList $list)
    {
        abort_unless($list->user_id === Auth::id(), 403);

        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ]);

        $handle = fopen($request->file('file')->getRealPath(), 'r');
        $header = array_map(fn ($col) => strtolower(trim($col)), fgetcsv($handle) ?: []);
        $emailIndex = array_search('email', $header, true);
        $nameIndex = array_search('name', $header, true);

        if ($emailIndex === false) {
            fclose($handle);

            return back()->with('error', 'The CSV must have an "email" column in its header row.');
        }

        $imported = 0;
        $skipped = 0;

        while (($row = fgetcsv($handle)) !== false) {
            $email = trim($row[$emailIndex] ?? '');
            $name = $nameIndex !== false ? trim($row[$nameIndex] ?? '') : null;

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $skipped++;
                continue;
            }

            $subscriber = EmailSubscriber::firstOrCreate(
                ['user_id' => Auth::id(), 'email' => $email],
                ['name' => $name ?: null, 'status' => 'subscribed']
            );

            $list->subscribers()->syncWithoutDetaching([$subscriber->id]);
            $imported++;
        }

        fclose($handle);

        return back()->with('success', "Imported {$imported} subscriber(s)." . ($skipped ? " Skipped {$skipped} invalid row(s)." : ''));
    }

    /**
     * Removes the subscriber from this list only - the contact itself
     * (and its status) is left alone since it may belong to other lists.
     */
    public function destroy(EmailList $list, EmailSubscriber $subscriber)
    {
        abort_unless($list->user_id === Auth::id(), 403);
        abort_unless($subscriber->user_id === Auth::id(), 403);

        $list->subscribers()->detach($subscriber->id);

        return back()->with('success', 'Subscriber removed from this list.');
    }
}
