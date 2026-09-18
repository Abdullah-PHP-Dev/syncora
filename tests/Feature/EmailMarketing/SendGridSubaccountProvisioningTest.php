<?php

namespace Tests\Feature\EmailMarketing;

use App\Models\EmailMarketing\EmailSubaccount;
use App\Models\User;
use App\Services\EmailMarketingServices\SendGridSubaccountService;
use Illuminate\Support\Facades\Http;
use Tests\Feature\EmailMarketing\Concerns\CreatesEmailMarketingTables;
use Tests\TestCase;

/**
 * The spec's explicit "must not create duplicate SendGrid subaccounts
 * after retries/page refresh" requirement - provision() is called twice
 * for the same user, and only the FIRST call should actually hit
 * POST /subusers or POST /api_keys.
 */
class SendGridSubaccountProvisioningTest extends TestCase
{
    use CreatesEmailMarketingTables;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createEmailMarketingTables();

        \App\Models\Admin\AdminSetting::query()->delete();
        set_adminSetting('email_marketing.sendgrid.parent_api_key', 'SG.parent-key');
    }

    public function test_provisioning_is_idempotent_and_only_calls_sendgrid_once(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'api.sendgrid.com/v3/subusers' => Http::response(['user_id' => 555, 'username' => 'socialeaz_user_1'], 200),
            'api.sendgrid.com/v3/api_keys' => Http::response(['api_key' => 'SG.subuser-key', 'api_key_id' => 'abc123'], 200),
        ]);

        $user = User::factory()->create();
        $service = app(SendGridSubaccountService::class);

        $first = $service->provision($user);
        $this->assertTrue($first['success']);
        $this->assertFalse($first['already_existed']);
        $this->assertEquals('active', $first['subaccount']->status);

        $second = $service->provision($user);
        $this->assertTrue($second['success']);
        $this->assertTrue($second['already_existed']);

        $this->assertEquals(1, EmailSubaccount::where('user_id', $user->id)->count());
        Http::assertSentCount(2); // exactly the two calls from the FIRST provision() only.
    }

    public function test_a_previously_failed_provision_can_retry(): void
    {
        Http::preventStrayRequests();
        // Both responses declared in ONE fake() call as a sequence - a
        // second fake() call for the same URL pattern doesn't reliably
        // override the first within one test process (documented
        // accumulation gotcha), so the failing-then-succeeding call must
        // be expressed as a sequence up front instead.
        Http::fake([
            'api.sendgrid.com/v3/subusers' => Http::sequence()
                ->push(['errors' => [['message' => 'username already exists']]], 400)
                ->push(['user_id' => 555, 'username' => 'socialeaz_user_1'], 200),
            'api.sendgrid.com/v3/api_keys' => Http::response(['api_key' => 'SG.subuser-key', 'api_key_id' => 'abc123'], 200),
        ]);

        $user = User::factory()->create();
        $service = app(SendGridSubaccountService::class);

        $result = $service->provision($user);
        $this->assertFalse($result['success']);
        $this->assertEquals('failed', EmailSubaccount::where('user_id', $user->id)->first()->status);

        $retry = $service->provision($user);
        $this->assertTrue($retry['success']);
        $this->assertEquals(1, EmailSubaccount::where('user_id', $user->id)->count());
    }
}
