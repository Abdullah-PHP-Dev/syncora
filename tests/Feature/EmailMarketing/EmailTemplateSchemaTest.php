<?php

namespace Tests\Feature\EmailMarketing;

use App\Models\EmailMarketing\EmailTemplate;
use App\Models\User;
use Tests\Feature\EmailMarketing\Concerns\CreatesEmailMarketingTables;
use Tests\TestCase;

/**
 * Covers the new schema_json column round-tripping alongside body on
 * store()/update() - the block editor's whole "re-open and keep editing
 * the actual block tree, not just rendered HTML" promise depends on this
 * actually persisting correctly.
 */
class EmailTemplateSchemaTest extends TestCase
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

    private function validSchema(): array
    {
        return [
            'version'  => 1,
            'settings' => ['emailWidth' => 600, 'fontFamily' => 'Arial, Helvetica, sans-serif', 'backgroundColor' => '#f5f5fa'],
            'sections' => [[
                'id' => 'sec_1', 'type' => 'section', 'settings' => ['backgroundColor' => '#fff'],
                'columns' => [[
                    'id' => 'col_1', 'width' => '100%',
                    'blocks' => [['id' => 'blk_1', 'type' => 'text', 'content' => 'Hello', 'settings' => []]],
                ]],
            ]],
        ];
    }

    public function test_store_round_trips_schema_json_and_body(): void
    {
        $user = User::factory()->create();
        $schema = $this->validSchema();

        $response = $this->actingAs($user)->post('/email/templates', [
            'name' => 'My Template', 'subject' => 'Hi', 'body' => '<p>hi</p>',
            'status' => 'draft', 'schema_json' => json_encode($schema),
        ]);

        $response->assertRedirect();
        $template = EmailTemplate::where('name', 'My Template')->firstOrFail();
        $this->assertEquals($schema, $template->schema_json);
        $this->assertEquals('<p>hi</p>', $template->body);
    }

    public function test_update_round_trips_schema_json_and_body(): void
    {
        $user = User::factory()->create();
        $template = EmailTemplate::create([
            'user_id' => $user->id, 'name' => 'T', 'subject' => 'S', 'body' => '<p>old</p>', 'status' => 'draft',
        ]);

        $schema = $this->validSchema();
        $response = $this->actingAs($user)->patch('/email/templates/' . $template->id, [
            'name' => 'T', 'subject' => 'S', 'body' => '<p>new</p>',
            'status' => 'draft', 'schema_json' => json_encode($schema),
        ]);

        $response->assertRedirect();
        $template->refresh();
        $this->assertEquals($schema, $template->schema_json);
        $this->assertEquals('<p>new</p>', $template->body);
    }

    public function test_a_malformed_schema_is_rejected_and_never_persisted(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/email/templates', [
            'name' => 'Bad', 'subject' => 'S', 'body' => '<p>x</p>', 'status' => 'draft',
            'schema_json' => json_encode(['version' => 1, 'settings' => []]), // missing "sections"
        ]);

        $response->assertSessionHasErrors('schema_json');
        $this->assertDatabaseMissing('email_templates', ['name' => 'Bad']);
    }

    public function test_an_unknown_block_type_is_rejected(): void
    {
        $user = User::factory()->create();
        $schema = $this->validSchema();
        $schema['sections'][0]['columns'][0]['blocks'][0]['type'] = 'video'; // deferred to a later phase, not yet valid

        $response = $this->actingAs($user)->post('/email/templates', [
            'name' => 'Bad2', 'subject' => 'S', 'body' => '<p>x</p>', 'status' => 'draft',
            'schema_json' => json_encode($schema),
        ]);

        $response->assertSessionHasErrors('schema_json');
        $this->assertDatabaseMissing('email_templates', ['name' => 'Bad2']);
    }
}
