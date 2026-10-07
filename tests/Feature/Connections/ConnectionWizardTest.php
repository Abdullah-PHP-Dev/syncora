<?php

namespace Tests\Feature\Connections;

use App\Http\Controllers\Admin\ConnectionHubController;
use App\Models\SocialConnection;
use App\Models\User;
use App\Services\Connections\Drivers\GoogleDriver;
use App\Services\Connections\Drivers\LinkedInDriver;
use App\Services\Connections\Drivers\MetaDriver;
use App\Support\Connections\ConnectionWizard;
use App\Support\Settings;
use Tests\TestCase;

/** Commit 12b: "Connect all recommended". */
class ConnectionWizardTest extends TestCase
{
    use CreatesConnectionTables;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createConnectionTables();
        $this->createSettingsTable();
        // Only Meta, Google and LinkedIn have an app configured.
        Settings::set('posts.facebook.client_id', 'fb');
        Settings::set('posts.google.client_id', 'g');
        Settings::set('posts.linkedin.client_id', 'li');
        $this->user = User::create(['name' => 'Seller', 'email' => 's@example.com', 'password' => bcrypt('x')]);
        $this->actingAs($this->user);
        // Role, subscription and locale-redirect middleware aren't under test here.
        $this->withoutMiddleware();
    }

    private function wizard(): ConnectionWizard
    {
        return app(ConnectionWizard::class);
    }

    private function connect(string $platform, string $step, string $status = 'active'): void
    {
        SocialConnection::create(['user_id' => $this->user->id, 'platform' => $platform, 'step' => $step, 'provider_account_id' => uniqid(), 'status' => $status]);
    }

    public function test_recommends_configured_primary_steps_not_yet_working(): void
    {
        $this->connect('google', GoogleDriver::OAUTH);
        $this->connect('linkedin', LinkedInDriver::PAGES, SocialConnection::NEEDS_REAUTH);

        $this->assertSame([['meta', MetaDriver::LOGIN], ['linkedin', LinkedInDriver::PAGES]], array_map(fn ($s) => [$s['platform'], $s['step']], $this->wizard()->pending($this->user->id)));
    }

    public function test_start_goes_straight_into_the_first_consent(): void
    {
        $response = $this->post(route('admin.connections.wizard.start'));

        $response->assertRedirect();
        $this->assertStringContainsString('facebook.com', $response->headers->get('Location'));
        $this->assertSame('hub', session('social_oauth_return_to'));
        $this->assertSame(['total' => 3, 'done' => 0, 'skipped' => 0, 'next' => 'meta'], $this->summary());
    }

    public function test_progress_skip_and_a_one_time_finished_report(): void
    {
        $this->wizard()->start($this->user->id);

        // Meta finished; a cancelled consent would simply be offered again.
        $this->connect('meta', MetaDriver::LOGIN);
        $this->assertSame(['total' => 3, 'done' => 1, 'skipped' => 0, 'next' => 'google'], $this->summary());

        $this->post(route('admin.connections.wizard.skip'))->assertRedirect(route('admin.connections.index'));
        $this->assertSame('linkedin', $this->summary()['next']);

        $this->connect('linkedin', LinkedInDriver::PAGES);
        // Skipped steps count as passed for progress; the banner names them.
        $this->assertSame(['total' => 3, 'done' => 3, 'skipped' => 1, 'next' => null], $this->summary());
        $this->assertNull($this->wizard()->state($this->user->id));
    }

    public function test_hub_page_carries_the_banner_and_finish_clears_it(): void
    {
        $this->wizard()->start($this->user->id);
        $wizard = app(ConnectionHubController::class)->index()->getData()['wizard'];
        $this->assertSame('meta', $wizard['state']['next']['platform']);
        $this->assertStringEndsWith('/connections/meta/connect/' . MetaDriver::LOGIN, $wizard['state']['next']['url']);
        $this->assertTrue($wizard['available']);

        $this->delete(route('admin.connections.wizard.finish'))->assertRedirect(route('admin.connections.index'));
        $this->assertNull($this->wizard()->state($this->user->id));
    }

    public function test_nothing_to_do(): void
    {
        $this->connect('meta', MetaDriver::LOGIN);
        $this->connect('google', GoogleDriver::OAUTH);
        $this->connect('linkedin', LinkedInDriver::PAGES);

        $this->post(route('admin.connections.wizard.start'))
            ->assertRedirect(route('admin.connections.index'))
            ->assertSessionHas('success', 'Everything recommended is already connected.');
    }

    private function summary(): ?array
    {
        $state = $this->wizard()->state($this->user->id);

        return $state ? array_merge($state, ['next' => $state['next']['platform'] ?? null]) : null;
    }
}
