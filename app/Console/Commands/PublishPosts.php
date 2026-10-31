<?php

namespace App\Console\Commands;

use App\Models\Post;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use App\Services\PostServices\MetaPostService;
use App\Services\PostServices\InstagramPostService;
use App\Services\PostServices\GooglePostService;
use App\Services\PostServices\YoutubePostService;
use App\Services\PostServices\TiktokPostService;
use App\Services\PostServices\XPostService;
use App\Services\PostServices\LinkedInPostService;
use App\Services\PostServices\WhatsAppPostService;
use App\Services\PostServices\ThreadsPostService;
use App\Services\PostServices\PinterestPostService;

class PublishPosts extends Command
{
    protected $signature = 'social:publish-posts';

    protected $description = 'Publish scheduled social media posts';

    protected array $services = [];

    public function __construct(
        MetaPostService $metaService,
        InstagramPostService $instagramService,
        GooglePostService $googleService,
        YoutubePostService $youtubeService,
        TiktokPostService $tiktokService,
        XPostService $xService,
        LinkedInPostService $linkedinService,
        WhatsAppPostService $whatsappService,
        ThreadsPostService $threadsService,
        PinterestPostService $pinterestService,
    ) {
        parent::__construct();

        $this->services = [
            'facebook'  => $metaService,
            'instagram' => $instagramService,
            'google'    => $googleService,
            'tiktok'    => $tiktokService,
            'youtube'   => $youtubeService,
            'x'         => $xService,
            'linkedin'  => $linkedinService,
            'whatsapp'  => $whatsappService,
            'threads'   => $threadsService,
            'pinterest' => $pinterestService,
        ];
    }

    /** Attempts for a post whose earlier tries certainly never reached the platform. */
    private const ATTEMPTS = 3;

    /** A claim older than this belongs to a run that died mid-send. */
    private const STUCK_MINUTES = 30;

    public function handle(): int
    {
        $this->info('Publishing scheduled posts...');

        // A run that died while sending leaves its claim behind. The post may
        // or may not have reached the platform, so it is never re-sent blindly.
        Post::where('status', 'publishing')->where('updated_at', '<', now()->subMinutes(self::STUCK_MINUTES))
            ->update(['status' => 'failed', 'error_message' => 'Publishing was interrupted. Check the platform before posting it again.']);

        // Only posts waiting to go out whose time has come. "!= completed" also
        // picked up failed posts (retried every run, forever) and drafts.
        Post::with(['socialAccount', 'media'])
            ->whereIn('status', ['pending', 'scheduled'])
            ->where(fn ($q) => $q->where('schedule_mode', 0)->orWhereNull('schedule_at')->orWhere('schedule_at', '<=', now()))
            ->orderBy('id')
            ->chunkById(50, function ($posts) {
                foreach ($posts as $post) {
                    if (!isset($this->services[$post->platform])) {
                        Log::warning("Unsupported platform: {$post->platform}");
                        continue;
                    }

                    // Claim it: whichever run flips the status first sends it,
                    // so overlapping runs can't publish a post twice.
                    if (!Post::whereKey($post->id)->whereIn('status', ['pending', 'scheduled'])->update(['status' => 'publishing', 'updated_at' => now()])) {
                        continue;
                    }
                    $post->status = 'publishing';

                    $this->publish($post);
                }
            });

        $this->info('Publishing completed.');

        return self::SUCCESS;
    }

    private function publish(Post $post): void
    {
        try {
            $response = $this->services[$post->platform]->publishPost($post);
            $success = (bool) ($response['success'] ?? false);
            $reason = $response['message'] ?? $response['error'] ?? null;

            // Services record the outcome; settle the claim if one didn't.
            $fresh = $post->fresh();
            if ($fresh && $fresh->status === 'publishing') {
                $fresh->update($success
                    ? ['status' => 'completed']
                    : ['status' => 'failed', 'error_message' => Post::cleanErrorMessage(is_string($reason) ? $reason : 'The platform rejected the post without saying why.')]);
            }

            if (!$success) {
                $reason ??= $fresh?->error_message ?? 'no reason given by the platform';
                Log::error("Post {$post->id} failed", (array) $response + ['reason' => $reason]);
                $this->error("Post #{$post->id} failed: " . (is_string($reason) ? $reason : json_encode($reason)));

                return;
            }

            $this->info("Published Post #{$post->id}");
        } catch (\Throwable $e) {
            if (($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface && $e->getStatusCode() === 403)
                || ($e instanceof \Illuminate\Validation\ValidationException && array_key_exists('package', $e->errors()))) {
                $this->info("Post #{$post->id} is waiting for package access or allowance.");
                $this->release($post, []);

                return;
            }

            Log::error("Post {$post->id} Exception: {$e->getMessage()}", ['trace' => $e->getTraceAsString()]);
            $this->error("Post #{$post->id} failed: {$e->getMessage()}");

            $attempts = (int) ($post->platform_metadata['publish_attempts'] ?? 0) + 1;
            if ($this->neverReachedPlatform($e, $post) && $attempts < self::ATTEMPTS) {
                $this->info("Post #{$post->id} will be retried (attempt {$attempts} of " . self::ATTEMPTS . ').');
                $this->release($post, ['publish_attempts' => $attempts]);

                return;
            }

            // Never leave a crashed post claimed - it would block the seller.
            Post::whereKey($post->id)->whereIn('status', ['publishing', 'pending'])->update([
                'status' => 'failed',
                'error_message' => Post::cleanErrorMessage($e->getMessage()),
            ]);
        }
    }

    /** Back to the queue for the next run, keeping any retry count. */
    private function release(Post $post, array $metadata): void
    {
        $fresh = $post->fresh();
        if ($fresh && $fresh->status === 'publishing') {
            $fresh->forceFill([
                'status' => 'pending',
                'platform_metadata' => $metadata ? array_merge($fresh->platform_metadata ?? [], $metadata) : $fresh->platform_metadata,
            ])->save();
        }
    }

    /**
     * Retrying is only safe when the platform certainly didn't get the post:
     * it couldn't be reached at all (DNS or connect failure), or it refused
     * the request outright (429 rate limit, 503 unavailable). A timeout after
     * sending may have posted it, so that is never retried.
     */
    private function neverReachedPlatform(\Throwable $e, Post $post): bool
    {
        if (!empty($post->fresh()?->post_id)) {
            return false;
        }
        if ($e instanceof \Illuminate\Http\Client\RequestException) {
            return in_array($e->response->status(), [429, 503], true);
        }
        if ($e instanceof \Illuminate\Http\Client\ConnectionException) {
            return (bool) preg_match('/cURL error (6|7):/', $e->getMessage());
        }

        return false;
    }
}
