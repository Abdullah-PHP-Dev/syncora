<?php

namespace Tests\Feature\Posts;

use App\Models\Messaging\MessageChannel;
use App\Models\SocialAccount;
use App\Services\MessagingServices\FacebookMessengerService;
use App\Services\MessagingServices\InstagramMessengerService;
use App\Services\PostServices\InstagramPostService;
use App\Services\PostServices\MetaPostService;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Connections\CreatesConnectionTables;
use Tests\TestCase;

/**
 * On connect, the posts and messaging sides both POST /subscribed_apps for
 * the same Page (or Instagram account). Each call REPLACES the field list,
 * so both must send the full list or the later one switches the other's
 * events off (comments stopped arriving while DMs kept working).
 */
class MetaWebhookSubscriptionTest extends TestCase
{
    use CreatesConnectionTables;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createConnectionTables();
        $this->createSettingsTable();
        $this->createMessageChannelsTable();
        \App\Support\Settings::set('posts.facebook.base_url', 'https://graph.facebook.com/v25.0/');
        \App\Models\User::create(['id' => 1, 'name' => 'Seller', 'email' => 's@example.com', 'password' => bcrypt('x')]);
        Http::fake(['*' => Http::response(['success' => true])]);
    }

    private function fieldsSentTo(string $id): array
    {
        return Http::recorded()
            ->filter(fn ($pair) => str_contains($pair[0]->url(), $id . '/subscribed_apps'))
            ->map(fn ($pair) => $pair[0]['subscribed_fields'] ?? null)
            ->values()->all();
    }

    public function test_facebook_page_posts_and_messenger_subscribe_the_same_full_list(): void
    {
        $page = SocialAccount::create(['user_id' => 1, 'platform' => 'facebook', 'platform_account_id' => 'PAGE1', 'name' => 'Page', 'account_type' => 'page', 'access_token' => 'page-token']);
        $channel = MessageChannel::create(['platform' => 'facebook', 'external_id' => 'PAGE1', 'social_account_id' => $page->id]);

        app(MetaPostService::class)->subscribeToWebhooks($page);
        app(FacebookMessengerService::class)->subscribeToWebhooks($channel);

        $this->assertSame([MetaPostService::PAGE_WEBHOOK_FIELDS, MetaPostService::PAGE_WEBHOOK_FIELDS], $this->fieldsSentTo('PAGE1'));
        $this->assertStringContainsString('feed', MetaPostService::PAGE_WEBHOOK_FIELDS);
        $this->assertStringContainsString('messages', MetaPostService::PAGE_WEBHOOK_FIELDS);
    }

    public function test_instagram_posts_and_messaging_subscribe_the_same_full_list(): void
    {
        $ig = SocialAccount::create(['user_id' => 1, 'platform' => 'instagram', 'platform_account_id' => 'IG1', 'name' => 'IG', 'account_type' => 'business_account', 'access_token' => 'page-token']);
        $channel = MessageChannel::create(['platform' => 'instagram', 'external_id' => 'IG1', 'social_account_id' => $ig->id]);

        app(InstagramPostService::class)->subscribeToWebhooks($ig);
        app(InstagramMessengerService::class)->subscribeToWebhooks($channel);

        $this->assertSame([InstagramPostService::ACCOUNT_WEBHOOK_FIELDS, InstagramPostService::ACCOUNT_WEBHOOK_FIELDS], $this->fieldsSentTo('IG1'));
    }
}
