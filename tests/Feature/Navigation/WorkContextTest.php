<?php

namespace Tests\Feature\Navigation;

use App\Support\WorkContext;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Tests\TestCase;

/** Working platform carried between Content, Ads and Inbox. */
class WorkContextTest extends TestCase
{
    private function visit(string $routeName, string $pattern, string $path, array $params = [], array $query = []): void
    {
        $request = Request::create('https://syncora.test/' . $path, 'GET', $query);
        $request->setLaravelSession($this->app['session.store']);
        $route = (new Route('GET', $pattern, []))->name($routeName);
        $route->bind($request);
        $request->setRouteResolver(fn () => $route);

        WorkContext::capture($request);
    }

    public function test_platform_page_sets_the_context(): void
    {
        $this->visit('admin.ads.campaigns.index', 'ads/{platform}/campaigns', 'ads/tiktok/campaigns');

        $this->assertSame('tiktok', WorkContext::platform());
        $this->assertSame('ads', WorkContext::module());
        $this->assertSame('tiktok', WorkContext::platformFor('posts'));
    }

    public function test_overview_keeps_the_last_platform(): void
    {
        $this->visit('admin.posts.index', 'posts/listing', 'posts/listing', [], ['platform' => 'instagram']);
        $this->visit('admin.posts.dashboard', 'posts/dashboard', 'posts/dashboard');

        $this->assertSame('instagram', WorkContext::platform());
        $this->assertSame('posts', WorkContext::module());
    }

    public function test_url_platform_replaces_the_context_and_twitter_means_x(): void
    {
        $this->visit('admin.ads.campaigns.index', 'ads/{platform}/campaigns', 'ads/facebook/campaigns');
        $this->visit('admin.posts.index', 'posts/listing', 'posts/listing', [], ['platform' => 'twitter']);

        $this->assertSame('x', WorkContext::platform());
    }

    public function test_module_only_gets_platforms_it_supports(): void
    {
        session(['work_context' => ['module' => 'inbox', 'platform' => 'whatsapp']]);

        $this->assertSame('whatsapp', WorkContext::platformFor('inbox'));
        $this->assertNull(WorkContext::platformFor('ads'), 'no WhatsApp ads module');
    }

    public function test_junk_platform_values_are_ignored(): void
    {
        $this->visit('admin.posts.index', 'posts/listing', 'posts/listing', [], ['platform' => '"><script>']);

        $this->assertNull(WorkContext::platform());
    }

    public function test_unrelated_pages_do_not_change_context(): void
    {
        session(['work_context' => ['module' => 'ads', 'platform' => 'linkedin']]);
        $this->visit('admin.ai-copilot.analytics.index', 'ai-copilot/analytics', 'ai-copilot/analytics');

        $this->assertSame('linkedin', WorkContext::platform());
        $this->assertSame('ads', WorkContext::module());
    }
}
