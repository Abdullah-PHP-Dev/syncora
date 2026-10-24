<?php

namespace Tests\Feature\Posts;

use App\Http\Controllers\Admin\PostController;
use App\Models\Post;
use App\Models\PostComment;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\PostServices\ThreadsPostService;
use App\Support\Settings;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\Feature\Connections\CreatesConnectionTables;
use Tests\TestCase;

/**
 * Threads replies on the post page (posts/{id}/preview/threads): reading
 * them (GET {media-id}/conversation) and replying (me/threads with
 * reply_to_id, then threads_publish) - shapes as in Meta's Threads docs.
 */
class ThreadsRepliesTest extends TestCase
{
    use CreatesConnectionTables;

    private const API = 'https://graph.threads.net/v1.0/';
    private const MEDIA = '18000000000000101';

    private User $user;
    private SocialAccount $account;
    private Post $post;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createConnectionTables();
        $this->createSettingsTable();
        Schema::create('posts', function ($t) {
            $t->id();
            $t->string('platform')->nullable();
            $t->unsignedBigInteger('user_id')->nullable();
            $t->unsignedBigInteger('social_account_id')->nullable();
            $t->string('post_id')->nullable();
            $t->text('content')->nullable();
            $t->string('status')->nullable();
            $t->text('error_message')->nullable();
            $t->integer('comments')->default(0);
            $t->json('platform_metadata')->nullable();
            $t->string('visibility')->nullable();
            $t->timestamps();
        });
        Schema::create('post_comments', function ($t) {
            $t->id();
            $t->string('platform')->nullable();
            $t->string('comment_id')->nullable();
            $t->unsignedBigInteger('parent_comment_id')->nullable();
            $t->unsignedBigInteger('user_id')->nullable();
            $t->unsignedBigInteger('social_account_id')->nullable();
            $t->unsignedBigInteger('post_id')->nullable();
            $t->string('sender_type')->nullable();
            $t->boolean('is_reply')->default(false);
            $t->string('user_name')->nullable();
            $t->text('user_avatar_url')->nullable();
            $t->text('content')->nullable();
            $t->string('status')->nullable();
            $t->integer('likes')->default(0);
            $t->timestamp('posted_at')->nullable();
            $t->timestamp('imported_at')->nullable();
            $t->timestamp('read_at')->nullable();
            $t->timestamps();
        });
        Settings::set('posts.threads.base_url', self::API);
        $this->user = User::create(['name' => 'Seller', 'email' => 's@example.com', 'password' => bcrypt('x')]);
        $this->actingAs($this->user);
        $this->account = SocialAccount::create(['user_id' => $this->user->id, 'platform' => 'threads', 'platform_account_id' => '25000000000000001', 'name' => 'socialeaz', 'username' => 'socialeaz', 'avatar_url' => 'https://cdn/me.jpg', 'access_token' => 'th-token', 'expires_at' => now()->addDays(30), 'is_token_valid' => true, 'has_posting_permission' => true]);
        $this->post = Post::forceCreate(['platform' => 'threads', 'user_id' => $this->user->id, 'social_account_id' => $this->account->id, 'post_id' => self::MEDIA, 'status' => 'completed', 'content' => 'Launch day']);
    }

    private function item(string $id, string $repliedTo, array $extra = []): array
    {
        return $extra + ['id' => $id, 'text' => "Reply {$id}", 'username' => 'sara', 'timestamp' => '2026-10-24T18:07:57+0000', 'replied_to' => ['id' => $repliedTo], 'root_post' => ['id' => self::MEDIA], 'hide_status' => 'NOT_HUSHED'];
    }

    public function test_import_reads_the_whole_conversation_oldest_first(): void
    {
        Http::fake([
            self::API . self::MEDIA . '/conversation*' => Http::sequence()
                ->push(['data' => [$this->item('R1', self::MEDIA), $this->item('R2', 'R1', ['is_reply_owned_by_me' => true, 'username' => 'socialeaz'])], 'paging' => ['cursors' => ['before' => 'a', 'after' => 'CUR2'], 'next' => self::API . 'next']])
                ->push(['data' => [$this->item('R3', self::MEDIA, ['hide_status' => 'HIDDEN']), $this->item('R4', self::MEDIA, ['profile_picture_url' => 'https://cdn/sara.jpg'])], 'paging' => ['cursors' => ['before' => 'b', 'after' => 'CUR3']]]),
        ]);

        $this->assertSame(3, app(ThreadsPostService::class)->importReplies($this->post));

        $r1 = PostComment::where('comment_id', 'R1')->sole();
        $r2 = PostComment::where('comment_id', 'R2')->sole();
        $this->assertSame([$this->post->id, 'customer', 'sara', 0], [(int) $r1->post_id, $r1->sender_type, $r1->user_name, (int) $r1->is_reply]);
        $this->assertSame([$r1->id, 1, 'support'], [(int) $r2->parent_comment_id, (int) $r2->is_reply, $r2->sender_type]);
        $this->assertNull(PostComment::where('comment_id', 'R3')->first(), 'hidden on Threads');
        $this->assertSame('https://cdn/sara.jpg', PostComment::where('comment_id', 'R4')->value('user_avatar_url'));
        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), '/conversation') && $r['reverse'] === 'false' && $r['access_token'] === 'th-token' && str_contains($r['fields'], 'replied_to'));
        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), '/conversation') && ($r['after'] ?? null) === 'CUR2');
    }

    public function test_importing_twice_does_not_duplicate_or_double_count(): void
    {
        Http::fake([self::API . self::MEDIA . '/conversation*' => Http::response(['data' => [$this->item('R1', self::MEDIA)]])]);

        app(ThreadsPostService::class)->importReplies($this->post);
        app(ThreadsPostService::class)->importReplies($this->post);

        $this->assertSame(1, PostComment::count());
        $this->assertSame(1, (int) $this->post->fresh()->comments);
    }

    public function test_a_refused_import_changes_nothing(): void
    {
        Http::fake([self::API . '*' => Http::response(['error' => ['message' => 'Application does not have permission for this action', 'code' => 10]], 403)]);

        $this->assertNull(app(ThreadsPostService::class)->importReplies($this->post));
        $this->assertSame(['completed', 0], [$this->post->fresh()->status, PostComment::count()]);
    }

    public function test_reply_from_the_post_page_is_published_and_saved(): void
    {
        $parent = PostComment::create(['platform' => 'threads', 'comment_id' => 'R1', 'post_id' => $this->post->id, 'social_account_id' => $this->account->id, 'content' => 'Price?', 'sender_type' => 'customer']);
        Http::fake([
            self::API . 'me/threads' => Http::response(['id' => 'CONTAINER1']),
            self::API . 'CONTAINER1*' => Http::response(['id' => 'CONTAINER1', 'status' => 'FINISHED']),
            self::API . '25000000000000001/threads_publish' => Http::response(['id' => '18000000000000999']),
        ]);

        $data = app(PostController::class)->storeReply(new Request(['content' => 'SAR 99']), $parent)->getData(true);

        $this->assertTrue($data['success'], $data['message'] ?? '');
        Http::assertSent(fn (HttpRequest $r) => $r->url() === self::API . 'me/threads' && $r['media_type'] === 'TEXT' && $r['text'] === 'SAR 99' && $r['reply_to_id'] === 'R1' && $r['access_token'] === 'th-token');
        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/threads_publish') && $r['creation_id'] === 'CONTAINER1');
        $reply = PostComment::where('comment_id', '18000000000000999')->sole();
        $this->assertSame([$parent->id, 'support', 'socialeaz', 1], [(int) $reply->parent_comment_id, $reply->sender_type, $reply->user_name, (int) $reply->is_reply]);
    }

    public function test_new_comment_from_the_post_page_replies_to_the_post(): void
    {
        Http::fake([
            self::API . 'me/threads' => Http::response(['id' => 'CONTAINER2']),
            self::API . 'CONTAINER2*' => Http::response(['status' => 'FINISHED']),
            self::API . '25000000000000001/threads_publish' => Http::response(['id' => '18000000000000777']),
        ]);

        $data = app(PostController::class)->storeComment(new Request(['content' => 'Thanks all!']), $this->post)->getData(true);

        $this->assertTrue($data['success'], $data['message'] ?? '');
        Http::assertSent(fn (HttpRequest $r) => $r->url() === self::API . 'me/threads' && $r['reply_to_id'] === self::MEDIA);
        $comment = PostComment::where('comment_id', '18000000000000777')->sole();
        $this->assertSame([null, 'support', $this->post->id], [$comment->parent_comment_id, $comment->sender_type, (int) $comment->post_id]);
    }

    public function test_a_refused_reply_explains_and_never_fails_the_post(): void
    {
        $parent = PostComment::create(['platform' => 'threads', 'comment_id' => 'R1', 'post_id' => $this->post->id, 'content' => 'Hi', 'sender_type' => 'customer']);
        Http::fake([self::API . 'me/threads' => Http::response(['error' => ['message' => '(#10) The user has not granted permission threads_manage_replies.', 'code' => 10]], 403)]);

        $response = app(PostController::class)->storeReply(new Request(['content' => 'Hello']), $parent);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertStringContainsString('Reconnect Threads in Connections', $response->getData(true)['message']);
        $this->assertSame(['completed', 1], [$this->post->fresh()->status, PostComment::count()]);
    }

    public function test_an_unpublished_post_cannot_be_replied_to(): void
    {
        $this->post->update(['post_id' => null, 'status' => 'scheduled']);
        Http::fake();

        $response = app(PostController::class)->storeComment(new Request(['content' => 'Hi']), $this->post);

        $this->assertSame(422, $response->getStatusCode());
        Http::assertNothingSent();
    }
}
