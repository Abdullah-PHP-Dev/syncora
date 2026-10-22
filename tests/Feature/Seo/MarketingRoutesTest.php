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
