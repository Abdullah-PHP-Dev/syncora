<?php

namespace App\Console\Commands\Notifications;

use App\Mail\PlatformNotificationMail;
use App\Models\Notifications\NotificationDelivery;
use App\Models\Notifications\NotificationEvent;
use App\Services\Notifications\NotificationCatalogue;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/** Renders a notification email to an HTML file for review (nothing is stored or sent). */
class PreviewNotification extends Command
{
    protected $signature = 'notifications:preview {event? : Catalogue key; omit for all} {--locale=en} {--count=1 : Render the grouped variant with this many items}';

    protected $description = 'Render platform notification emails to storage/app/notification-previews (no email is sent)';

    private const SAMPLE = [
        'name' => 'Sara', 'platform' => 'Instagram', 'account' => '@acme', 'excerpt' => 'Spring offer: 20% off all orders this week', 'reason' => 'Media aspect ratio not supported',
        'plan' => 'Growth', 'amount' => 'SAR 199.00', 'receipt' => 'TX-10423', 'days' => 3, 'choice' => 3, 'feature_key' => 'ai_credits', 'used' => 800, 'limit' => 1000, 'percent' => 80,
        'inviter' => 'Sara Ahmed', 'workspace' => 'Acme Trading', 'role_key' => 'member', 'email' => 'omar@example.com', 'scheduled' => 3, 'actor' => 'Omar',
        'succeeded' => 2, 'total' => 3, 'failed' => 'LinkedIn', 'customer' => 'Mohammed', 'channel' => 'WhatsApp', 'minutes' => 72, 'author' => 'Noura',
        'value' => 'SAR 48,000', 'subject' => 'Follow up with Noura', 'automation' => 'Welcome new leads', 'message' => 'New lead from the website',
        'step' => 'create_ad_set', 'campaign' => 'November Newsletter', 'reason_key' => 'provider_error', 'service' => 'Analytics', 'ticket' => 'SLZ-10423',
        'priority' => 'High', 'device' => 'Chrome · Windows', 'network' => '188.50.x.x', 'source' => 'Facebook lead ad', 'method' => 'Tamara',
    ];

    public function handle(NotificationCatalogue $catalogue): int
    {
        $events = $this->argument('event') ? [$this->argument('event')] : array_keys($catalogue->all());
        $dir = storage_path('app/notification-previews');
        File::ensureDirectoryExists($dir);
        foreach ($events as $key) {
            $deliveries = [];
            for ($i = 0; $i < max(1, (int) $this->option('count')); $i++) {
                $event = new NotificationEvent(['event' => $key, 'payload' => ['params' => self::SAMPLE, 'dates' => ['when' => now()->toIso8601String(), 'end' => now()->addDays(3)->toIso8601String(), 'expires' => now()->addDays(7)->toIso8601String(), 'period_end' => now()->addMonth()->toIso8601String(), 'due' => now()->addHour()->toIso8601String()], 'url' => '/dashboard'], 'occurred_at' => now()]);
                $event->id = 900000 + $i;
                $delivery = new NotificationDelivery(['locale' => $this->option('locale'), 'to_address' => 'preview@example.com']);
                $delivery->setRelation('notificationEvent', $event)->setRelation('user', null);
                $deliveries[] = $delivery;
            }
            $html = (new PlatformNotificationMail($deliveries, ['url' => url('/preview')]))->render();
            File::put($path = "$dir/$key.{$this->option('locale')}.html", $html);
            $this->line($path);
        }

        return self::SUCCESS;
    }
}
