<?php

namespace Tests\Feature\Posts;

use App\Support\Settings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Feature\Connections\CreatesConnectionTables;
use Tests\TestCase;

/** GET/POST /api/comments/threads - Threads "replies" webhooks, payloads shaped as in Meta's docs. */
class ThreadsCommentWebhookTest extends TestCase
{
    use CreatesConnectionTables;

    private const TARGET = '25000000000000001';
    private const POST = '18000000000000101';

    private int $accountId;
    private int $postId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createConnectionTables();
        $this->createSettingsTable();
        (require database_path('migrations/2026_09_05_223054_create_webhook_logs_table.php'))->up();
        Schema::create('posts', function ($t) {
            $t->id();
            $t->string('platform')->nullable();
            $t->unsignedBigInteger('user_id')->nullable();
            $t->unsignedBigInteger('social_account_id')->nullable();
            $t->string('post_id')->nullable();
            $t->text('content')->nullable();
            $t->string('status')->nullable();
            $t->integer('comments')->default(0);
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
        Settings::set('threads.facebook.client_id', '1312337257646594');
        Settings::set('threads.facebook.client_key', 'threads-secret');
        DB::table('users')->insert(['id' => 7, 'name' => 'Seller', 'email' => 's@example.com', 'password' => 'x', 'created_at' => now(), 'updated_at' => now()]);
        $this->accountId = DB::table('social_accounts')->insertGetId(['user_id' => 7, 'platform' => 'threads', 'platform_account_id' => self::TARGET, 'name' => 'socialeaz', 'username' => 'socialeaz', 'access_token' => 't', 'is_token_valid' => true, 'created_at' => now(), 'updated_at' => now()]);
        $this->postId = DB::table('posts')->insertGetId(['user_id' => 7, 'platform' => 'threads', 'social_account_id' => $this->accountId, 'post_id' => self::POST, 'status' => 'completed']);
    }

    /** A "replies" delivery as documented: topic moderate, values {value, field}. */
    private function deliver(array $value, ?string $secret = 'threads-secret', string $field = 'replies')
    {
        $body = json_encode([
            'app_id' => '1312337257646594', 'topic' => 'moderate', 'target_id' => self::TARGET, 'time' => time(),
            'subscription_id' => '1234567890123456', 'has_uid_field' => false,
            'values' => ['value' => $value + [
                'media_type' => 'TEXT_POST', 'permalink' => 'https://www.threads.net/@sara/post/Pp', 'shortcode' => 'Pp',
                'timestamp' => '2026-10-24T18:07:57+0000',
                'root_post' => ['id' => self::POST, 'owner_id' => self::TARGET, 'username' => 'socialeaz'],
            ], 'field' => $field],
        ]);
        $headers = ['CONTENT_TYPE' => 'application/json'];
        if ($secret !== null) {
            $headers['HTTP_X_HUB_SIGNATURE_256'] = 'sha256=' . hash_hmac('sha256', $body, $secret);
        }

        return $this->call('POST', '/api/comments/threads', [], [], [], $headers, $body);
    }

    private function reply(string $id): ?object
    {
        return DB::table('post_comments')->where('comment_id', $id)->first();
    }

    public function test_subscription_handshake(): void
    {
        $this->get('/api/comments/threads?hub.mode=subscribe&hub.verify_token=socialeaz-98897&hub.challenge=1158201444')->assertOk()->assertSeeText('1158201444');
        $this->get('/api/comments/threads?hub.mode=subscribe&hub.verify_token=wrong&hub.challenge=1158201444')->assertForbidden();
        // Opened in a browser: says it's reachable, never echoes anything.
        $this->get('/api/comments/threads')->assertOk()->assertSeeText('Threads webhook endpoint reachable');
        $this->get('/api/comments/threads?hub.challenge=x')->assertOk()->assertDontSeeText('x', false);

        Settings::set('threads.facebook.webhook_verify_token', 'threads-verify');
        $this->get('/api/comments/threads?hub.mode=subscribe&hub.verify_token=threads-verify&hub.challenge=42')->assertOk()->assertSeeText('42');
    }

    public function test_a_reply_is_stored_against_its_post_once(): void
    {
        $reply = ['id' => 'R1', 'username' => 'sara', 'text' => 'Love this!', 'replied_to' => ['id' => self::POST], 'profile_picture_url' => 'https://scontent.cdninstagram.com/sara.jpg'];

        $this->deliver($reply)->assertOk()->assertSeeText('EVENT_RECEIVED');
        $this->deliver($reply)->assertOk(); // Meta retry

        $r1 = $this->reply('R1');
        $this->assertSame(
            ['threads', $this->postId, $this->accountId, 7, 'customer', 'sara', 'Love this!', 0, 'approved', 'https://scontent.cdninstagram.com/sara.jpg'],
            [$r1->platform, (int) $r1->post_id, (int) $r1->social_account_id, (int) $r1->user_id, $r1->sender_type, $r1->user_name, $r1->content, (int) $r1->is_reply, $r1->status, $r1->user_avatar_url]
        );
        $this->assertSame('2026-10-24 18:07:57', \Carbon\Carbon::parse($r1->posted_at)->utc()->toDateTimeString());
        $this->assertSame(1, DB::table('post_comments')->count());
        $this->assertSame(1, (int) DB::table('posts')->where('id', $this->postId)->value('comments'));
        $this->assertSame([2, 2], [DB::table('webhook_logs')->where('platform', 'threads')->where('processed', true)->count(), DB::table('webhook_logs')->where('event_type', 'moderate.replies')->count()]);
    }

    public function test_a_reply_to_a_reply_is_threaded_and_our_own_is_support(): void
    {
        $this->deliver(['id' => 'R1', 'username' => 'sara', 'text' => 'Price?', 'replied_to' => ['id' => self::POST]]);
        $this->deliver(['id' => 'R2', 'username' => 'SocialEaz', 'text' => 'SAR 99', 'replied_to' => ['id' => 'R1']]);

        $r2 = $this->reply('R2');
        $this->assertSame([(int) $this->reply('R1')->id, 1, 'support', $this->postId], [(int) $r2->parent_comment_id, (int) $r2->is_reply, $r2->sender_type, (int) $r2->post_id]);
    }

    public function test_a_reply_on_a_post_made_outside_the_app_is_kept_for_the_account(): void
    {
        $body = ['id' => 'R9', 'username' => 'sara', 'text' => 'Hi', 'replied_to' => ['id' => '18999'], 'root_post' => ['id' => '18999', 'owner_id' => self::TARGET]];
        $this->deliver($body)->assertOk();

        $r9 = $this->reply('R9');
        $this->assertSame([null, $this->accountId, 7], [$r9->post_id, (int) $r9->social_account_id, (int) $r9->user_id]);
    }

    public function test_unsigned_forged_or_unknown_deliveries_store_nothing(): void
    {
        $reply = ['id' => 'R1', 'username' => 'sara', 'text' => 'x', 'replied_to' => ['id' => self::POST]];

        $this->deliver($reply, null)->assertForbidden();
        $this->deliver($reply, 'other-secret')->assertForbidden();
        $this->assertSame(2, DB::table('webhook_logs')->where('signature_valid', false)->count());

        DB::table('social_accounts')->update(['platform_account_id' => 'someone-else']);
        $this->deliver($reply)->assertOk(); // acknowledged so Meta stops retrying
        $this->deliver(['id' => 'P1', 'text' => 'my post'], 'threads-secret', 'publish')->assertOk();

        $this->assertSame(0, DB::table('post_comments')->count());
        $this->assertSame('No matching connected account, post or reply - nothing stored.', DB::table('webhook_logs')->latest('id')->value('note'));
    }
}
