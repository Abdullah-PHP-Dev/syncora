<?php

namespace App\Services\Notifications;

use App\Models\Messaging\Conversation;
use App\Models\SellerTeamMember;
use App\Models\SocialConnection;
use App\Models\Subscription;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\Notifications\Emitters\ConnectionNotifications;
use App\Services\Notifications\Emitters\ModuleNotifications;
use App\Services\Notifications\Emitters\Support;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Time-based notifications (no single write causes them). Run by notifications:scan every
 * ten minutes; every emit is deduplicated, so overlapping or repeated runs are harmless.
 * Each scan is bounded (date windows + limits) - never a full-table walk.
 */
class NotificationScanner
{
    /** Manual renewals only: remind before the end, then after it (audit BIL-02..05). */
    public const TRIAL_REMINDERS = [7, 3, 1];

    public const PLAN_REMINDERS = [14, 7, 3, 1];

    public const EXPIRED_STAGES = [1, 4, 8]; // first day without a plan, +3 days, +7 days

    public function billing(): int
    {
        $count = 0;
        $today = now()->startOfDay();
        Subscription::query()->whereIn('status', ['trial', 'active'])
            ->whereBetween('end_date', [$today->copy()->subDays(8)->toDateString(), $today->copy()->addDays(14)->toDateString()])
            ->orderBy('id')->limit(5000)->get()
            ->each(function (Subscription $subscription) use ($today, &$count) {
                $owner = User::find($subscription->user_id);
                if (! $owner || $owner->isWorkspaceAccount() || $owner->is_active === false || ! $this->daytime($owner)) {
                    return;
                }
                $end = Carbon::parse($subscription->end_date)->startOfDay();
                $trial = $subscription->status === 'trial';
                $base = ['workspace' => $owner->id, 'subject' => $subscription, 'url' => Support::url('admin.subscription.plans'),
                    'dates' => ['end' => $end->copy()->endOfDay()], 'params' => ['plan' => (string) $subscription->bundle_name]];
                if ($end->gte($today)) {
                    $days = (int) $today->diffInDays($end) ?: 0;
                    if (in_array($days, $trial ? self::TRIAL_REMINDERS : self::PLAN_REMINDERS, true)) {
                        $base['params'] += ['days' => $days, 'choice' => $days];
                        Notify::event($trial ? 'billing.trial_ending' : 'billing.subscription_ending', $base + ['dedup' => $end->toDateString().':'.$days]) && $count++;
                    }

                    return;
                }
                $since = (int) $end->diffInDays($today);
                if ($trial ? $since === 1 : in_array($since, self::EXPIRED_STAGES, true)) {
                    Notify::event($trial ? 'billing.trial_expired' : 'billing.subscription_expired', $base + ['dedup' => $end->toDateString().':'.$since]) && $count++;
                }
            });

        return $count;
    }

    /** BIL-09: a BNPL payment still waiting for the provider after 15 minutes. */
    public function pendingPayments(): int
    {
        $count = 0;
        WalletTransaction::where('category', 'subscription')->where('status', 'pending')
            ->whereBetween('created_at', [now()->subDay(), now()->subMinutes(15)])
            ->where(fn ($q) => $q->whereIn('payment_gateway', ['tamara', 'tabby'])->orWhereIn('payment_method', ['tamara', 'tabby', 'bnpl']))
            ->limit(500)->get()->each(function (WalletTransaction $tx) use (&$count) {
                $method = ucfirst((string) ($tx->payment_gateway ?: $tx->payment_method));
                Notify::event('billing.payment_pending', [
                    'workspace' => $tx->seller_id, 'subject' => $tx, 'url' => Support::url('admin.subscription.plans'),
                    'params' => ['method' => $method, 'amount' => Support::money($tx->amount, $tx->meta['currency'] ?? null), 'plan' => (string) ($tx->meta['bundle_name'] ?? '—')],
                    'dedup' => 'pending',
                ]) && $count++;
            });

        return $count;
    }

    /** TEAM-03 / TEAM-04. */
    public function invitations(): int
    {
        $count = 0;
        SellerTeamMember::whereNull('accepted_at')->whereNotNull('token_hash')->whereBetween('expires_at', [now()->addHours(1), now()->addDay()])
            ->limit(1000)->get()->each(function (SellerTeamMember $m) use (&$count) {
                Notify::event('team.invitation_expiring', [
                    'workspace' => (int) $m->owner_id, 'subject' => $m, 'emails' => [$m->email], 'params' => ['workspace' => $m->workspace_name],
                    'url' => Support::url('seller-workspaces.index'), 'dedup' => 'expiring:'.optional($m->expires_at)->timestamp,
                ]) && $count++;
            });
        SellerTeamMember::whereNull('accepted_at')->whereBetween('expires_at', [now()->subDay(), now()])
            ->limit(1000)->get()->each(function (SellerTeamMember $m) use (&$count) {
                Notify::event('team.invitation_expired', [
                    'workspace' => (int) $m->owner_id, 'subject' => $m, 'params' => ['email' => $m->email],
                    'url' => Support::url('admin.seller-team.index'), 'dedup' => 'expired:'.optional($m->expires_at)->timestamp,
                ]) && $count++;
            });

        return $count;
    }

