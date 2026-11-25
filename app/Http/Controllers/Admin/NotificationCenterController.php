<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Notifications\UserNotification;
use App\Models\User;
use App\Services\Notifications\Links;
use App\Services\Notifications\NotificationCatalogue;
use App\Services\Notifications\NotificationPresenter;
use App\Services\Notifications\PreferenceResolver;
use App\Services\Notifications\RecipientContext;
use App\Services\Notifications\WorkspaceAccess;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The person's own notification center and notification settings.
 * Always the real signed-in person (never the workspace account they act as),
 * so each teammate sees and controls only their own notifications.
 */
class NotificationCenterController extends Controller
{
    private function person(Request $request): User
    {
        return $request->attributes->get('workspace_actor') ?? $request->user();
    }

    private function find(Request $request, int $id): UserNotification
    {
        return UserNotification::where('user_id', $this->person($request)->id)->findOrFail($id);
    }

    public function page(Request $request, NotificationCatalogue $catalogue)
    {
        return view('admin.notifications.index', [
            'categories' => array_keys($catalogue->categories()),
            'feedUrl' => route('admin.notifications.feed'),
        ]);
    }

    /** Paginated list: ?filter=unread|all, ?category=, ?before=<id> (load more). */
    public function feed(Request $request, NotificationPresenter $presenter)
    {
        $data = $request->validate([
            'filter' => ['nullable', Rule::in(['all', 'unread'])],
            'category' => ['nullable', 'string', 'max:40'],
            'before' => ['nullable', 'integer'],
        ]);
        $person = $this->person($request);
        $query = UserNotification::where('user_id', $person->id)->visible()
            ->when(($data['filter'] ?? 'all') === 'unread', fn ($q) => $q->whereNull('read_at'))
            ->when($data['category'] ?? null, fn ($q, $c) => $q->where('category', $c))
            ->when($data['before'] ?? null, fn ($q, $id) => $q->where('id', '<', $id));
        $page = (clone $query)->orderByDesc('id')->limit(21)->get();

        return response()->json([
            'items' => $page->take(20)->map(fn ($n) => $presenter->item($n, $person))->values(),
            'has_more' => $page->count() > 20,
            'unread' => UserNotification::where('user_id', $person->id)->unread()->count(),
        ]);
    }

    public function read(Request $request, int $userNotification)
    {
        $this->find($request, $userNotification)->forceFill(['read_at' => now()])->save();

        return response()->json(['success' => true]);
    }

    public function readAll(Request $request)
    {
        $count = UserNotification::where('user_id', $this->person($request)->id)->unread()
            ->when($request->input('category'), fn ($q, $c) => $q->where('category', $c))->update(['read_at' => now()]);

        return response()->json(['success' => true, 'updated' => $count]);
    }

    public function dismiss(Request $request, int $userNotification)
    {
        $this->find($request, $userNotification)->forceFill(['archived_at' => now(), 'read_at' => now()])->save();

        return response()->json(['success' => true]);
    }

    public function clearRead(Request $request)
    {
        $count = UserNotification::where('user_id', $this->person($request)->id)->visible()->whereNotNull('read_at')->update(['archived_at' => now()]);

        return response()->json(['success' => true, 'updated' => $count]);
    }

    /** Opens a notification: marks it read, opens its workspace, then goes to its target. */
    public function open(Request $request, int $userNotification, WorkspaceAccess $access)
    {
        $n = $this->find($request, $userNotification);
        $n->forceFill(['read_at' => $n->read_at ?? now()])->save();
        if (! $access->enter($request, $this->person($request), $n->workspace_id)) {
            return response()->view('notifications.message', ['title' => __('notifications.ui.no_access_title'), 'body' => __('notifications.ui.no_access_body')], 403);
        }

        return redirect()->to(Links::localized($n->url, app()->getLocale()));
    }

    public function settings(Request $request, NotificationCatalogue $catalogue, PreferenceResolver $preferences, WorkspaceAccess $access)
    {
        $person = $this->person($request);
        $workspaceId = (int) $request->query('workspace', 0);
        $workspaces = $this->workspaces($person);
        abort_unless($workspaceId === 0 || $workspaces->has($workspaceId), 404);

        $groups = collect($catalogue->all())->groupBy('category', true)->map(fn ($events) => collect($events)
            ->filter(fn ($config) => ! $config['staff'] || $person->isTeamMember())
            ->map(fn ($config, $key) => [
                'key' => $key,
                'label' => \App\Services\Notifications\NotificationText::label($key),
                'mandatory' => $config['mandatory'],
                'failure_safe' => $config['failure_safe'],
                'allowed' => $config['allowed'],
                'choice' => $preferences->for($person, $config, $workspaceId ?: null),
            ])->values())->filter(fn ($events) => $events->isNotEmpty());

        return view('admin.notifications.settings', [
            'person' => $person,
            'groups' => $groups,
            'workspaces' => $workspaces,
            'workspaceId' => $workspaceId,
            'settings' => $person->notification_settings ?? [],
            'timezones' => timezone_identifiers_list(),
            'focus' => $request->query('category'),
        ]);
    }

