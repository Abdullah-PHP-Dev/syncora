<?php

namespace Tests\Feature\EmailMarketing;

use App\Models\EmailMarketing\EmailTemplate;
use App\Models\User;
use Tests\Feature\EmailMarketing\Concerns\CreatesEmailMarketingTables;
use Tests\TestCase;

/**
 * autosave() is deliberately NOT update() - a version row per autosave
 * tick (every ~2s while editing) would explode email_template_versions
 * and defeat version history as meaningful checkpoints rather than a
 * running log. These tests pin that distinction plus the same tenant
 * isolation guarantee every other ownership-checked route in this module
 * already has (see TenantIsolationTest.php).
 */
class EmailTemplateAutosaveTest extends TestCase
{
    use CreatesEmailMarketingTables;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createEmailMarketingTables();
        $this->withoutMiddleware([
            \Mcamara\LaravelLocalization\Middleware\LocaleSessionRedirect::class,
            \Mcamara\LaravelLocalization\Middleware\LocaleCookieRedirect::class,
            \Mcamara\LaravelLocalization\Middleware\LaravelLocalizationRedirectFilter::class,
            \App\Http\Middleware\EnsureSeller::class,
            \App\Http\Middleware\EnsureActiveSubscription::class,
        ]);
    }

    public function test_autosave_updates_schema_and_body_without_creating_a_version_row(): void
    {
        $user = User::factory()->create();
        $template = EmailTemplate::create([
            'user_id' => $user->id, 'name' => 'T', 'subject' => 'S', 'body' => '<p>old</p>',
            'status' => 'draft', 'current_version' => 3,
        ]);
        $template->versions()->create(['version' => 1, 'html_content' => '<p>v1</p>']);
        $template->versions()->create(['version' => 2, 'html_content' => '<p>v2</p>']);

        $schema = ['version' => 1, 'settings' => [], 'sections' => []];

        $response = $this->actingAs($user)->post('/email/templates/' . $template->id . '/autosave', [
            'body' => '<p>autosaved</p>', 'schema_json' => json_encode($schema),
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $template->refresh();
        $this->assertEquals('<p>autosaved</p>', $template->body);
        $this->assertEquals($schema, $template->schema_json);
        $this->assertEquals(3, $template->current_version, 'autosave must never bump current_version');
        $this->assertEquals(2, $template->versions()->count(), 'autosave must never create a version row');
    }

    public function test_autosave_is_blocked_for_another_sellers_template(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $template = EmailTemplate::create([
            'user_id' => $owner->id, 'name' => 'T', 'subject' => 'S', 'body' => '<p>x</p>', 'status' => 'draft',
        ]);

        $response = $this->actingAs($intruder)->post('/email/templates/' . $template->id . '/autosave', [
            'body' => '<p>hacked</p>',
        ]);

        $response->assertForbidden();
        $this->assertEquals('<p>x</p>', $template->fresh()->body);
    }
}