    /** CON-01 one day before expiry; CON-02 one reminder 48 h after a break that is still unresolved. */
    public function connections(): int
    {
        $count = 0;
        SocialConnection::where('status', SocialConnection::EXPIRING)->whereBetween('expires_at', [now(), now()->addDay()])
            ->limit(1000)->get()->each(function (SocialConnection $c) use (&$count) {
                ConnectionNotifications::statusChanged($c, null, ':1d');
                $count++;
            });
        SocialConnection::whereIn('status', [SocialConnection::NEEDS_REAUTH, SocialConnection::EXPIRED, SocialConnection::REVOKED])
            ->whereBetween('updated_at', [now()->subHours(72), now()->subHours(48)])
            ->where(fn ($q) => $q->whereNull('last_error')->orWhere('last_error', '!=', SocialConnection::DISCONNECTED_BY_USER))
            ->limit(1000)->get()->each(function (SocialConnection $c) use (&$count) {
                ConnectionNotifications::statusChanged($c, null, ':reminder');
                $count++;
            });

        return $count;
    }

    /**
     * INB-03: the customer spoke last and nobody (human or AI) has answered for the SLA window.
     * One event per "waiting episode" (keyed by the last inbound message), grouped per person.
     */
    public function unansweredConversations(): int
    {
        if (! Schema::hasTable('messages')) {
            return 0;
        }
        $sla = (int) config('notifications.defaults.inbox_sla_minutes', 60);
        $count = 0;
        Conversation::whereBetween('last_message_at', [now()->subDay(), now()->subMinutes($sla)])
            ->where(fn ($q) => $q->whereNull('status')->orWhereNotIn('status', ['resolved', 'closed', 'archived', 'spam']))
            ->orderByDesc('last_message_at')->limit(1000)->get()
            ->each(function (Conversation $conversation) use ($sla, &$count) {
                $last = DB::table('messages')->where('conversation_id', $conversation->id)->orderByDesc('id')->first(['id', 'direction', 'created_at']);
                if (! $last || $last->direction !== 'inbound' || Carbon::parse($last->created_at)->gt(now()->subMinutes($sla))) {
                    return;
                }
                Notify::event('inbox.unanswered', [
                    'workspace' => ModuleNotifications::workspaceOf($conversation->social_account_id), 'subject' => $conversation,
                    'users' => array_filter([$conversation->assigned_user_id]),
                    'params' => ['customer' => $conversation->customer_name ?: '—', 'channel' => Support::platform($conversation->platform),
                        'minutes' => (int) Carbon::parse($last->created_at)->diffInMinutes(now())],
                    'url' => Support::url('admin.chats.dashboard', ['conversation' => $conversation->id]),
                    'group_url' => Support::url('admin.chats.dashboard'),
                    'dedup' => 'episode:'.$last->id,
                ]) && $count++;
            });

        return $count;
    }

    /** GGL-02: a Google service whose sync has failed for more than a day without a success. */
    public function googleSync(): int
    {
        if (! Schema::hasTable('google_sync_events')) {
            return 0;
        }
        $count = 0;
        DB::table('google_sync_events')->where('created_at', '>=', now()->subDays(3))->orderBy('id')
            ->get(['user_id', 'service', 'status', 'created_at'])
            ->groupBy(fn ($e) => $e->user_id.'|'.$e->service)
            ->each(function ($events) use (&$count) {
                $lastOk = $events->where('status', 'ok')->max('created_at');
                $failures = $events->filter(fn ($e) => $e->status !== 'ok' && (! $lastOk || $e->created_at > $lastOk));
                $latest = $events->sortBy('created_at')->last();
                if ($failures->isEmpty() || $latest->status === 'ok' || Carbon::parse($failures->min('created_at'))->gt(now()->subDay())) {
                    return;
                }
                Notify::event('google.sync_failing', [
                    'workspace' => (int) $latest->user_id, 'params' => ['service' => ucwords(str_replace('_', ' ', (string) $latest->service))],
                    'url' => Support::url('admin.google.analytics'), 'dedup' => $latest->user_id.':'.$latest->service.':'.intdiv(now()->dayOfYear, 3),
                ]) && $count++;
            });

        return $count;
    }

    /** Reminders go out in the owner's daytime (09:00-21:00 local). */
    private function daytime(User $owner): bool
    {
        $hour = (int) now(RecipientContext::timezone($owner, $owner->id))->format('G');

        return $hour >= 9 && $hour < 21;
    }
}
