<?php

namespace Tests\Feature\LinkedIn;

use App\Models\SocialAccount;
use App\Models\User;
use App\Services\SocialAuth\SocialAuthService;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * LinkedIn posting connect (SocialAuthService::callbackLinkedin): Company
 * Pages the user administers are saved as posting accounts, using an
 * active LinkedIn-Version, and failures are reported instead of a silent
 * "Connected 0".
 */
class LinkedInConnectTest extends TestCase
{
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            '0001_01_01_000000_create_users_table.php',
            '2026_06_13_213124_create_permission_tables.php',
            '2026_08_26_100000_create_social_accounts_table.php',
            '2026_08_28_100000_create_social_account_post_details_table.php',
        ] as $migration) {
            (require database_path('migrations/' . $migration))->up();
        }

        $this->user = User::create(['name' => 'Seller', 'email' => 'seller@example.com', 'password' => bcrypt('x')]);
        $this->actingAs($this->user);
        session(['social_oauth_state_linkedin' => 'state-1']);
    }

    private function fakeLinkedIn(array $acls, int $aclsStatus = 200): void
    {
        Http::fake([
            'www.linkedin.com/oauth/v2/accessToken' => Http::response(['access_token' => 'li-token', 'expires_in' => 5184000]),
            'api.linkedin.com/rest/organizationAcls*' => Http::response($acls, $aclsStatus),
            'api.linkedin.com/rest/organizations/555060042' => Http::response(['localizedName' => 'Socialeaz', 'vanityName' => 'socialeaz']),
            'api.linkedin.com/rest/adAccountUsers*' => Http::response(['elements' => []]),
            '*' => Http::response([], 404),
        ]);
    }

    public function test_company_page_is_saved_as_a_posting_account(): void
    {
        $this->fakeLinkedIn(['elements' => [['organization' => 'urn:li:organization:555060042', 'role' => 'ADMINISTRATOR']]]);

        $response = app(SocialAuthService::class)->callback('linkedin', 'code-1', 'state-1');

        $this->assertStringContainsString('Connected 1 LinkedIn Organization', session('success'));
        $account = SocialAccount::where('platform', 'linkedin')->firstOrFail();
        $this->assertSame('555060042', $account->platform_account_id);
        $this->assertSame('Socialeaz', $account->name);
        $this->assertSame('organization', $account->account_type);
        $this->assertTrue($account->has_posting_permission);
        // An active API version, not the retired 202401.
        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), 'organizationAcls') && $r->hasHeader('LinkedIn-Version', '202606'));
        Http::assertNotSent(fn (HttpRequest $r) => $r->hasHeader('LinkedIn-Version', '202401'));
        $this->assertTrue($response->isRedirect());
    }

    public function test_rejected_acls_call_shows_linkedins_reason(): void
    {
        $this->fakeLinkedIn(['status' => 426, 'message' => 'Requested version 20240101 is not active'], 426);

        app(SocialAuthService::class)->callback('linkedin', 'code-1', 'state-1');

        $this->assertStringContainsString('Requested version 20240101 is not active', session('error'));
        $this->assertSame(0, SocialAccount::count());
    }

    public function test_no_admin_pages_explains_what_to_do(): void
    {
        $this->fakeLinkedIn(['elements' => []]);

        app(SocialAuthService::class)->callback('linkedin', 'code-1', 'state-1');

        $this->assertStringContainsString('No LinkedIn Company Pages found', session('error'));
    }

    public function test_provider_error_is_shown(): void
    {
        Http::fake();

        app(SocialAuthService::class)->callback('linkedin', '', 'state-1', null, 'The user cancelled LinkedIn login');

        $this->assertStringContainsString('The user cancelled LinkedIn login', session('error'));
        Http::assertNothingSent();
    }
}
