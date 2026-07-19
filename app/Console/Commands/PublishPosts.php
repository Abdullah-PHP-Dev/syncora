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
        ];
    }

    public function handle(): int
    {
        $this->info('Publishing scheduled posts...');

        Post::with(['postAccount', 'media'])
            ->where('status', '!=', 'completed')
            ->orderBy('id')
            ->chunkById(50, function ($posts) {

                foreach ($posts as $post) {

                    try {

                        if (!isset($this->services[$post->platform])) {
                            Log::warning("Unsupported platform: {$post->platform}");
                            continue;
                        }

                        if ($post->schedule_mode) {
                            $scheduleTime = Carbon::parse($post->schedule_at);
                    
                            if ($scheduleTime->isFuture()) {
                                continue;
                            }
                        }   
                      
                        $response = $this->services[$post->platform]->publishPost($post);
                     
                        if (!($response['success'] ?? false)) {

                            Log::error("Post {$post->id} failed", $response);

                            continue;
                        }

                        $this->info("Published Post #{$post->id}");

                    } catch (\Throwable $e) {
                        dd($e->getMessage());
                        Log::error(
                            "Post {$post->id} Exception: {$e->getMessage()}",
                            [
                                'trace' => $e->getTraceAsString()
                            ]
                        );

                        $this->error("Post {$post->id} failed.");
                    }
                }
            });

        $this->info('Publishing completed.');

        return self::SUCCESS;
    }
}