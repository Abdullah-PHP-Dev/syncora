<?php

namespace Tests\Feature\EmailMarketing;

use App\Models\EmailMarketing\EmailTemplate;
use App\Models\User;
use Tests\Feature\EmailMarketing\Concerns\CreatesEmailMarketingTables;
use Tests\TestCase;

/**
 * Restoring a version must re-hydrate the actual block tree
 * (schema_json), not just the rendered HTML - otherwise "restore" would
 * silently downgrade a block-editor template back to a frozen, no-longer-
 * editable-as-blocks HTML blob every time.
 */
class EmailTemplateVersionRestoreTest extends TestCase
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

    private function schema(string $marker): array
    {
        return [
            'version' => 1,
            'settings' => ['emailWidth' => 600],
            'sections' => [[
                'id' => 'sec_1', 'type' => 'section', 'settings' => [],
                'columns' => [['id' => 'col_1', 'width' => '100%', 'blocks' => [
                    ['id' => 'blk_1', 'type' => 'text', 'content' => $marker, 'settings' => []],
                ]]],
            ]],
        ];
    }

    public function test_restoring_a_version_restores_its_schema_json_too(): void
    {
        $user = User::factory()->create();
        $template = EmailTemplate::create([
            'user_id' => $user->id, 'name' => 'T', 'subject' => 'S',
            'body' => '<p>v1</p>', 'schema_json' => $this->schema('v1'), 'status' => 'draft',
        ]);

        // Save version 2 with a different schema.
        $this->actingAs($user)->patch('/email/templates/' . $template->id, [
            'name' => 'T', 'subject' => 'S', 'body' => '<p>v2</p>',
            'status' => 'draft', 'schema_json' => json_encode($this->schema('v2')),
        ]);

        $version1 = $template->versions()->where('version', 1)->firstOrFail();
        $this->assertEquals($this->schema('v1'), $version1->schema_json);

        $response = $this->actingAs($user)->post('/email/templates/' . $template->id . '/versions/' . $version1->id . '/restore');

        $response->assertRedirect();
        $template->refresh();
        $this->assertEquals($this->schema('v1'), $template->schema_json);
        $this->assertEquals('<p>v1</p>', $template->body);
    }

    public function test_restoring_a_legacy_version_with_no_schema_json_leaves_it_null(): void
    {
        $user = User::factory()->create();
        $template = EmailTemplate::create([
            'user_id' => $user->id, 'name' => 'Legacy', 'subject' => 'S',
            'body' => '<p>legacy html</p>', 'schema_json' => null, 'status' => 'draft',
        ]);

        // First save via the legacy code editor (no schema_json submitted)
        // snapshots version 1 as editor_type=code, schema_json=null.
        $this->actingAs($user)->patch('/email/templates/' . $template->id, [
            'name' => 'Legacy', 'subject' => 'S', 'body' => '<p>legacy html edited</p>', 'status' => 'draft',
        ]);

        $version1 = $template->versions()->where('version', 1)->firstOrFail();
        $this->assertNull($version1->schema_json);
        $this->assertEquals('code', $version1->editor_type);

        // Now save with a real schema (the seller switched to the block
        // editor for this template).
        $this->actingAs($user)->patch('/email/templates/' . $template->id, [
            'name' => 'Legacy', 'subject' => 'S', 'body' => '<p>now blocks</p>',
            'status' => 'draft', 'schema_json' => json_encode($this->schema('blocks')),
        ]);
        $this->assertNotNull($template->fresh()->schema_json);

        // Restoring the legacy version 1 should put schema_json back to
        // null - a code-editor version has no block tree to restore.
        $response = $this->actingAs($user)->post('/email/templates/' . $template->id . '/versions/' . $version1->id . '/restore');

        $response->assertRedirect();
        $this->assertNull($template->fresh()->schema_json);
        // version 1 was snapshotted from the ORIGINAL body, before the
        // first patch() call overwrote it with "edited" - restoring it
        // brings back the original, not the edited value.
        $this->assertEquals('<p>legacy html</p>', $template->fresh()->body);
    }
}
