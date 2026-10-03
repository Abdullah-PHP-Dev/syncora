<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\AdCampaign;
use App\Models\Messaging\Conversation;
use App\Models\Post;
use App\Models\SocialAccount;
use App\Models\Ticket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * The seller navbar's ⌘K search (was a dead input): the seller's own
 * accounts, posts, campaigns, conversations and support tickets matching
 * the query, grouped, each with the URL it opens. Every query is scoped
 * to the signed-in seller.
 */
class GlobalSearchController extends Controller
{
    private const PER_GROUP = 5;

    private static function platformName(?string $platform): string
    {
        return ['x' => 'X', 'twitter' => 'X', 'tiktok' => 'TikTok', 'linkedin' => 'LinkedIn', 'youtube' => 'YouTube', 'whatsapp' => 'WhatsApp', 'google_chat' => 'Google Chat'][$platform] ?? ucfirst((string) $platform);
    }

    public function __invoke(Request $request): JsonResponse
    {
        $term = trim((string) $request->query('q', ''));
        if (mb_strlen($term) < 2) {
            return response()->json(['groups' => []]);
        }

        $userId = Auth::id();
        $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term) . '%';
        $groups = [];

        $accounts = SocialAccount::where('user_id', $userId)
            ->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('username', 'like', $like))
            ->limit(self::PER_GROUP)->get(['id', 'platform', 'name', 'username', 'has_ads_permission', 'has_posting_permission']);
        $groups[] = ['label' => 'Accounts', 'items' => $accounts->map(fn ($a) => [
            'title'    => $a->name ?: ($a->username ?: ucfirst($a->platform)),
            'subtitle' => self::platformName($a->platform) . ($a->username ? ' · @' . ltrim($a->username, '@') : ''),
            'icon'     => 'bx-user-circle',
            'url'      => $a->has_ads_permission && !$a->has_posting_permission
                ? route('admin.ads.campaigns.index', ['platform' => $a->platform])
                : route('admin.posts.index', ['platform' => $a->platform]),
        ])];

        $posts = Post::where('user_id', $userId)->where('content', 'like', $like)
            ->latest('id')->limit(self::PER_GROUP)->get(['id', 'platform', 'content', 'created_at']);
        $groups[] = ['label' => 'Posts', 'items' => $posts->map(fn ($p) => [
            'title'    => Str::limit($p->content, 70),
            'subtitle' => self::platformName($p->platform) . ' · ' . $p->created_at?->format('M j, Y'),
            'icon'     => 'bx-edit-alt',
            'url'      => route('admin.posts.preview', ['post' => $p->id, 'platform' => $p->platform ?: 'facebook']),
        ])];

        $campaigns = AdCampaign::where('user_id', $userId)->where('name', 'like', $like)
            ->latest('id')->limit(self::PER_GROUP)->get(['id', 'platform', 'name', 'status']);
        $groups[] = ['label' => 'Campaigns', 'items' => $campaigns->map(fn ($c) => [
            'title'    => $c->name,
            'subtitle' => self::platformName($c->platform) . ($c->status ? ' · ' . ucfirst(strtolower($c->status)) : ''),
            'icon'     => 'bx-bullseye',
            'url'      => route('admin.ads.campaigns.edit', ['platform' => $c->platform, 'campaign' => $c->id]),
        ])];

        $conversations = Conversation::whereIn('social_account_id', SocialAccount::where('user_id', $userId)->select('id'))
            ->where(fn ($q) => $q->where('customer_name', 'like', $like)->orWhere('last_message_preview', 'like', $like))
            ->latest('last_message_at')->limit(self::PER_GROUP)->get(['id', 'platform', 'customer_name', 'last_message_preview']);
        $groups[] = ['label' => 'Conversations', 'items' => $conversations->map(fn ($c) => [
            'title'    => $c->customer_name ?: 'Unknown',
            'subtitle' => self::platformName($c->platform) . ($c->last_message_preview ? ' · ' . Str::limit($c->last_message_preview, 50) : ''),
            'icon'     => 'bx-message-square-dots',
            'url'      => route('admin.chats.dashboard', ['conversation' => $c->id]),
        ])];

        $tickets = Ticket::where('user_id', $userId)
            ->where(fn ($q) => $q->where('subject', 'like', $like)->orWhere('ticket_number', 'like', $like))
            ->latest('id')->limit(self::PER_GROUP)->get(['id', 'ticket_number', 'subject', 'status']);
        $groups[] = ['label' => 'Support tickets', 'items' => $tickets->map(fn ($t) => [
            'title'    => $t->subject,
            'subtitle' => $t->ticket_number . ' · ' . str_replace('_', ' ', $t->status),
            'icon'     => 'bx-support',
            'url'      => route('admin.tickets.show', $t),
        ])];

        return response()->json([
            'groups' => array_values(array_filter($groups, fn ($g) => count($g['items']) > 0)),
        ]);
    }
}
