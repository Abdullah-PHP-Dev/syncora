<?php

namespace Tests\Feature\Tiktok;

use App\Services\MessagingServices\TiktokMessagingService;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * TikTok Business Messaging connect: messaging permission only when a DM
 * scope was actually granted, and the account's real profile is fetched.
 */
class TiktokMessagingConnectTest extends TestCase
{
    public function test_scope_without_messaging_is_not_messaging_capable(): void
    {
        // What TikTok granted this app before Business Messaging approval.
        $granted = 'biz.brand.insights,biz.creator.info,comment.list,tto.campaign.link,video.insights,user.info.basic,biz.creator.insights,video.list,biz.ads.recommend';

        $this->assertFalse(TiktokMessagingService::grantsMessaging($granted));
        $this->assertFalse(TiktokMessagingService::grantsMessaging(null));
        $this->assertFalse(TiktokMessagingService::grantsMessaging(''));
    }

    public function test_messaging_scopes_are_recognised(): void
    {
        $this->assertTrue(TiktokMessagingService::grantsMessaging('user.info.basic,biz.dm.read,biz.dm.send'));
        $this->assertTrue(TiktokMessagingService::grantsMessaging('user.info.basic message.list.send'));
        $this->assertTrue(TiktokMessagingService::grantsMessaging('im.chat'));
    }

    public function test_profile_is_fetched_from_business_get(): void
    {
        Http::fake(['*business/get/*' => Http::response(['code' => 0, 'message' => 'OK', 'data' => [
            'display_name' => 'Socialeaz', 'username' => 'socialeaz', 'profile_image' => 'https://p16.tiktokcdn.com/avatar.jpeg', 'follower_count' => 10,
        ]])]);

        $profile = app(TiktokMessagingService::class)->fetchBusinessProfile('tok-123', 'biz-1');

        $this->assertSame(['display_name' => 'Socialeaz', 'username' => 'socialeaz', 'profile_image' => 'https://p16.tiktokcdn.com/avatar.jpeg'], $profile);
        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), 'business/get/')
            && $r->method() === 'GET'
            && $r->hasHeader('Access-Token', 'tok-123')
            && $r['business_id'] === 'biz-1'
            && json_decode($r['fields'], true) === ['display_name', 'username', 'profile_image']);
    }

    public function test_profile_failure_returns_empty_without_throwing(): void
    {
        Http::fake(['*business/get/*' => Http::response(['code' => 40001, 'message' => 'No permission'])]);

        $this->assertSame([], app(TiktokMessagingService::class)->fetchBusinessProfile('tok', 'biz'));
    }
}
