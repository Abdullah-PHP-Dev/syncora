<?php

namespace App\Console\Commands;

use App\Models\Post;
use Carbon\Carbon;
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

    public function handle(): int
    {
        $this->info('Publishing scheduled posts...');

        // Only posts waiting to go out. "!= completed" also picked up failed
        // posts (retried every run, forever) and drafts (never meant to post).
        Post::with(['socialAccount', 'media'])
            ->where('status', 'pending')
            ->orderBy('id')
            ->chunkById(50, function ($posts) {
           
                foreach ($posts as $post) {

                    try {

                        if (!isset($this->services[$post->platform])) {
                            Log::warning("Unsupported platform: {$post->platform}");
                            continue;
                        }

                        if ($post->schedule_mode && Carbon::parse($post->schedule_at)->isFuture()) {
                            continue;
                        }   
                       
                        $response = $this->services[$post->platform]->publishPost($post);

                        if (!($response['success'] ?? false)) {
                            // Services save the reason on the post; not all return it.
                            $reason = $response['message'] ?? $response['error'] ?? $post->fresh()->error_message ?? 'no reason given by the platform';
                            Log::error("Post {$post->id} failed", $response + ['reason' => $reason]);
                            $this->error("Post #{$post->id} failed: " . (is_string($reason) ? $reason : json_encode($reason)));

                            continue;
                        }

                        $this->info("Published Post #{$post->id}");

                    } catch (\Throwable $e) {

                        Log::error(
                            "Post {$post->id} Exception: {$e->getMessage()}",
                            [
                                'trace' => $e->getTraceAsString()
                            ]
                        );
                        $this->error("Post #{$post->id} failed: {$e->getMessage()}");

                        // Never leave a crashed post pending - it would be retried every run.
                        Post::whereKey($post->id)->where('status', 'pending')->update([
                            'status' => 'failed',
                            'error_message' => Post::cleanErrorMessage($e->getMessage()),
                        ]);
                    }
                }
            });

        $this->info('Publishing completed.');

        return self::SUCCESS;
    }
}