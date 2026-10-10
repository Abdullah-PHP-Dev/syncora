<?php

namespace Tests\Feature\Posts;

use App\Support\Settings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Feature\Connections\CreatesConnectionTables;
use Tests\TestCase;

/** POST/GET /api/comments/facebook - Meta's Page feed webhook. */
class FacebookCommentWebhookTest extends TestCase
{
    use CreatesConnectionTables;

    private int $accountId;
    private int $postId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createConnectionTables();
        $this->createSettingsTable();
        $this->createMessageChannelsTable();
        Schema::create('posts', function ($t) {
            $t->id();
            $t->string('platform')->nullable();
            $t->unsignedBigInteger('user_id')->nullable();
            $t->unsignedBigInteger('social_account_id')->nullable();
            $t->string('post_id')->nullable();
            $t->text('content')->nullable();
            $t->string('status')->nullable();
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
            $t->text('content')->nullable();
            $t->string('status')->nullable();
            $t->timestamp('posted_at')->nullable();
            $t->timestamp('imported_at')->nullable();
            $t->timestamp('read_at')->nullable();
            $t->timestamps();
        });
        Settings::set('posts.facebook.client_secret', 'app-secret');
        DB::table('users')->insert(['id' => 7, 'name' => 'Seller', 'email' => 's@example.com', 'password' => 'x', 'created_at' => now(), 'updated_at' => now()]);
        $this->accountId = DB::table('social_accounts')->insertGetId(['user_id' => 7, 'platform' => 'facebook', 'platform_account_id' => 'PAGE1', 'name' => 'Page', 'access_token' => 't', 'is_token_valid' => true, 'created_at' => now(), 'updated_at' => now()]);
        $this->postId = DB::table('posts')->insertGetId(['user_id' => 7, 'platform' => 'facebook', 'social_account_id' => $this->accountId, 'post_id' => 'PAGE1_555', 'status' => 'completed']);
    }

    private function deliver(array $value, ?string $secret = 'app-secret')
    {
        $body = json_encode(['object' => 'page', 'entry' => [['id' => 'PAGE1', 'time' => time(), 'changes' => [['field' => 'feed', 'value' => $value + ['item' => 'comment']]]]]]);
        $headers = ['CONTENT_TYPE' => 'application/json'];
        if ($secret !== null) {
            $headers['HTTP_X_HUB_SIGNATURE_256'] = 'sha256=' . hash_hmac('sha256', $body, $secret);
        }

        return $this->call('POST', '/api/comments/facebook', [], [], [], $headers, $body);
    }

    private function comment(string $id): ?object
    {
        return DB::table('post_comments')->where('comment_id', $id)->first();
    }

    public function test_verification_handshake(): void
    {
        $this->get('/api/comments/facebook?hub.mode=subscribe&hub.verify_token=socialeaz-98897&hub.challenge=abc')->assertOk()->assertSee('abc');
        $this->get('/api/comments/facebook?hub.mode=subscribe&hub.verify_token=wrong&hub.challenge=abc')->assertForbidden();
    }

    public function test_missing_settings_never_mean_accept(): void
    {
        Settings::set('posts.facebook.client_secret', '');

        // An empty secret must not validate a body signed with an empty key.
        $this->deliver(['verb' => 'add', 'comment_id' => 'C9', 'post_id' => 'PAGE1_555', 'message' => 'x'], '')->assertForbidden();
        $this->assertNull($this->comment('C9'));
    }

    public function test_unsigned_or_wrongly_signed_events_are_rejected(): void
    {
        $this->deliver(['verb' => 'add', 'comment_id' => 'C1', 'post_id' => 'PAGE1_555', 'message' => 'x'], null)->assertForbidden();
        $this->deliver(['verb' => 'add', 'comment_id' => 'C1', 'post_id' => 'PAGE1_555', 'message' => 'x'], 'other-secret')->assertForbidden();
        $this->assertNull($this->comment('C1'));
    }

    public function test_comment_reply_edit_and_delete(): void
    {
        $from = ['id' => 'U1', 'name' => 'Sara'];
        $this->deliver(['verb' => 'add', 'comment_id' => 'C1', 'post_id' => 'PAGE1_555', 'parent_id' => 'PAGE1_555', 'message' => 'How much?', 'from' => $from])->assertOk();
        $this->deliver(['verb' => 'add', 'comment_id' => 'C1', 'post_id' => 'PAGE1_555', 'parent_id' => 'PAGE1_555', 'message' => 'How much?', 'from' => $from])->assertOk(); // Meta retry
        $this->deliver(['verb' => 'add', 'comment_id' => 'C2', 'post_id' => 'PAGE1_555', 'parent_id' => 'C1', 'message' => 'SAR 99', 'from' => ['id' => 'PAGE1', 'name' => 'Page']]);

        $c1 = $this->comment('C1');
        $this->assertSame([$this->postId, 7, 'customer', 'Sara'], [(int) $c1->post_id, (int) $c1->user_id, $c1->sender_type, $c1->user_name]);
        $this->assertSame(1, DB::table('post_comments')->where('comment_id', 'C1')->count());
        $c2 = $this->comment('C2');
        $this->assertSame([(int) $c1->id, 1, 'support'], [(int) $c2->parent_comment_id, (int) $c2->is_reply, $c2->sender_type]);

        $this->deliver(['verb' => 'edited', 'comment_id' => 'C1', 'post_id' => 'PAGE1_555', 'message' => 'How much in SAR?', 'from' => $from]);
        $this->assertSame('How much in SAR?', $this->comment('C1')->content);

        $this->deliver(['verb' => 'remove', 'comment_id' => 'C1', 'post_id' => 'PAGE1_555', 'from' => $from]);
        $this->assertNull($this->comment('C1'));
        $this->assertNull($this->comment('C2'));
    }

    public function test_video_posts_and_posts_made_on_facebook(): void
    {
        $video = DB::table('posts')->insertGetId(['user_id' => 7, 'platform' => 'facebook', 'social_account_id' => $this->accountId, 'post_id' => '888', 'status' => 'completed']);

        $this->deliver(['verb' => 'add', 'comment_id' => 'V1', 'post_id' => 'PAGE1_888', 'message' => 'Nice video', 'from' => ['id' => 'U1', 'name' => 'Sara']]);
        $this->deliver(['verb' => 'add', 'comment_id' => 'E1', 'post_id' => 'PAGE1_777', 'message' => 'Hi', 'from' => ['id' => 'U1', 'name' => 'Sara']]);

        $this->assertSame($video, (int) $this->comment('V1')->post_id);
        $this->assertNull($this->comment('E1')->post_id);
        $this->assertSame(7, (int) $this->comment('E1')->user_id);
    }
}
