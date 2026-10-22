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

    public function test_every_indexable_page_has_a_unique_title_and_description_within_serp_limits(): void
    {
        $seo = new MarketingSeo();
        $titles = [];
        foreach (array_keys($seo->pages()) as $route) {
            foreach (['en', 'ar'] as $locale) {
                $meta = $seo->metadata($route, $locale);
                $this->assertNotEmpty($meta['title'], "$route/$locale title");
                $this->assertLessThanOrEqual(60, mb_strlen($meta['title']), "$route/$locale title length");
                $this->assertGreaterThanOrEqual(70, mb_strlen((string) $meta['description']), "$route/$locale description too short");
                $this->assertLessThanOrEqual(160, mb_strlen((string) $meta['description']), "$route/$locale description length");
                $titles[] = $meta['title'];
            }
        }
        $this->assertCount(count($titles), array_unique($titles), 'duplicate titles');
        $this->assertSame('Social Media Scheduler & Content Calendar | Socialeaz', $seo->metadata('product.scheduling-publishing', 'en')['title']);
    }

    public function test_software_application_schema_uses_real_plan_prices_and_no_invented_ratings(): void
    {
        $seo = $this->partialMock(MarketingSeo::class, fn ($m) => $m->shouldReceive('planOffers')->andReturn(['@type' => 'AggregateOffer', 'priceCurrency' => 'SAR', 'lowPrice' => 0.0, 'highPrice' => 299.0, 'offerCount' => 3]));
        $graph = $seo->schema('product.analytics', 'en', 'Analytics | Socialeaz', 'Description')['@graph'];
        $app = collect($graph)->firstWhere('@type', 'SoftwareApplication');
        $this->assertSame(['BusinessApplication', 'Web', 'SAR', 299.0], [$app['applicationCategory'], $app['operatingSystem'], $app['offers']['priceCurrency'], $app['offers']['highPrice']]);
        $this->assertSame('https://socialeaz.com/en/pricing', $app['offers']['url']);
        $this->assertArrayNotHasKey('aggregateRating', $app);
        $this->assertArrayNotHasKey('review', $app);
        $this->assertSame('Analytics', collect($graph)->firstWhere('@type', 'BreadcrumbList')['itemListElement'][1]['name']);
        $this->assertNull(collect($seo->schema('about', 'en', 'About', 'Description')['@graph'])->firstWhere('@type', 'SoftwareApplication'));
    }

    public function test_sitemap_lists_hreflang_alternates_for_every_url(): void
    {
        $xml = simplexml_load_string((new SeoController())->sitemap(new MarketingSeo())->getContent());
        $xml->registerXPathNamespace('s', 'http://www.sitemaps.org/schemas/sitemap/0.9');
        $xml->registerXPathNamespace('x', 'http://www.w3.org/1999/xhtml');
        foreach ($xml->xpath('//s:url') as $url) {
            $url->registerXPathNamespace('x', 'http://www.w3.org/1999/xhtml');
            $links = $url->xpath('x:link');
            $this->assertCount(3, $links);
            $byLang = collect($links)->mapWithKeys(fn ($l) => [(string) $l['hreflang'] => (string) $l['href']]);
            $this->assertSame($byLang['en'], $byLang['x-default']);
            $this->assertContains((string) $url->loc, [$byLang['en'], $byLang['ar']]);
        }
    }

    public function test_robots_keeps_private_areas_out_of_search(): void
    {
        $live = (new SeoController())->robots(Request::create('https://socialeaz.com/robots.txt'), new MarketingSeo())->getContent();
        foreach (['/dashboard', '/admin/', '/api/', '/en/dashboard', '/ar/admin/'] as $path) {
            $this->assertStringContainsString("Disallow: {$path}\n", $live);
        }
    }

    public function test_security_headers_are_added_without_overriding_the_edge(): void
    {
        $middleware = new \App\Http\Middleware\SecurityHeaders();
        $https = $middleware->handle(Request::create('https://socialeaz.com/en'), fn () => response('ok'));
        $this->assertSame('max-age=31536000', $https->headers->get('Strict-Transport-Security'));
        $this->assertSame('strict-origin-when-cross-origin', $https->headers->get('Referrer-Policy'));

        $http = $middleware->handle(Request::create('http://socialeaz.com/en'), fn () => response('ok')->header('Referrer-Policy', 'no-referrer'));
        $this->assertFalse($http->headers->has('Strict-Transport-Security'));
        $this->assertSame('no-referrer', $http->headers->get('Referrer-Policy'));
    }

    public function test_marketing_pages_reference_optimized_images_that_exist(): void
    {
        $views = ['front/pages/home_modern', 'front/pages/pricing', 'front/pages/about', 'front/pages/services', 'layouts/partials/header_modern', 'layouts/partials/footer_modern'];
        foreach ($views as $view) {
            $source = file_get_contents(resource_path("views/{$view}.blade.php"));
            $this->assertDoesNotMatchRegularExpression('#home-theme/(hero|logo|avatars|planner|approvals|analytics|copilot-[a-z]+)(-studio)?\.png#', $source, "$view still uses a heavy PNG");
            preg_match_all('#home-theme/([a-z0-9_-]+\.webp)#', $source, $m);
            foreach ($m[1] as $file) {
                $this->assertFileExists(public_path('assets/images/home-theme/'.$file));
                $this->assertLessThan(150 * 1024, filesize(public_path('assets/images/home-theme/'.$file)), "$file is too large");
            }
        }
    }

    public function test_google_analytics_tag_renders_only_on_the_production_host(): void
    {
        $render = function (string $url, ?string $id) {
            config(['seo.google_analytics_id' => $id]);
            $this->app->instance('request', Request::create($url));

            return view('layouts.partials.google-analytics')->render();
        };

        $live = $render('https://socialeaz.com/en', 'G-7L4307MDTL');
        $this->assertStringContainsString('https://www.googletagmanager.com/gtag/js?id=G-7L4307MDTL', $live);
        $this->assertStringContainsString("gtag('config', 'G-7L4307MDTL');", $live);
        $this->assertSame(1, substr_count($live, 'gtag/js'));

        $this->assertStringNotContainsString('gtag', $render('https://labs.socialeaz.com/en', 'G-7L4307MDTL'));
        $this->assertStringNotContainsString('gtag', $render('https://socialeaz.com/en', null));
        $this->assertStringNotContainsString('gtag', $render('https://socialeaz.com/en', "G-1');alert(1);//"));
    }

    public function test_google_analytics_id_comes_from_the_admin_setting_first(): void
    {
        $cache = new \ReflectionProperty(\App\Support\Settings::class, 'requestCache');
        $cache->setValue(null, ['google.analytics.measurement_id' => 'G-ADMIN123']);
        config(['seo.google_analytics_id' => 'G-ENV456']);
        $this->app->instance('request', Request::create('https://socialeaz.com/en'));

        try {
            $html = view('layouts.partials.google-analytics')->render();
            $this->assertStringContainsString("gtag('config', 'G-ADMIN123');", $html);
            $this->assertStringNotContainsString('G-ENV456', $html);

            $cache->setValue(null, ['google.analytics.measurement_id' => null]);
            $this->assertStringContainsString("gtag('config', 'G-ENV456');", view('layouts.partials.google-analytics')->render());
        } finally {
            $cache->setValue(null, null);
        }
    }

    public function test_static_robots_file_matches_the_production_rules(): void
    {
        $file = file_get_contents(public_path('robots.txt'));
        $rules = trim(preg_replace('/^#.*\n/m', '', $file));
        $live = trim((new SeoController())->robots(Request::create('https://socialeaz.com/robots.txt'), new MarketingSeo())->getContent());

        $this->assertSame($live, $rules, 'public/robots.txt is out of sync with SeoController::robots()');
        $this->assertStringContainsString('Sitemap: https://socialeaz.com/sitemap.xml', $file);
    }
}
