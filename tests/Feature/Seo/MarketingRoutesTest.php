<?php

namespace Tests\Feature\Seo;

use Tests\TestCase;

/**
 * HTTP-level checks on the public marketing routes. Routes are registered
 * for one locale per application boot (LaravelLocalization::setLocale()
 * reads ROUTING_LOCALE in tests), so each test boots the locale it needs.
 */
class MarketingRoutesTest extends TestCase
{
    private function bootLocale(?string $locale): void
    {
        putenv($locale ? 'ROUTING_LOCALE='.$locale : 'ROUTING_LOCALE');
        $this->refreshApplication();
        $this->withoutVite();
        config(['seo.base_url' => 'https://socialeaz.com', 'seo.locales' => ['en', 'ar'], 'seo.indexable' => true]);
        // Home and pricing read plans from the legacy bundles table, which
        // has no migration - render them with an empty catalog.
        $this->partialMock(\App\Services\PackagePresentationService::class, fn ($m) => $m->shouldReceive('catalog')
            ->andReturn(['plans' => collect(), 'features' => collect(), 'maxSaving' => 0]));
    }

    protected function tearDown(): void
    {
        putenv('ROUTING_LOCALE');
        parent::tearDown();
    }

    public function test_legacy_product_page_permanently_redirects_to_services(): void
    {
        foreach (['en', 'ar'] as $locale) {
            $this->bootLocale($locale);
            $this->get("/{$locale}/product")->assertStatus(301)->assertRedirect("/{$locale}/services");
            $this->get("/{$locale}/product/analytics")->assertOk();
        }
        $this->assertFileDoesNotExist(resource_path('views/front/pages/product.blade.php'));
    }

    public function test_public_marketing_pages_are_never_rate_limited(): void
    {
        $this->bootLocale('en');
        for ($i = 0; $i < 100; $i++) {
            $this->get('/en/services', ['User-Agent' => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)'])->assertOk();
        }
        foreach (array_keys(app(\App\Support\Seo\MarketingSeo::class)->pages()) as $name) {
            $middleware = implode(',', app('router')->gatherRouteMiddleware(app('router')->getRoutes()->getByName($name)));
            $this->assertStringNotContainsString('throttle', strtolower($middleware), "$name is throttled");
        }
    }

    public function test_every_sitemap_url_is_an_indexable_self_canonical_page_with_reciprocal_hreflang(): void
    {
        $this->bootLocale(null);
        $response = $this->get('/sitemap.xml')->assertOk();
        $this->assertStringStartsWith('application/xml', $response->headers->get('Content-Type'));
        $xml = simplexml_load_string($response->getContent());
        $xml->registerXPathNamespace('s', 'http://www.sitemaps.org/schemas/sitemap/0.9');

        $entries = [];
        foreach ($xml->xpath('//s:url') as $url) {
            $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', (string) $url->lastmod, "{$url->loc} has no lastmod");
            $this->assertDoesNotMatchRegularExpression('#/(login|register|dashboard|admin|password|forgot)#', (string) $url->loc);
            $alternates = [];
            foreach ($url->children('http://www.w3.org/1999/xhtml')->link as $link) {
                $alternates[(string) $link->attributes()->hreflang] = (string) $link->attributes()->href;
            }
            $entries[(string) $url->loc] = $alternates;
        }
        $this->assertNotEmpty($entries);

        foreach (['en', 'ar'] as $locale) {
            $this->bootLocale($locale);
            foreach ($entries as $loc => $alternates) {
                if (! str_starts_with($loc, "https://socialeaz.com/{$locale}")) {
                    continue;
                }
                $html = $this->get($loc)->assertOk()->getContent();
                $this->assertStringContainsString('<link rel="canonical" href="'.$loc.'">', $html, "$loc canonical");
                $this->assertStringContainsString('content="index, follow', $html, "$loc is not indexable");
                $this->assertStringNotContainsString('noindex', $html, "$loc sends noindex");
                $this->assertSame(1, preg_match_all('#<h1[\s>]#', $html), "$loc must have exactly one H1");
                foreach (['og:title', 'og:description', 'og:image'] as $og) {
                    $this->assertMatchesRegularExpression('#<meta property="'.$og.'" content="[^"]+">#', $html, "$loc $og");
                }
                $this->assertStringContainsString('<meta property="og:locale" content="'.($locale === 'ar' ? 'ar_SA' : 'en_US').'">', $html);
                preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $html, $m);
                $faqPage = collect(json_decode($m[1], true, flags: JSON_THROW_ON_ERROR)['@graph'])->firstWhere('@type', 'FAQPage');
                foreach ($faqPage['mainEntity'] ?? [] as $question) {
                    $this->assertStringContainsString(e($question['name']), $html, "$loc FAQ schema question is not visible");
                }
                foreach ($alternates as $lang => $href) {
                    $this->assertStringContainsString('hreflang="'.$lang.'" href="'.$href.'"', $html, "$loc hreflang $lang");
                    // Reciprocal: the alternate lists this URL back under the same set.
                    $this->assertSame($alternates, $entries[$href] ?? null, "$href does not reciprocate $loc");
                }
            }
        }
    }

