<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\EmailMarketing\PollSendGridContactImport;
use App\Models\EmailMarketing\EmailList;
use App\Models\EmailMarketing\EmailSubaccount;
use App\Models\EmailMarketing\EmailSubscriber;
use App\Services\EmailMarketingServices\SendGridContactService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

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

    public function store(Request $request, EmailList $list, SendGridContactService $contacts)
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

        $this->syncOneToSendGrid($subscriber, $list);

        return back()->with('success', 'Subscriber added.');
    }

    /**
     * Expects a CSV with an "email" column and an optional "name" column
     * (header row required). Rows with an invalid/missing email are
     * skipped and counted, not fatal to the whole import - a large list
     * from an export elsewhere in the wild will always have a few bad
     * rows, and losing the other 999 good ones over that would be worse.
     * SendGrid sync is one batched call for the whole file (see
     * SendGridContactService::upsertBatch()'s docblock), not one call per
     * row.
     */
    public function import(Request $request, EmailList $list, SendGridContactService $contacts)
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
        $importedSubscribers = [];

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
            $importedSubscribers[] = $subscriber;
            $imported++;
        }

        fclose($handle);

        $this->syncBatchToSendGrid($importedSubscribers, $list, $contacts);

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

    /**
     * Best-effort, same "save locally first, sync externally in an outer
     * try/catch" pattern used across every connect flow in this app -
     * a SendGrid sync failure never blocks the subscriber actually being
     * added to the local list.
     */
    private function syncOneToSendGrid(EmailSubscriber $subscriber, EmailList $list): void
    {
        if ($subscriber->sendgrid_contact_id) {
            return;
        }

        $subaccount = EmailSubaccount::where('user_id', Auth::id())->where('status', 'active')->first();

        if (!$subaccount) {
            return;
        }

        try {
            $result = app(SendGridContactService::class)->upsert($subaccount, $subscriber, $list);

            if (($result['success'] ?? false) && ($result['job_id'] ?? null)) {
                PollSendGridContactImport::dispatch($subaccount->id, $subscriber->id, $result['job_id']);
            }
        } catch (\Throwable $e) {
            Log::warning('SendGrid contact sync failed after adding subscriber.', ['subscriber_id' => $subscriber->id, 'error' => $e->getMessage()]);
        }
    }

    private function syncBatchToSendGrid(array $subscribers, EmailList $list, SendGridContactService $contacts): void
    {
        if (empty($subscribers)) {
            return;
        }

        $subaccount = EmailSubaccount::where('user_id', Auth::id())->where('status', 'active')->first();

        if (!$subaccount) {
            return;
        }

        try {
            $result = $contacts->upsertBatch($subaccount, $subscribers, $list);

            if (($result['success'] ?? false) && ($result['job_id'] ?? null)) {
                foreach ($subscribers as $subscriber) {
                    if (!$subscriber->sendgrid_contact_id) {
                        PollSendGridContactImport::dispatch($subaccount->id, $subscriber->id, $result['job_id']);
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning('SendGrid batch contact sync failed after CSV import.', ['list_id' => $list->id, 'error' => $e->getMessage()]);
        }
    }
}
