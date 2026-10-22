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
}
