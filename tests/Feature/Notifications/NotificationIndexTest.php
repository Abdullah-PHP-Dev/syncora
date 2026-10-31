<?php

namespace Tests\Feature\Notifications;

use App\Http\Controllers\Admin\NotificationController;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Feature\Connections\CreatesConnectionTables;
use Tests\TestCase;

/** The navbar notification center (GET /notifications). */
class NotificationIndexTest extends TestCase
{
    use CreatesConnectionTables;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createConnectionTables();
        Schema::create('post_comments', function ($t) {
            $t->id();
            foreach (['platform', 'comment_id', 'sender_type', 'user_name', 'status'] as $c) {
                $t->string($c)->nullable();
            }
            foreach (['parent_comment_id', 'user_id', 'social_account_id', 'post_id'] as $c) {
                $t->unsignedBigInteger($c)->nullable();
            }
            $t->text('user_avatar_url')->nullable();
            $t->text('content')->nullable();
            $t->timestamp('read_at')->nullable();
            $t->timestamps();
        });
        Schema::create('posts', function ($t) {
            $t->id();
            $t->string('platform')->nullable();
        });
        Schema::create('conversations', function ($t) {
            $t->id();
            $t->unsignedBigInteger('social_account_id');
            $t->string('platform')->nullable();
            $t->string('customer_name')->nullable();
            $t->text('customer_avatar_url')->nullable();
            $t->string('last_message_preview')->nullable();
            $t->unsignedInteger('unread_count')->default(0);
            $t->timestamp('last_message_at')->nullable();
            $t->timestamps();
        });

        $this->user = User::create(['name' => 'Seller', 'email' => 's@example.com', 'password' => bcrypt('x')]);
        $this->actingAs($this->user);
    }

    public function test_a_comment_without_a_post_does_not_break_the_list(): void
    {
        // Webhook comments can arrive before (or without) being matched to a post.
        DB::table('post_comments')->insert([
            ['user_id' => $this->user->id, 'platform' => 'facebook', 'user_name' => 'Sara', 'content' => 'Unlinked', 'post_id' => null, 'created_at' => now(), 'updated_at' => now()],
            ['user_id' => $this->user->id, 'platform' => 'facebook', 'user_name' => 'Omar', 'content' => 'Linked', 'post_id' => 42, 'created_at' => now()->subMinute(), 'updated_at' => now()],
        ]);

        $data = app(NotificationController::class)->index()->getData(true);

        $this->assertSame(2, $data['count']);
        $urls = collect($data['items'])->pluck('url', 'preview');
        $this->assertSame(route('admin.comments.dashboard'), $urls['Unlinked']);
        $this->assertStringEndsWith('/posts/42/preview/facebook#comment-2', $urls['Linked']);
    }

    public function test_a_comment_opens_its_post_preview_on_the_comments_platform(): void
    {
        DB::table('posts')->insert([['id' => 236, 'platform' => 'instagram'], ['id' => 237, 'platform' => 'tiktok']]);
        DB::table('post_comments')->insert([
            ['id' => 259, 'user_id' => $this->user->id, 'platform' => 'instagram', 'user_name' => 'Sara', 'content' => 'Love it', 'post_id' => 236, 'parent_comment_id' => null, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 260, 'user_id' => $this->user->id, 'platform' => 'instagram', 'user_name' => 'Omar', 'content' => 'A reply', 'post_id' => 236, 'parent_comment_id' => 259, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 261, 'user_id' => $this->user->id, 'platform' => null, 'user_name' => 'Lina', 'content' => 'No platform', 'post_id' => 237, 'parent_comment_id' => null, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $urls = collect(app(NotificationController::class)->index()->getData(true)['items'])->pluck('url', 'preview');

        $this->assertSame(route('admin.posts.preview', ['post' => 236, 'platform' => 'instagram']) . '#comment-259', $urls['Love it']);
        $this->assertStringEndsWith('/posts/236/preview/instagram#comment-259', $urls['A reply']); // a reply opens its thread
        $this->assertStringEndsWith('/posts/237/preview/tiktok#comment-261', $urls['No platform']); // falls back to the post's platform
    }

    public function test_unread_messages_are_listed_with_comments_and_counted(): void
    {
        $account = fn (string $platform, ?array $caps) => DB::table('social_accounts')->insertGetId([
            'user_id' => $this->user->id, 'platform' => $platform, 'platform_account_id' => $platform . '-1', 'name' => ucfirst($platform),
            'enabled_capabilities' => $caps === null ? null : json_encode($caps), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $instagram = $account('instagram', null);          // default: every capability on
        $facebook = $account('facebook', ['publishing']);  // messaging switched off in the Hub
        DB::table('conversations')->insert([
            ['social_account_id' => $instagram, 'platform' => 'instagram', 'customer_name' => 'Sara', 'last_message_preview' => 'Is this in stock?', 'unread_count' => 3, 'last_message_at' => now(), 'created_at' => now(), 'updated_at' => now()],
            ['social_account_id' => $instagram, 'platform' => 'instagram', 'customer_name' => 'Read', 'last_message_preview' => 'Already read', 'unread_count' => 0, 'last_message_at' => now(), 'created_at' => now(), 'updated_at' => now()],
            ['social_account_id' => $facebook, 'platform' => 'facebook', 'customer_name' => 'Hidden', 'last_message_preview' => 'Messaging off', 'unread_count' => 5, 'last_message_at' => now(), 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('post_comments')->insert(['user_id' => $this->user->id, 'platform' => 'instagram', 'user_name' => 'Omar', 'content' => 'Nice post', 'post_id' => null, 'created_at' => now()->subMinute(), 'updated_at' => now()]);

        $data = app(NotificationController::class)->index()->getData(true);
        $items = collect($data['items']);

        $this->assertSame(4, $data['count']); // 3 unread messages + 1 comment
        $this->assertSame(['conversation' => 1, 'comment' => 1], $items->countBy('type')->all());
        $message = $items->firstWhere('type', 'conversation');
        $this->assertSame(['Sara', 'Is this in stock?', 3], [$message['author'], $message['preview'], $message['badge']]);
        $this->assertStringContainsString('conversation=', $message['url']);
    }
}
