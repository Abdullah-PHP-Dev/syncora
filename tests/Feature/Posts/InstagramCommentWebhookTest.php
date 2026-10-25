<?php

namespace Tests\Feature\Posts;

use App\Support\Settings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Feature\Connections\CreatesConnectionTables;
use Tests\TestCase;

/**
 * POST /api/comments/instagram (and /api/messaging/instagram, the same
 * handler) - "comments" webhooks shaped as in Meta's Instagram docs:
 * object instagram, entry[].id = the professional account ID,
 * changes[].value = {from{id,username}, media{id,media_product_type}, id, text, parent_id?}.
 */
class InstagramCommentWebhookTest extends TestCase
{
    use CreatesConnectionTables;

    private const IG_USER = '17841440159861121';   // professional account ID (entry.id)
    private const SCOPED = '28172509515715641';    // Instagram Login app-scoped id (/me id)
    private const MEDIA = '18502583398100703';

    protected function setUp(): void
    {
        parent::setUp();
        $this->createConnectionTables();
        $this->createSettingsTable();
        $this->createMessageChannelsTable();
        (require database_path('migrations/2026_09_05_223054_create_webhook_logs_table.php'))->up();
        Schema::create('posts', function ($t) {
            $t->id();
            $t->string('platform')->nullable();
            $t->unsignedBigInteger('user_id')->nullable();
            $t->unsignedBigInteger('social_account_id')->nullable();
            $t->string('post_id')->nullable();
            $t->string('status')->nullable();
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
        Settings::set('posts.facebook.client_secret', 'meta-secret');
        Settings::set('posts.instagram.client_secret', 'instagram-secret');
        DB::table('users')->insert(['id' => 7, 'name' => 'Seller', 'email' => 's@example.com', 'password' => 'x', 'created_at' => now(), 'updated_at' => now()]);
    }

    private function account(string $platformAccountId, array $metadata = []): int
    {
        return DB::table('social_accounts')->insertGetId(['user_id' => 7, 'platform' => 'instagram', 'platform_account_id' => $platformAccountId, 'name' => 'social.eaz', 'username' => 'social.eaz', 'access_token' => 't', 'is_token_valid' => true, 'metadata' => json_encode($metadata), 'created_at' => now(), 'updated_at' => now()]);
    }

    private function igPost(int $accountId): int
    {
        return DB::table('posts')->insertGetId(['user_id' => 7, 'platform' => 'instagram', 'social_account_id' => $accountId, 'post_id' => self::MEDIA, 'status' => 'completed']);
    }

    private function deliver(array $value, string $secret = 'meta-secret', string $url = '/api/comments/instagram')
    {
        $body = json_encode(['object' => 'instagram', 'entry' => [[
            'id' => self::IG_USER, 'time' => time(),
            'changes' => [['field' => 'comments', 'value' => $value + ['from' => ['id' => '17841400000000009', 'username' => 'sara'], 'media' => ['id' => self::MEDIA, 'media_product_type' => 'FEED']]]],
        ]]]);

        return $this->call('POST', $url, [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_HUB_SIGNATURE_256' => 'sha256=' . hash_hmac('sha256', $body, $secret)], $body);
    }

    private function comment(string $id): ?object
    {
        return DB::table('post_comments')->where('comment_id', $id)->first();
    }

    public function test_a_page_linked_account_comment_is_stored_on_its_post_once(): void
    {
        $postId = $this->igPost($accountId = $this->account(self::IG_USER, ['linked_page_id' => '1265919733267400']));

        $this->deliver(['id' => 'C1', 'text' => 'Love it!'])->assertOk()->assertSeeText('EVENT_RECEIVED');
        $this->deliver(['id' => 'C1', 'text' => 'Love it!'])->assertOk(); // Meta retry

        $c = $this->comment('C1');
        $this->assertSame([$postId, $accountId, 7, 'customer', 'sara', 'Love it!'], [(int) $c->post_id, (int) $c->social_account_id, (int) $c->user_id, $c->sender_type, $c->user_name, $c->content]);
        $this->assertSame(1, (int) DB::table('posts')->value('comments'));
        $this->assertSame([2, 'comments'], [DB::table('webhook_logs')->where('platform', 'instagram')->where('signature_valid', true)->count(), DB::table('webhook_logs')->value('event_type')]);
    }

    public function test_an_instagram_login_account_is_matched_by_its_professional_id(): void
    {
        // Stored under the /me app-scoped id; the webhook sends the professional account ID.
        $postId = $this->igPost($accountId = $this->account(self::SCOPED, ['settings' => ['auth_type' => 'instagram_login', 'ig_user_id' => self::IG_USER]]));

        // Instagram Login deliveries are signed with the Instagram app secret.
        $this->deliver(['id' => 'C2', 'text' => 'Price?'], 'instagram-secret')->assertOk();

        $c = $this->comment('C2');
        $this->assertSame([$postId, $accountId], [(int) $c->post_id, (int) $c->social_account_id]);
    }

    public function test_with_both_connections_the_comment_goes_to_the_account_that_published_the_post(): void
    {
        $this->account(self::IG_USER, ['linked_page_id' => '1265919733267400']);
        $postId = $this->igPost($login = $this->account(self::SCOPED, ['settings' => ['auth_type' => 'instagram_login', 'ig_user_id' => self::IG_USER]]));

        $this->deliver(['id' => 'C3', 'text' => 'Nice'])->assertOk();

        $this->assertSame([$postId, $login], [(int) $this->comment('C3')->post_id, (int) $this->comment('C3')->social_account_id]);
    }

    public function test_a_reply_is_threaded_and_our_own_is_support(): void
    {
        $this->igPost($this->account(self::IG_USER));

        $this->deliver(['id' => 'C1', 'text' => 'Price?']);
        $this->deliver(['id' => 'C2', 'text' => 'SAR 99', 'parent_id' => 'C1', 'from' => ['id' => self::IG_USER, 'username' => 'social.eaz']], 'meta-secret', '/api/messaging/instagram')->assertOk();

        $reply = $this->comment('C2');
        $this->assertSame([(int) $this->comment('C1')->id, 1, 'support'], [(int) $reply->parent_comment_id, (int) $reply->is_reply, $reply->sender_type]);
    }

    public function test_a_forged_delivery_stores_nothing_and_is_logged(): void
    {
        $this->igPost($this->account(self::IG_USER));

        $this->deliver(['id' => 'C1', 'text' => 'x'], 'wrong-secret')->assertForbidden();

        $this->assertSame(0, DB::table('post_comments')->count());
        $this->assertSame(1, DB::table('webhook_logs')->where('signature_valid', false)->count());
    }
}
