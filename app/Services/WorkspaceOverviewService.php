<?php

namespace App\Services;

use App\Models\{CopilotMessage, Faq, MediaAsset, Post, PostComment, SocialAccount, Ticket, User, UserIntegration};
use App\Models\Admin\AdCampaign;
use App\Models\EmailMarketing\{EmailCampaign, EmailSubscriber};
use App\Models\Messaging\Conversation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

class WorkspaceOverviewService
{
    public function data(User $user, int $days): array
    {
        $start = today()->subDays($days - 1);
        $end = now();
        $owned = fn (string $model): Builder => $model::query()->where('user_id', $user->id);
        $queries = [
            'posts' => $owned(Post::class), 'ads' => $owned(AdCampaign::class),
            'email' => $owned(EmailCampaign::class), 'comments' => $owned(PostComment::class),
            'ai' => $owned(CopilotMessage::class), 'media' => $owned(MediaAsset::class),
            'connections' => $owned(SocialAccount::class)->where('is_token_valid', true),
            'support' => $owned(Ticket::class),
            'knowledge' => $owned(Faq::class),
            'integrations' => $owned(UserIntegration::class)->where('is_enabled', true),
            'audience' => $owned(EmailSubscriber::class)->where('status', 'subscribed'),
            'inbox' => Conversation::query()->whereHas('channel', fn ($q) => $q->where('user_id', $user->id)),
        ];
        $definitions = [
            'posts' => ['Publishing', 'bx-calendar-edit', 'admin.posts.dashboard'],
            'ads' => ['Ad campaigns', 'bx-bullseye', 'admin.ads.dashboard'],
            'email' => ['Email marketing', 'bx-envelope', 'admin.email.dashboard'],
            'inbox' => ['Inbox', 'bx-message-square-dots', 'admin.chats.dashboard'],
            'comments' => ['Comments', 'bx-comment-detail', 'admin.comments.dashboard'],
            'ai' => ['AI assistant', 'bx-bot', 'admin.ai-copilot.analytics.index'],
            'media' => ['Media library', 'bx-images', 'admin.media-gallery.index'],
            'connections' => ['Connected accounts', 'bx-plug', 'admin.connections.index'],
            'support' => ['Support tickets', 'bx-support', 'admin.tickets.index'],
            'knowledge' => ['Knowledge base', 'bx-book-open', 'admin.knowledge-base.index'],
            'integrations' => ['Integrations', 'bx-extension', 'admin.integrations.index'],
            'audience' => ['Email subscribers', 'bx-group', 'admin.email.lists.index'],
        ];
        $modules = [];
        $available = [];
        foreach ($definitions as $key => [$label, $icon, $route]) {
            $query = $queries[$key];
            $available[$key] = Schema::hasTable($query->getModel()->getTable());
            if ($key === 'inbox') $available[$key] = $available[$key] && Schema::hasTable('social_accounts');
            if (!$available[$key]) {
                $modules[] = compact('key', 'label', 'icon', 'route') + ['current' => null, 'previous' => null, 'total' => null, 'available' => false];
                continue;
            }
            $current = (clone $query)->whereBetween('created_at', [$start, $end])->count();
            $previous = (clone $query)->whereBetween('created_at', [$start->copy()->subDays($days), $start->copy()->subMicrosecond()])->count();
            $modules[] = compact('key', 'label', 'icon', 'route', 'current', 'previous') + ['total' => (clone $query)->count(), 'available' => true];
        }
        $statuses = $available['posts'] ? (clone $queries['posts'])->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status')->map(fn ($v) => (int) $v)->all() : [];
        $platforms = $available['connections'] ? (clone $queries['connections'])->selectRaw('platform, COUNT(*) as total')->groupBy('platform')->pluck('total', 'platform')->map(fn ($v) => (int) $v)->all() : [];
        $series = [];
        foreach (['posts', 'ads', 'email', 'inbox', 'comments', 'ai'] as $key) {
            if (!$available[$key]) continue;
            $counts = (clone $queries[$key])->whereBetween('created_at', [$start, $end])
                ->selectRaw('DATE(created_at) as day, COUNT(*) as total')->groupByRaw('DATE(created_at)')->pluck('total', 'day');
            $series[$key] = [];
            for ($i = 0; $i < $days; $i++) {
                $series[$key][] = (int) ($counts[$start->copy()->addDays($i)->toDateString()] ?? 0);
            }
        }
        $labels = [];
        for ($i = 0; $i < $days; $i++) $labels[] = $start->copy()->addDays($i)->toDateString();
        $recent = $available['posts'] ? (clone $queries['posts'])->latest()->limit(5)->get(['id', 'title', 'content', 'platform', 'status', 'created_at']) : collect();
        $scheduled = $available['posts'] ? (clone $queries['posts'])->where('status', 'scheduled')->where('schedule_at', '>=', $end)->orderBy('schedule_at')->limit(4)->get(['id', 'title', 'content', 'platform', 'schedule_at']) : collect();
        $unread = $available['inbox'] ? (int) (clone $queries['inbox'])->sum('unread_count') : 0;
        $failed = (int) ($statuses['failed'] ?? 0);
        $openTickets = $available['support'] ? (clone $queries['support'])->whereNotIn('status', ['resolved', 'closed'])->count() : 0;
        $subscribers = $available['audience'] ? $owned(EmailSubscriber::class)->where('status', 'subscribed')->count() : 0;
        $emailTotals = $available['email'] ? (clone $queries['email'])->whereBetween('sent_at', [$start, $end])->selectRaw('COALESCE(SUM(sent_count),0) as sent, COALESCE(SUM(delivered_count),0) as delivered, COALESCE(SUM(opened_count),0) as opened, COALESCE(SUM(clicked_count),0) as clicked')->first() : (object) ['sent' => 0, 'delivered' => 0, 'opened' => 0, 'clicked' => 0];
        return compact('modules', 'available', 'statuses', 'platforms', 'series', 'labels', 'recent', 'scheduled', 'unread', 'failed', 'openTickets', 'subscribers', 'emailTotals', 'days', 'start', 'end');
    }
}
