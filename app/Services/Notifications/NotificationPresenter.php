<?php

namespace App\Services\Notifications;

use App\Models\Notifications\UserNotification;
use App\Models\User;
use App\Models\Workspace;

/** A stored notification as the bell / notification center shows it, in the viewer's language. */
class NotificationPresenter
{
    private array $workspaces = [];

    public function item(UserNotification $n, ?User $viewer = null): array
    {
        $locale = in_array(app()->getLocale(), RecipientContext::LOCALES, true) ? app()->getLocale() : 'en';
        $viewer ??= User::find($n->user_id);
        $stored = $n->params ?? [];
        $payload = isset($stored['legacy_title']) ? ['params' => $stored] : $stored;
        $text = NotificationText::render($n->event, $payload, $locale, RecipientContext::timezone($viewer, $n->workspace_id), max(1, (int) $n->group_count));
        $config = rescue(fn () => app(NotificationCatalogue::class)->get($n->event), ['tone' => 'info'], false);

        return [
            'type' => 'platform',
            'id' => $n->id,
            'event' => $n->event,
            'category' => $n->category,
            'category_label' => trans('notifications.categories.'.$n->category, [], $locale),
            'icon' => config('notifications.categories.'.$n->category.'.icon', 'bx-bell'),
            'priority' => (int) $n->priority,
            'tone' => $config['tone'] ?? 'info',
            'title' => $text['title'],
            'author' => $text['title'],
            'preview' => $text['body'],
            'count' => (int) $n->group_count,
            'workspace' => $this->workspaceName($n->workspace_id),
            'read' => $n->read_at !== null,
            'url' => route('admin.notifications.open', $n->id),
            'read_url' => route('admin.notifications.read', $n->id),
            'dismiss_url' => route('admin.notifications.dismiss', $n->id),
            'created_at' => optional($n->updated_at ?? $n->created_at)->toIso8601String(),
        ];
    }

    private function workspaceName(?int $accountId): ?string
    {
        if (! $accountId) {
            return null;
        }

        return $this->workspaces[$accountId] ??= rescue(fn () => Workspace::where('account_id', $accountId)->value('name'), null, false)
            ?? User::whereKey($accountId)->value('name');
    }
}
