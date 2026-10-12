<?php

namespace Tests\Feature\Posts;

use App\Http\Controllers\Admin\PostController;
use App\Models\Post;
use App\Models\PostComment;
use App\Models\User;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\Feature\Connections\CreatesConnectionTables;
use Tests\TestCase;

/** Post preview: a new comment / a reply, published on Facebook as the Page. */
class FacebookPublishCommentTest extends TestCase
{
    use CreatesConnectionTables;

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
            $t->string('user_avatar_url')->nullable();
            $t->text('content')->nullable();
            $t->string('status')->nullable();
            $t->string('error_message')->nullable();
            $t->integer('likes')->nullable();
            $t->timestamp('posted_at')->nullable();
            $t->timestamp('imported_at')->nullable();
            $t->timestamp('read_at')->nullable();
            $t->timestamps();
        });

        \App\Support\Settings::set('posts.facebook.base_url', 'https://graph.facebook.com/v23.0/');
        $user = User::create(['name' => 'Seller', 'email' => 's@example.com', 'password' => bcrypt('x')]);
        $this->actingAs($user);
        // A long-lived Page token: no exchange round-trip before commenting.
        $accountId = DB::table('social_accounts')->insertGetId(['user_id' => $user->id, 'platform' => 'facebook', 'platform_account_id' => 'PAGE1', 'name' => 'Socialeaz', 'access_token' => encrypt('page-token', false), 'is_token_valid' => true, 'expires_at' => now()->addDays(30), 'created_at' => now(), 'updated_at' => now()]);
        $this->post = Post::forceCreate(['user_id' => $user->id, 'platform' => 'facebook', 'social_account_id' => $accountId, 'post_id' => 'PAGE1_555', 'status' => 'completed']);
    }

    private function controller(): PostController
    {
        return app(PostController::class);
    }

    public function test_a_new_comment_is_published_on_the_post_and_shown_at_once(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['id' => 'PAGE1_555_901'])]);

        $data = $this->controller()->storeComment(new Request(['content' => 'Thanks for the support!']), $this->post)->getData(true);

        $this->assertTrue($data['success']);
        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), '/PAGE1_555/comments') && $r['message'] === 'Thanks for the support!');
        $comment = PostComment::sole();
        $this->assertSame(['PAGE1_555_901', $this->post->id, null, false, 'support', 'Socialeaz'], [$comment->comment_id, $comment->post_id, $comment->parent_comment_id, (bool) $comment->is_reply, $comment->sender_type, $comment->user_name]);
        $this->assertSame(['Socialeaz', 'Thanks for the support!', true], [$data['comment']['author'], $data['comment']['content'], $data['comment']['isOwn']]);
    }

    public function test_a_reply_is_published_under_the_comment(): void
    {
        $parent = PostComment::create(['platform' => 'facebook', 'comment_id' => 'PAGE1_555_100', 'post_id' => $this->post->id, 'social_account_id' => $this->post->social_account_id, 'user_name' => 'Customer', 'content' => 'Love it', 'sender_type' => 'customer']);
        Http::fake(['graph.facebook.com/*' => Http::response(['id' => 'PAGE1_555_101'])]);

        $data = $this->controller()->storeReply(new Request(['content' => 'Glad you like it!']), $parent)->getData(true);

        $this->assertTrue($data['success']);
        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), '/PAGE1_555_100/comments') && $r['message'] === 'Glad you like it!');
        $reply = PostComment::where('comment_id', 'PAGE1_555_101')->sole();
        $this->assertSame([$parent->id, $this->post->id, true], [(int) $reply->parent_comment_id, $reply->post_id, (bool) $reply->is_reply]);
        $this->assertSame(['Socialeaz', true], [$data['reply']['author'], $data['reply']['isOwn']]);
    }

    public function test_a_rejected_comment_leaves_the_post_published(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['message' => '(#200) Permissions error']], 400)]);

        $response = $this->controller()->storeComment(new Request(['content' => 'Hello']), $this->post);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertStringContainsString('Permissions error', $response->getData(true)['message']);
        $this->assertSame('completed', $this->post->fresh()->status);
        $this->assertSame(0, PostComment::count());
    }

    public function test_an_unpublished_post_cannot_be_commented_on(): void
    {
        $this->post->forceFill(['post_id' => null, 'status' => 'failed'])->save();
        Http::fake();

        $response = $this->controller()->storeComment(new Request(['content' => 'Hello']), $this->post);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertStringContainsString("isn't on Facebook yet", $response->getData(true)['message']);
        Http::assertNothingSent();
    }
}
