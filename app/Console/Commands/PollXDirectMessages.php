<?php

namespace App\Console\Commands;

use App\Models\Messaging\MessageChannel;
use App\Services\MessagingServices\XMessagingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * X has no realistically obtainable real-time DM webhook (that needs the
 * Enterprise/Premium Account Activity API tier) - this polls GET
 * /2/dm_events for every connected X channel instead, on a schedule (see
 * bootstrap/app.php's withSchedule()), using each channel's stored
 * pagination cursor so only genuinely new events are fetched each run.
 */
class PollXDirectMessages extends Command
{
    protected $signature = 'messaging:poll-x-dms';

    protected $description = 'Poll X (Twitter) for new Direct Messages across every connected channel';

    public function handle(XMessagingService $service): int
    {
        $channels = MessageChannel::where('platform', 'x')->where('status', true)->get();

        foreach ($channels as $channel) {
            try {
                $service->pollMessages($channel);
                $this->info("Polled X channel #{$channel->id} ({$channel->name})");
            } catch (\Throwable $e) {
                Log::error("X DM poll failed for channel #{$channel->id}: {$e->getMessage()}", ['trace' => $e->getTraceAsString()]);
                $this->error("Channel #{$channel->id} failed.");
            }
        }

        return self::SUCCESS;
    }
}
