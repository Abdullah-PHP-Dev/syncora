<?php

namespace App\Services\Notifications\Emitters;

use App\Models\Messaging\Conversation;
use App\Models\Messaging\Message;
use App\Models\PostComment;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Services\Crm\BuyingIntent;
use App\Services\Notifications\Notify;
use Illuminate\Support\Facades\DB;

/** Inbox, comments, support, ads, email marketing and automation events (model observers call these). */
class ModuleNotifications
{
    // ── Inbox & comments: email is a safety net, never a mirror of every message ──

    public static function workspaceOf(?int $socialAccountId): ?int
    {
        return $socialAccountId ? DB::table('social_accounts')->where('id', $socialAccountId)->value('user_id') : null;
    }

    public static function conversationUpdated(Conversation $conversation): void
    {
        if (! $conversation->wasChanged('assigned_user_id') || ! $conversation->assigned_user_id) {
            return;
        }
        Notify::event('inbox.conversation_assigned', [
            'workspace' => self::workspaceOf($conversation->social_account_id), 'subject' => $conversation, 'actor' => Support::actorId(),
            'users' => [$conversation->assigned_user_id],
            'params' => ['customer' => $conversation->customer_name ?: '—', 'channel' => Support::platform($conversation->platform)],
            'url' => Support::url('admin.chats.dashboard', ['conversation' => $conversation->id]), 'dedup' => $conversation->assigned_user_id.':'.now()->timestamp,
        ]);
    }

    public static function messageCreated(Message $message): void
    {
        if ($message->direction !== 'inbound' || ! BuyingIntent::detect($message->body)) {
            return;
        }
        $conversation = Conversation::find($message->conversation_id);
        if (! $conversation) {
            return;
        }
        Notify::event('inbox.buying_intent', [
            'workspace' => self::workspaceOf($conversation->social_account_id), 'subject' => $conversation,
            'users' => array_filter([$conversation->assigned_user_id]),
            'params' => ['customer' => $conversation->customer_name ?: '—', 'channel' => Support::platform($conversation->platform), 'excerpt' => Support::excerpt($message->body, 120)],
            'url' => Support::url('admin.chats.dashboard', ['conversation' => $conversation->id]),
            'dedup' => now()->toDateString(), // once per conversation per day
        ]);
    }

    public static function messageUpdated(Message $message): void
    {
        if (! $message->wasChanged('status') || $message->status !== 'failed' || $message->direction !== 'outbound' || $message->sender_type !== 'bot') {
            return;
        }
        $conversation = Conversation::find($message->conversation_id);
        if (! $conversation) {
            return;
        }
        Notify::event('inbox.ai_reply_failed', [
            'workspace' => self::workspaceOf($conversation->social_account_id), 'subject' => $message,
            'params' => ['customer' => $conversation->customer_name ?: '—', 'channel' => Support::platform($conversation->platform)],
            'url' => Support::url('admin.chats.dashboard', ['conversation' => $conversation->id]), 'dedup' => 'failed',
        ]);
    }

    public static function commentCreated(PostComment $comment): void
    {
        if ($comment->sender_type === 'support' || ! $comment->user_id || ! BuyingIntent::detect($comment->content)) {
            return;
        }
        Notify::event('comments.buying_intent', [
            'workspace' => $comment->user_id, 'subject' => $comment,
            'params' => ['author' => $comment->user_name ?: '—', 'platform' => Support::platform($comment->platform), 'excerpt' => Support::excerpt($comment->content, 120)],
            'url' => Support::url('admin.comments.dashboard'), 'dedup' => 'intent',
        ]);
    }

    // ── Support tickets (one ticket model: Ticket / TicketMessage) ──

    private static function ticketBase(Ticket $ticket): array
    {
        return [
            'subject' => $ticket, 'actor' => Support::actorId(),
            'params' => ['ticket' => $ticket->ticket_number, 'subject' => Support::excerpt($ticket->subject, 120), 'priority' => ucfirst((string) $ticket->priority)],
            'url' => Support::url('admin.tickets.show', ['ticket' => $ticket->id]),
        ];
    }

    public static function ticketCreated(Ticket $ticket): void
    {
        $base = self::ticketBase($ticket);
        Notify::event('support.ticket_received', $base + ['workspace' => $ticket->user_id, 'dedup' => 'created']);
        Notify::event('support.ticket_new', $base + ['dedup' => 'created']);
    }