    public function test_homepage_internal_links_never_hit_a_redirect(): void
    {
        foreach (['en', 'ar'] as $locale) {
            $this->bootLocale($locale);
            $html = $this->get("https://socialeaz.com/{$locale}")->assertOk()->getContent();
            preg_match_all('#href="(https://socialeaz\.com/[^"\#?]*|/[^/"\#?][^"\#?]*)#', $html, $m);
            $paths = collect($m[1])->map(fn ($href) => parse_url($href, PHP_URL_PATH))
                ->filter(fn ($path) => str_starts_with($path, "/{$locale}"))
                ->unique();
            $this->assertGreaterThan(10, $paths->count());
            foreach ($paths as $path) {
                $status = $this->get('https://socialeaz.com'.$path)->getStatusCode();
                $this->assertSame(200, $status, "homepage ($locale) links to $path, which returns $status");
            }
        }
    }

    public function test_legacy_aliases_are_permanent_redirects(): void
    {
        $this->bootLocale('en');
        $this->get('/en/help')->assertStatus(301)->assertRedirect(url('/en/contact').'#support-faq');
        $this->get('/en/channels')->assertStatus(301)->assertRedirect(url('/en').'#channels');
        $this->get('/en/tools')->assertStatus(301);
        $this->get('/en/ai-copilot')->assertStatus(301);
        $this->get('/en/amber')->assertStatus(301);
        $this->get('/en/guides')->assertNotFound();
        $this->get('/en/api')->assertNotFound();
    }

    public function test_retired_homepage_designs_permanently_redirect_to_the_homepage_in_their_locale(): void
    {
        foreach (['en', 'ar'] as $locale) {
            $this->bootLocale($locale);
            foreach (['yellow', 'studio', 'amber'] as $retired) {
                // One hop, straight to the canonical homepage (no /amber -> /studio chain).
                $this->get("https://socialeaz.com/{$locale}/{$retired}")->assertStatus(301)->assertRedirect("https://socialeaz.com/{$locale}");
            }
            $this->get("https://socialeaz.com/{$locale}")->assertOk();
        }
    }

    public function test_index_php_urls_permanently_redirect_to_the_clean_url(): void
    {
        $this->bootLocale('en');
        $send = fn (string $uri, string $method = 'GET') => $this->app->make(\Illuminate\Contracts\Http\Kernel::class)
            ->handle(\Illuminate\Http\Request::create('https://socialeaz.com'.$uri, $method, server: ['SCRIPT_NAME' => '/index.php', 'SCRIPT_FILENAME' => public_path('index.php')]));
        $location = fn (string $uri) => [$send($uri)->getStatusCode(), $send($uri)->headers->get('Location')];

        $this->assertSame([301, '/en/pricing'], $location('/index.php/en/pricing'));
        $this->assertSame([301, '/en/pricing?billing=yearly'], $location('/index.php/en/pricing?billing=yearly'));
        $this->assertSame([301, '/en'], $location('/index.php/en/'));
        $this->assertSame([301, '/'], $location('/index.php'));
        $this->assertSame([301, '/?x=1'], $location('/index.php?x=1'));
        $this->assertSame([301, '/evil.example'], $location('/index.php//evil.example/'));
        $this->assertNotSame(301, $send('/index.php/en/login', 'POST')->getStatusCode());
        // The clean URL itself is served, not redirected again.
        $this->assertSame(200, $send('/en/pricing')->getStatusCode());
        $this->assertSame(404, $send('/index.phpx')->getStatusCode());
    }

