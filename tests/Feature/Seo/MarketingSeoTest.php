<?php

namespace Tests\Feature\Seo;

use App\Http\Controllers\SeoController;
use App\Support\Seo\MarketingSeo;
use Illuminate\Http\Request;
use Tests\TestCase;

class MarketingSeoTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['seo.base_url' => 'https://socialeaz.com', 'seo.locales' => ['en', 'ar'], 'seo.indexable' => true]);
    }

    public function test_canonical_urls_are_localized_and_auth_pages_are_excluded(): void
    {
        $seo = new MarketingSeo();
        $this->assertSame('https://socialeaz.com/en', $seo->localizedUrl('home', 'en'));
        $this->assertSame('https://socialeaz.com/ar/product/integrations', $seo->localizedUrl('product.integrations', 'ar'));
        $this->assertNull($seo->localizedUrl('password.reset', 'en'));
        $this->assertNull($seo->localizedUrl('login', 'en'));
        $this->assertFalse($seo->indexableHost('socialize.test'));
        $this->assertTrue($seo->indexableHost('socialeaz.com'));
    }

    public function test_sitemap_contains_each_public_page_in_both_languages(): void
    {
        $seo = new MarketingSeo();
        $response = (new SeoController())->sitemap($seo);
        $xml = simplexml_load_string($response->getContent());
        $this->assertNotFalse($xml);
        $urls = array_map('strval', $xml->xpath('//*[local-name()="loc"]'));
        $this->assertCount(count($seo->pages()) * 2, $urls);
        $this->assertContains('https://socialeaz.com/ar/solutions/brands', $urls);
        $this->assertCount(count($urls), array_unique($urls));
        foreach ($urls as $url) {
            $this->assertStringStartsWith('https://socialeaz.com/', $url);
            $this->assertStringNotContainsString('forgot-password', $url);
            $this->assertStringNotContainsString('?', $url);
        }
    }

    public function test_robots_advertises_the_sitemap_and_blocks_local_hosts(): void
    {
        $controller = new SeoController();
        $seo = new MarketingSeo();
        $live = $controller->robots(Request::create('https://socialeaz.com/robots.txt'), $seo)->getContent();
        $local = $controller->robots(Request::create('http://socialize.test/robots.txt'), $seo)->getContent();
        $this->assertStringContainsString("Allow: /\n", $live);
        $this->assertStringNotContainsString("Disallow: /\n", $live);
        $this->assertStringContainsString('Sitemap: https://socialeaz.com/sitemap.xml', $live);
        $this->assertStringContainsString("Disallow: /\n", $local);
    }

    public function test_live_arabic_metadata_is_indexable_with_self_referencing_alternates(): void
    {
        $request = Request::create('https://socialeaz.com/ar/pricing?billing=yearly&utm_source=test');
        $request->setRouteResolver(fn () => (new \Illuminate\Routing\Route('GET', 'ar/pricing', fn () => null))->name('pricing'));
        $this->app->instance('request', $request);
        $this->app->setLocale('ar');
        $html = view('layouts.partials.marketing-seo')->render();
        $this->assertStringContainsString('content="index, follow, max-image-preview:large"', $html);
        $this->assertStringContainsString('rel="canonical" href="https://socialeaz.com/ar/pricing"', $html);
        $this->assertStringContainsString('hreflang="en" href="https://socialeaz.com/en/pricing"', $html);
        $this->assertStringContainsString('hreflang="ar" href="https://socialeaz.com/ar/pricing"', $html);
        $this->assertStringNotContainsString('utm_source', $html);
    }

    public function test_localized_metadata_and_structured_data_use_real_site_urls(): void
    {
        $seo = new MarketingSeo();
        $en = $seo->metadata('home', 'en');
        $ar = $seo->metadata('home', 'ar');
        $this->assertNotSame($en['title'], $ar['title']);
        $this->assertNotSame($en['description'], $ar['description']);
        config(['social.Instagram' => null]);
        $schema = $seo->schema('product.analytics', 'ar', 'التحليلات', 'وصف الصفحة');
        $this->assertSame('https://schema.org', $schema['@context']);
        $this->assertSame('https://socialeaz.com/ar/product/analytics', $schema['@graph'][2]['url']);
        $this->assertSame('ar', $schema['@graph'][2]['inLanguage']);
        $this->assertSame('support@socialeaz.com', $schema['@graph'][0]['email']);
        $this->assertArrayNotHasKey('aggregateRating', $schema['@graph'][0]);
    }
}
