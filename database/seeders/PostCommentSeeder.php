<?php

namespace Database\Seeders;

use App\Models\Post;
use App\Models\PostComment;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Seeds top-level comments + nested replies for a single post so its
 * preview page (resources/js/components/posts/PostPreview.vue) has
 * something to render while the post's real Instagram access token is
 * expired (see InstagramPostService::fetchComments).
 */
class PostCommentSeeder extends Seeder
{
    private int $postId = 94;

    public function run(): void
    {
        $post = Post::find($this->postId);

        if (!$post) {
            $this->command?->warn("Post {$this->postId} not found, skipping.");
            return;
        }

        PostComment::where('post_id', $post->id)->delete();

        $commentCount = fake()->numberBetween(6, 9);

        for ($i = 0; $i < $commentCount; $i++) {
            $postedAt = Carbon::now()->subDays(fake()->numberBetween(0, 6))->subMinutes(fake()->numberBetween(0, 600));

            $comment = PostComment::create([
                'platform' => 'instagram',
                'comment_id' => 'seed_' . Str::random(12),
                'parent_comment_id' => null,
                'post_account_id' => $post->post_account_id,
                'post_id' => $post->id,
                'user_name' => fake()->userName(),
                'content' => fake()->realText(fake()->numberBetween(20, 140)),
                'likes' => fake()->numberBetween(0, 60),
                'is_reply' => false,
                'sender_type' => 'customer',
                'status' => 'approved',
                'posted_at' => $postedAt,
            ]);

            $replyCount = fake()->numberBetween(0, 3);

            for ($r = 0; $r < $replyCount; $r++) {
                $isSupportReply = $r === 0 && fake()->boolean(40);

                PostComment::create([
                    'platform' => 'instagram',
                    'comment_id' => 'seed_' . Str::random(12),
                    'parent_comment_id' => $comment->id,
                    'post_account_id' => $post->post_account_id,
                    'post_id' => $post->id,
                    'user_name' => $isSupportReply ? ($post->user->name ?? 'Support') : fake()->userName(),
                    'content' => fake()->realText(fake()->numberBetween(10, 100)),
                    'likes' => fake()->numberBetween(0, 25),
                    'is_reply' => true,
                    'sender_type' => $isSupportReply ? 'support' : 'customer',
                    'status' => 'approved',
                    'posted_at' => (clone $postedAt)->addMinutes(fake()->numberBetween(5, 500)),
                ]);
            }
        }

        $this->command?->info("Seeded comments for post {$this->postId}.");
    }
}