    public function saveSettings(Request $request, NotificationCatalogue $catalogue, PreferenceResolver $preferences)
    {
        $person = $this->person($request);
        $workspaces = $this->workspaces($person);
        $data = $request->validate([
            'workspace' => ['nullable', 'integer'],
            'email_enabled' => ['nullable', 'boolean'],
            'locale' => ['nullable', Rule::in(RecipientContext::LOCALES)],
            'timezone' => ['nullable', 'timezone:all'],
            'quiet_enabled' => ['nullable', 'boolean'],
            'quiet_start' => ['nullable', 'date_format:H:i'],
            'quiet_end' => ['nullable', 'date_format:H:i'],
            'daily_hour' => ['nullable', 'integer', 'between:0,23'],
            'weekly_day' => ['nullable', 'integer', 'between:0,6'],
            'marketing' => ['nullable', 'boolean'],
            'events' => ['nullable', 'array'],
        ]);
        $workspaceId = (int) ($data['workspace'] ?? 0);
        abort_unless($workspaceId === 0 || $workspaces->has($workspaceId), 404);

        // Free-form nested input is read from the request and whitelisted against the catalogue.
        foreach ((array) $request->input('events', []) as $key => $choice) {
            if (! is_string($key) || ! $catalogue->has($key) || ! is_array($choice)) {
                continue;
            }
            $config = $catalogue->get($key);
            if ($config['mandatory']) {
                continue; // Required alerts cannot be switched off (the form shows them locked).
            }
            $email = in_array($choice['email'] ?? null, $config['allowed'], true) ? $choice['email'] : null;
            $inApp = array_key_exists('in_app', $choice) ? filter_var($choice['in_app'], FILTER_VALIDATE_BOOLEAN) : null;
            if ($config['failure_safe'] && $email === 'off') {
                $inApp = true;
            }
            $preferences->save($person, $workspaceId, 'event', $key, $inApp, $email);
        }

        if ($workspaceId === 0) {
            $settings = $person->notification_settings ?? [];
            $settings['email_enabled'] = $request->boolean('email_enabled', true);
            $settings['quiet_hours'] = ['enabled' => $request->boolean('quiet_enabled'), 'start' => $data['quiet_start'] ?? '22:00', 'end' => $data['quiet_end'] ?? '08:00'];
            $settings['daily_hour'] = (int) ($data['daily_hour'] ?? config('notifications.defaults.daily_digest_hour'));
            $settings['weekly_day'] = (int) ($data['weekly_day'] ?? config('notifications.defaults.weekly_digest_day'));
            $person->forceFill([
                'notification_settings' => $settings,
                'locale' => $data['locale'] ?? null,
                'timezone' => $data['timezone'] ?? null,
                // Marketing consent is separate and explicit (rule R18).
                'marketing_opt_in_at' => $request->boolean('marketing') ? ($person->marketing_opt_in_at ?? now()) : null,
            ])->save();
        }

        return $request->expectsJson()
            ? response()->json(['success' => true, 'message' => __('notifications.preferences.saved')])
            : redirect()->route('admin.notifications.settings', array_filter(['workspace' => $workspaceId]))->with('success', __('notifications.preferences.saved'));
    }

    public function resendVerification(Request $request)
    {
        $person = $this->person($request);
        if (! $person->hasVerifiedEmail()) {
            $person->sendEmailVerificationNotification();
        }

        return back()->with('success', __('notifications.ui.verify_sent'));
    }

    /** Workspaces this person may set overrides for: owned ones and joined ones. */
    private function workspaces(User $person)
    {
        $owned = rescue(fn () => \App\Models\Workspace::where('owner_id', $person->id)->pluck('name', 'account_id'), collect(), false);
        $joined = \App\Models\SellerTeamMember::where('user_id', $person->id)->whereNotNull('accepted_at')->get()
            ->mapWithKeys(fn ($m) => [$m->owner_id => $m->workspace_name]);

        return collect($owned)->union($joined);
    }
}