    public static function ticketUpdated(Ticket $ticket): void
    {
        $base = self::ticketBase($ticket);
        $stamp = (string) now()->timestamp;
        if ($ticket->wasChanged('assigned_to') && $ticket->assigned_to) {
            Notify::event('support.ticket_assigned', $base + ['users' => [$ticket->assigned_to], 'dedup' => 'assigned:'.$ticket->assigned_to.':'.$stamp]);
        }
        if ($ticket->wasChanged('priority') && $ticket->priority === 'urgent') {
            Notify::event('support.ticket_escalated', $base + ['users' => array_filter([$ticket->assigned_to]), 'dedup' => 'urgent:'.$stamp]);
        }
        if ($ticket->wasChanged('status') && in_array($ticket->status, ['resolved', 'closed'], true)) {
            Notify::event('support.ticket_'.$ticket->status, $base + ['workspace' => $ticket->user_id, 'dedup' => $ticket->status.':'.$stamp]);
        }
    }

    public static function ticketMessageCreated(TicketMessage $message): void
    {
        $ticket = Ticket::find($message->ticket_id);
        if (! $ticket || $message->is_internal_note || TicketMessage::where('ticket_id', $ticket->id)->count() <= 1) {
            return; // internal notes are staff-only; the opening message is covered by ticket_received / ticket_new
        }
        $author = \App\Models\User::find($message->user_id);
        $staff = $author && rescue(fn () => $author->isTeamMember(), false, false);
        // Billing and account requests are read in the app, not in the inbox (rule R10).
        $private = in_array(strtolower((string) $ticket->category), ['billing', 'account'], true);
        $base = self::ticketBase($ticket);
        $base['params']['excerpt'] = $private ? '—' : Support::excerpt($message->body, 300);
        $staff
            ? Notify::event('support.reply_received', $base + ['workspace' => $ticket->user_id, 'dedup' => 'message:'.$message->id])
            : Notify::event('support.customer_replied', $base + ['users' => array_filter([$ticket->assigned_to]), 'dedup' => 'message:'.$message->id]);
    }

    // ── Ads, email marketing, automations ──

    public static function adBuildUpdated($run): void
    {
        if (! $run->wasChanged('status') || $run->status !== 'failed') {
            return;
        }
        Notify::event('ads.build_failed', [
            'workspace' => $run->user_id, 'subject' => $run, 'users' => array_filter([Support::actorId()]),
            'params' => ['platform' => Support::platform($run->platform), 'step' => (string) ($run->failed_step ?: '—'), 'reason' => Support::excerpt($run->error, 200)],
            'url' => Support::url('admin.ads.dashboard'), 'dedup' => 'failed',
        ]);
    }

    public static function adReportUpdated($schedule): void
    {
        if (! $schedule->wasChanged('last_error') || ! $schedule->last_error) {
            return;
        }
        Notify::event('ads.report_failed', [
            'workspace' => $schedule->user_id, 'subject' => $schedule, 'params' => ['name' => (string) $schedule->name],
            'url' => Support::url('admin.ads.dashboard'), 'dedup' => now()->toDateString(),
        ]);
    }

    public static function campaignUpdated($campaign): void
    {
        if (! $campaign->wasChanged('status')) {
            return;
        }
        $returned = $campaign->status === 'draft' && $campaign->getOriginal('status') === 'scheduled' && ! Support::actorId();
        if ($campaign->status !== 'failed' && ! $returned) {
            return;
        }
        Notify::event('email_marketing.campaign_failed', [
            'workspace' => $campaign->user_id, 'subject' => $campaign,
            'params' => ['campaign' => (string) $campaign->name, 'reason_key' => $returned ? 'package_ended' : 'provider_error'],
            'url' => Support::url('admin.email.campaigns.edit', ['campaign' => $campaign->id], 'admin.email.dashboard'), 'dedup' => $campaign->status.':'.now()->timestamp,
        ]);
    }

    public static function automationRunSaved($run): void
    {
        if ($run->status !== 'failed' || ! $run->wasChanged('status') && ! $run->wasRecentlyCreated) {
            return;
        }
        $name = DB::table('crm_automations')->where('id', $run->automation_id)->value('name') ?? '—';
        Notify::event('crm.automation_failed', [
            'workspace' => $run->workspace_id, 'subject' => $run, 'params' => ['automation' => $name],
            'url' => Support::url('admin.crm.automations.index'), 'dedup' => 'failed',
        ]);
    }
}
