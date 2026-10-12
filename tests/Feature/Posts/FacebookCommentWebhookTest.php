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
            $t->integer('likes')->default(0);
            $t->integer('shares')->default(0);
            $t->integer('comments')->default(0);
            $t->json('platform_metadata')->nullable();
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

    public function test_new_comments_keep_the_post_count_current(): void
    {
        $from = ['id' => 'U1', 'name' => 'Sara'];
        $this->deliver(['verb' => 'add', 'comment_id' => 'C1', 'post_id' => 'PAGE1_555', 'message' => 'Hi', 'from' => $from, 'created_time' => 1760000000]);
        $this->deliver(['verb' => 'add', 'comment_id' => 'C1', 'post_id' => 'PAGE1_555', 'message' => 'Hi', 'from' => $from]); // Meta retry
        $this->deliver(['verb' => 'add', 'comment_id' => 'C2', 'post_id' => 'PAGE1_555', 'parent_id' => 'C1', 'message' => 'Thanks', 'from' => $from]);
        $this->assertSame(2, (int) DB::table('posts')->where('id', $this->postId)->value('comments'));
        $this->assertSame(1760000000, \Carbon\Carbon::parse($this->comment('C1')->posted_at)->timestamp);

        $this->deliver(['verb' => 'remove', 'comment_id' => 'C1', 'post_id' => 'PAGE1_555', 'from' => $from]);
        $this->assertSame(0, (int) DB::table('posts')->where('id', $this->postId)->value('comments'));
    }

    public function test_the_pages_own_comment_echo_updates_the_saved_row(): void
    {
        // Saved by publishComment() when the seller commented from the preview.
        DB::table('post_comments')->insert(['platform' => 'facebook', 'comment_id' => 'C5', 'post_id' => $this->postId, 'sender_type' => 'support', 'user_name' => 'Page', 'content' => 'Thanks --. all', 'created_at' => now(), 'updated_at' => now()]);

        $this->deliver(['verb' => 'add', 'comment_id' => 'C5', 'post_id' => 'PAGE1_555', 'message' => 'Thanks --. all', 'from' => ['id' => 'PAGE1', 'name' => 'Page']]);
        // A customer's comment that happens to contain "--." is still imported.
        $this->deliver(['verb' => 'add', 'comment_id' => 'C6', 'post_id' => 'PAGE1_555', 'message' => 'Price --. ?', 'from' => ['id' => 'U1', 'name' => 'Sara']]);

        $this->assertSame(1, DB::table('post_comments')->where('comment_id', 'C5')->count());
        $this->assertSame('support', $this->comment('C5')->sender_type);
        $this->assertSame('customer', $this->comment('C6')->sender_type);
    }

    public function test_reactions_and_shares_update_the_post_and_comment_counts(): void
    {
        DB::table('post_comments')->insert(['platform' => 'facebook', 'comment_id' => 'C1', 'post_id' => $this->postId, 'content' => 'Hi', 'likes' => 0, 'created_at' => now(), 'updated_at' => now()]);
        $react = fn (string $verb, array $extra = []) => $this->deliver(['item' => 'reaction', 'verb' => $verb, 'reaction_type' => 'like', 'post_id' => 'PAGE1_555', 'from' => ['id' => 'U1']] + $extra)->assertOk();

        $react('add');
        $react('add');
        $react('edited'); // like -> love: same count
        $react('remove');
        $this->deliver(['item' => 'share', 'verb' => 'add', 'post_id' => 'PAGE1_555', 'share_id' => 'S1'])->assertOk();
        $react('add', ['comment_id' => 'C1']);

        $post = DB::table('posts')->find($this->postId);
        $this->assertSame([1, 1], [(int) $post->likes, (int) $post->shares]);
        $this->assertSame(1, (int) $this->comment('C1')->likes);
        $this->assertSame(1, DB::table('post_comments')->count()); // reactions never become comments
    }

    public function test_messenger_messages_and_button_taps_reach_the_inbox(): void
    {
        \Illuminate\Support\Facades\Bus::fake();
        \Illuminate\Support\Facades\Http::fake(['*' => \Illuminate\Support\Facades\Http::response(['first_name' => 'Sara', 'last_name' => 'Ali'])]);
        DB::table('message_channels')->insert(['social_account_id' => $this->accountId, 'platform' => 'facebook', 'external_id' => 'PAGE1', 'created_at' => now(), 'updated_at' => now()]);

        $body = json_encode(['object' => 'page', 'entry' => [['id' => 'PAGE1', 'time' => time(), 'messaging' => [
            ['sender' => ['id' => 'PSID1'], 'recipient' => ['id' => 'PAGE1'], 'message' => ['mid' => 'm1', 'text' => 'Hello']],
            ['sender' => ['id' => 'PSID1'], 'recipient' => ['id' => 'PAGE1'], 'postback' => ['mid' => 'm2', 'title' => 'Track my order', 'payload' => 'TRACK']],
            ['sender' => ['id' => 'PSID1'], 'recipient' => ['id' => 'PAGE1'], 'read' => ['watermark' => 1]],
            ['sender' => ['id' => 'PAGE1'], 'recipient' => ['id' => 'PSID1'], 'message' => ['mid' => 'm3', 'text' => 'Hi!', 'is_echo' => true]],
        ]]]]);
        $this->call('POST', '/api/comments/facebook', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_HUB_SIGNATURE_256' => 'sha256=' . hash_hmac('sha256', $body, 'app-secret')], $body)->assertOk();

        $sent = collect();
        \Illuminate\Support\Facades\Bus::assertDispatched(\App\Jobs\Messaging\ProcessInboundMessage::class, function ($job) use ($sent) {
            $sent->push($job);

            return true;
        });
        $this->assertCount(2, $sent); // the read receipt and our own echo are skipped
        $this->assertSame(['Hello', 'Track my order'], $sent->map(fn ($job) => (new \ReflectionProperty($job, 'body'))->getValue($job))->all());
    }

    public function test_commenter_avatars_are_looked_up_once(): void
    {
        Settings::set('posts.facebook.base_url', 'https://graph.facebook.com/v25.0/');
        DB::table('social_accounts')->where('id', $this->accountId)->update(['avatar_url' => 'https://cdn.example/page.jpg']);
        \Illuminate\Support\Facades\Http::fake(['graph.facebook.com/v25.0/C1*' => \Illuminate\Support\Facades\Http::response(['from' => ['id' => 'U1', 'name' => 'Sara Ali', 'picture' => ['data' => ['url' => 'https://cdn.example/sara.jpg']]]])]);

        $this->deliver(['verb' => 'add', 'comment_id' => 'C1', 'post_id' => 'PAGE1_555', 'message' => 'Hi', 'from' => ['id' => 'U1']]); // no name sent
        $this->deliver(['verb' => 'add', 'comment_id' => 'C1', 'post_id' => 'PAGE1_555', 'message' => 'Hi', 'from' => ['id' => 'U1']]); // Meta retry
        $this->deliver(['verb' => 'edited', 'comment_id' => 'C1', 'post_id' => 'PAGE1_555', 'message' => 'Hi!', 'from' => ['id' => 'U1']]);
        $this->deliver(['verb' => 'add', 'comment_id' => 'C2', 'post_id' => 'PAGE1_555', 'parent_id' => 'C1', 'message' => 'Hello', 'from' => ['id' => 'PAGE1', 'name' => 'Page']]);

        $this->assertSame(['https://cdn.example/sara.jpg', 'Sara Ali'], [$this->comment('C1')->user_avatar_url, $this->comment('C1')->user_name]);
        $this->assertSame('https://cdn.example/page.jpg', $this->comment('C2')->user_avatar_url); // the Page's own reply: its own picture, no lookup
        \Illuminate\Support\Facades\Http::assertSentCount(1);
        \Illuminate\Support\Facades\Http::assertSent(fn ($r) => str_contains(urldecode($r->url()), 'fields=from{id,name,picture.type(large)}'));
    }

    public function test_a_private_profile_falls_back_to_initials(): void
    {
        Settings::set('posts.facebook.base_url', 'https://graph.facebook.com/v25.0/');
        \Illuminate\Support\Facades\Http::fake(['*' => \Illuminate\Support\Facades\Http::response(['error' => ['message' => 'Unsupported get request']], 400)]);

        $this->deliver(['verb' => 'add', 'comment_id' => 'C1', 'post_id' => 'PAGE1_555', 'message' => 'Hi', 'from' => ['id' => 'U1', 'name' => 'Sara']])->assertOk();

        $this->assertSame(['Sara', null], [$this->comment('C1')->user_name, $this->comment('C1')->user_avatar_url]);
    }

    public function test_comments_on_video_posts_find_their_post(): void
    {
        // Published through /{page}/videos: saved with the VIDEO id, while
        // comment webhooks carry the feed post id.
        Settings::set('posts.facebook.base_url', 'https://graph.facebook.com/v25.0/');
        $video = DB::table('posts')->insertGetId(['user_id' => 7, 'platform' => 'facebook', 'social_account_id' => $this->accountId, 'post_id' => '4455667788', 'status' => 'completed']);
        \Illuminate\Support\Facades\Http::fake([
            'graph.facebook.com/v25.0/PAGE1_9001*' => \Illuminate\Support\Facades\Http::response(['attachments' => ['data' => [['media_type' => 'video', 'target' => ['id' => '4455667788']]]], 'id' => 'PAGE1_9001']),
            '*' => \Illuminate\Support\Facades\Http::response([]),
        ]);

        $this->deliver(['verb' => 'add', 'comment_id' => '9001_1', 'post_id' => 'PAGE1_9001', 'message' => 'Great video', 'from' => ['id' => 'U1', 'name' => 'Sara']]);
        $this->deliver(['verb' => 'add', 'comment_id' => '9001_2', 'post_id' => 'PAGE1_9001', 'message' => 'Agreed', 'from' => ['id' => 'U2', 'name' => 'Ali']]);

        $this->assertSame([$video, $video], [(int) $this->comment('9001_1')->post_id, (int) $this->comment('9001_2')->post_id]);
        $this->assertSame('PAGE1_9001', json_decode(DB::table('posts')->where('id', $video)->value('platform_metadata'), true)['feed_post_id']);
        // Asked Facebook once; the second comment matched the remembered id.
        \Illuminate\Support\Facades\Http::assertSentCount(1 + 2); // + one avatar lookup per comment
        \Illuminate\Support\Facades\Http::assertSent(fn ($r) => str_contains(urldecode($r->url()), 'fields=attachments{target{id},media_type}'));
    }

    public function test_a_page_connected_twice_still_finds_the_post(): void
    {
        // The same Page connected by a second user (another team member) -
        // the webhook resolves the first row, but the post belongs to the second.
        DB::table('users')->insert(['id' => 8, 'name' => 'Teammate', 'email' => 't@example.com', 'password' => 'x', 'created_at' => now(), 'updated_at' => now()]);
        $second = DB::table('social_accounts')->insertGetId(['user_id' => 8, 'platform' => 'facebook', 'platform_account_id' => 'PAGE1', 'name' => 'Page', 'access_token' => 't', 'is_token_valid' => true, 'created_at' => now(), 'updated_at' => now()]);
        $post = DB::table('posts')->insertGetId(['user_id' => 8, 'platform' => 'facebook', 'social_account_id' => $second, 'post_id' => 'PAGE1_321', 'status' => 'completed']);

        $this->deliver(['verb' => 'add', 'comment_id' => '321_1', 'post_id' => 'PAGE1_321', 'message' => 'Hi', 'from' => ['id' => 'U1', 'name' => 'Sara']]);

        $this->assertSame($post, (int) $this->comment('321_1')->post_id);
    }

    public function test_relink_command_links_existing_comments(): void
    {
        Settings::set('posts.facebook.base_url', 'https://graph.facebook.com/v25.0/');
        $video = DB::table('posts')->insertGetId(['user_id' => 7, 'platform' => 'facebook', 'social_account_id' => $this->accountId, 'post_id' => '4455667788', 'status' => 'completed']);
        $parent = DB::table('post_comments')->insertGetId(['platform' => 'facebook', 'comment_id' => '9001_1', 'social_account_id' => $this->accountId, 'content' => 'Hi', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('post_comments')->insert(['platform' => 'facebook', 'comment_id' => '9001_5', 'parent_comment_id' => $parent, 'is_reply' => true, 'social_account_id' => $this->accountId, 'content' => 'Reply', 'created_at' => now(), 'updated_at' => now()]);
        \Illuminate\Support\Facades\Http::fake(['graph.facebook.com/v25.0/PAGE1_9001*' => \Illuminate\Support\Facades\Http::response(['attachments' => ['data' => [['target' => ['id' => '4455667788']]]]])]);

        $this->artisan('comments:facebook-relink')->assertSuccessful();

        $this->assertSame(0, DB::table('post_comments')->whereNull('post_id')->count());
        $this->assertSame([$video], DB::table('post_comments')->distinct()->pluck('post_id')->map(fn ($id) => (int) $id)->all());
    }
}