    public function test_trailing_slashes_permanently_redirect_to_the_slashless_url(): void
    {
        $this->bootLocale('en');
        // The test client trims trailing slashes, so send raw requests through the kernel.
        $send = fn (string $uri, string $method = 'GET') => $this->app->make(\Illuminate\Contracts\Http\Kernel::class)
            ->handle(\Illuminate\Http\Request::create('https://socialeaz.com'.$uri, $method));

        $this->assertSame([301, '/en'], [$send('/en/')->getStatusCode(), $send('/en/')->headers->get('Location')]);
        $this->assertSame('/en/pricing?billing=yearly', $send('/en/pricing/?billing=yearly')->headers->get('Location'));
        $this->assertSame('/evil.example', $send('//evil.example/')->headers->get('Location'));
        $this->assertNotSame(301, $send('/en/login/', 'POST')->getStatusCode());
        $this->get('/en/pricing')->assertOk();
    }

    public function test_faq_pages_emit_faqpage_schema_from_their_visible_faqs(): void
    {
        $seo = app(\App\Support\Seo\MarketingSeo::class);
        foreach (['contact', 'product.scheduling-publishing', 'solutions.teams'] as $route) {
            $graph = collect($seo->schema($route, 'ar', 'Title', 'Description')['@graph']);
            $faq = $graph->firstWhere('@type', 'FAQPage');
            $this->assertNotNull($faq, "$route has no FAQPage");
            $this->assertSame(count($seo->faqs($route, 'ar')), count($faq['mainEntity']));
            $this->assertSame('Answer', $faq['mainEntity'][0]['acceptedAnswer']['@type']);
        }
        $this->assertNull(collect($seo->schema('pricing', 'en', 'Title', 'Description')['@graph'])->firstWhere('@type', 'FAQPage'));
    }

    public function test_auth_pages_stay_noindex(): void
    {
        $this->bootLocale('en');
        foreach (['/en/login', '/en/register'] as $path) {
            $this->get('https://socialeaz.com'.$path)->assertOk()->assertSee('<meta name="robots" content="noindex, follow">', false);
        }
    }

    public function test_homepage_organization_schema_and_footer_share_the_public_social_links(): void
    {
        $this->bootLocale('en');
        $html = $this->get('https://socialeaz.com/en')->assertOk()->getContent();

        preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $html, $m);
        $schema = json_decode($m[1] ?? '', true, flags: JSON_THROW_ON_ERROR);
        $org = collect($schema['@graph'])->firstWhere('@type', 'Organization');
        $this->assertSame('Socialeaz', $org['name']);
        $this->assertSame(['SocialEaz', 'Social Eaz', 'سوشيال إيز'], $org['alternateName']);
        $this->assertSame('ImageObject', $org['logo']['@type']);
        $this->assertGreaterThanOrEqual(512, getimagesize(public_path('assets/images/home-theme/logo-square-1024.png'))[0]);
        $this->assertSame(['@type' => 'PostalAddress', 'addressLocality' => 'Riyadh', 'addressCountry' => 'SA'], $org['address']);
        $this->assertSame(['en', 'ar'], $org['contactPoint']['availableLanguage']);

        $expected = array_values(array_filter(config('social'), fn ($url) => is_string($url) && str_starts_with($url, 'https://')));
        $this->assertSame($expected, $org['sameAs']);
        foreach ($org['sameAs'] as $url) {
            $this->assertStringNotContainsString('/admin/', $url);
            $this->assertStringContainsString('href="'.$url.'"', $html, "footer is missing $url");
        }
    }
}
