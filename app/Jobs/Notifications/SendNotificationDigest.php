<?php

namespace App\Jobs\Notifications;

use App\Mail\BrandedPlatformMail;
use App\Mail\PlatformNotificationMail;
use App\Models\Notifications\EmailSuppression;
use App\Models\Notifications\NotificationDigestItem;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Notifications\Links;
use App\Services\Notifications\NotificationText;
use App\Services\Notifications\Notify;
use App\Services\Notifications\RecipientContext;
use App\Services\Notifications\RecipientResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * One person's daily or weekly digest: "Needs you" first, then highlights; repeated items
 * collapse into one line with a count. Items are claimed before sending (two dispatchers
 * can't send the same digest) and released again if the send fails. Never sends an empty digest.
 */
class SendNotificationDigest implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [300, 900, 1800];

    public function __construct(public int $userId, public string $cadence) {}

    public function handle(RecipientResolver $resolver): void
    {
        $user = User::find($this->userId);
        $claimed = DB::transaction(function () {
            $ids = NotificationDigestItem::where('user_id', $this->userId)->where('cadence', $this->cadence)->whereNull('sent_at')
                ->lockForUpdate()->limit(200)->pluck('id');
            NotificationDigestItem::whereIn('id', $ids)->update(['sent_at' => now()]);

            return $ids;
        });
        if ($claimed->isEmpty() || ! $user || ! $user->email || EmailSuppression::blocks($user->email)) {
            return;
        }

        $items = NotificationDigestItem::with('notificationEvent')->whereIn('id', $claimed)->get()
            ->filter(fn ($i) => $i->notificationEvent && $resolver->stillAllowed($user, $i->notificationEvent, $i->notificationEvent->config()));
        if ($items->isEmpty()) {
            return; // Never an empty digest.
        }

        $locale = RecipientContext::locale($user);
        $tz = RecipientContext::timezone($user);
        $names = [];
        $rows = $items->groupBy(fn ($i) => $i->notificationEvent->event.'|'.$i->notificationEvent->workspace_id)->map(function ($group) use ($user, $locale, $tz, &$names) {
            $event = $group->sortByDesc('id')->first()->notificationEvent;
            $config = $event->config();
            $text = NotificationText::render($event->event, $event->payload ?? [], $locale, $tz, $group->count());
            $ws = $event->workspace_id;
            $names[$ws] ??= $ws ? (rescue(fn () => Workspace::where('account_id', $ws)->value('name'), null, false) ?? User::whereKey($ws)->value('name')) : null;

            return [
                'section' => $config['section'], 'priority' => $config['priority'],
                'category' => trans('notifications.categories.'.$config['category'], [], $locale),
                'title' => $text['title'], 'count' => $group->count(), 'workspace' => $names[$ws],
                'url' => Links::email($event, $user),
            ];
        })->sortBy('priority')->values();

        $sections = collect(['attention', 'highlights'])
            ->map(fn ($s) => ['title' => trans('notifications.digest.'.$s, [], $locale), 'items' => $rows->where('section', $s)->values()->all()])
            ->filter(fn ($s) => $s['items'] !== [])->values()->all();
        $total = $rows->sum('count');
        $subject = NotificationText::line('notifications.digest.'.$this->cadence.'_subject', $total, ['count' => $total], $locale);

        try {
            Notify::$processing = true;
            $mailer = config('notifications.mailer') ? Mail::mailer(config('notifications.mailer')) : Mail::mailer();
            $mailer->to($user->email)->send(new BrandedPlatformMail(PlatformNotificationMail::layoutData($locale, 'report', [
                'subject' => $subject, 'preheader' => trans('notifications.digest.preheader', [], $locale),
                'headline' => trans('notifications.digest.'.$this->cadence.'_headline', [], $locale), 'cta' => trans('notifications.digest.cta', [], $locale),
            ], [
                'sections' => $sections, 'cta_url' => route('admin.notifications.center'),
                'category' => trans('notifications.digest.'.$this->cadence.'_headline', [], $locale), 'manage_url' => Links::preferences($user),
            ]), $locale));
        } catch (\Throwable $e) {
            NotificationDigestItem::whereIn('id', $claimed)->update(['sent_at' => null]);
            throw $e;
        } finally {
            Notify::$processing = false;
        }
        DB::table('notification_deliveries')->whereIn('notification_event_id', $items->pluck('notification_event_id'))
            ->where('user_id', $user->id)->where('channel', 'email')->where('status', 'digested')
            ->update(['status' => 'sent', 'sent_at' => now(), 'updated_at' => now()]);
    }
}
