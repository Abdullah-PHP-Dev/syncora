<?php

namespace App\Http\Controllers;

use App\Models\Notifications\NotificationEvent;
use App\Models\Notifications\UserNotification;
use App\Models\User;
use App\Services\Notifications\Links;
use App\Services\Notifications\NotificationCatalogue;
use App\Services\Notifications\PreferenceResolver;
use App\Services\Notifications\RecipientContext;
use App\Services\Notifications\WorkspaceAccess;
use Illuminate\Http\Request;

/**
 * Links inside platform emails (all signed, none carrying credentials):
 *  - go:          sign-in if needed, re-check access, open the right workspace, then the target (R16)
 *  - unsubscribe: RFC 8058 one-click (POST) or a confirm page (GET); optional mail only
 *  - manage:      the notification settings page, opened at the email's category
 */
class NotificationLinkController extends Controller
{
    public function go(Request $request, int $event, int $user, WorkspaceAccess $access)
    {
        if (! $request->hasValidSignature()) {
            return $this->message('link_expired', 403);
        }
        $record = NotificationEvent::find($event);
        $target = $record?->payload['url'] ?? null;
        if (! $user) {
            // Recipients without an account (invitations) are linked straight to the target.
            return redirect()->to(Links::localized($target, app()->getLocale()));
        }
        $person = $request->user();
        if (! $person) {
            $request->session()->put('url.intended', $request->fullUrl());

            return redirect()->route('login');
        }
        if ($person->id !== $user || ! $record) {
            return $this->message('no_access', 403);
        }
        UserNotification::where('user_id', $person->id)->where('notification_event_id', $record->id)->whereNull('read_at')->update(['read_at' => now()]);
        if (! $access->enter($request, $person, $record->workspace_id)) {
            return $this->message('no_access', 403);
        }
        return redirect()->to(Links::localized($target, RecipientContext::locale($person, $record->workspace_id)));
    }

    public function unsubscribe(Request $request, NotificationCatalogue $catalogue, PreferenceResolver $preferences)
    {
        if (! $request->hasValidSignature()) {
            return $this->message('link_expired', 403);
        }
        $person = User::find((int) $request->query('user'));
        $event = (string) $request->query('event');
        if (! $person || ! $catalogue->has($event) || $catalogue->get($event)['mandatory']) {
            return $this->message('link_expired', 404);
        }
        $locale = RecipientContext::locale($person);
        app()->setLocale($locale);
        if ($request->isMethod('post')) {
            $config = $catalogue->get($event);
            $preferences->save($person, (int) $request->query('workspace', 0), 'event', $event, $config['failure_safe'] ? true : null, 'off');

            return $request->expectsJson() || ! $request->has('confirm')
                ? response(__('notifications.ui.unsubscribed'), 200)
                : view('notifications.message', ['title' => __('notifications.ui.unsubscribe_confirm'), 'body' => __('notifications.ui.unsubscribed')]);
        }

        return view('notifications.unsubscribe', ['event' => \App\Services\Notifications\NotificationText::label($event), 'action' => $request->fullUrl()]);
    }

    public function manage(Request $request)
    {
        if (! $request->hasValidSignature()) {
            return $this->message('link_expired', 403);
        }

        return redirect()->route('admin.notifications.settings', array_filter(['category' => $request->query('category')]));
    }

    private function message(string $kind, int $status)
    {
        return response()->view('notifications.message', [
            'title' => __("notifications.ui.{$kind}_title"), 'body' => __("notifications.ui.{$kind}_body"),
        ], $status);
    }
}
