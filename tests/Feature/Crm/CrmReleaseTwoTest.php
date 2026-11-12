<?php

namespace Tests\Feature\Crm;

use App\Models\Bundle;
use App\Models\Crm\Activity;
use App\Models\Crm\Automation;
use App\Models\Crm\AutomationRun;
use App\Models\Crm\Contact;
use App\Models\Crm\Deal;
use App\Models\Crm\Lead;
use App\Models\SellerTeamMember;
use App\Models\SocialAccount;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Crm\AutomationEngine;
use App\Services\Crm\BuyingIntent;
use App\Services\Crm\CrmSettings;
use App\Services\Crm\LeadCapture;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Crm\Concerns\CrmFixtures;
use Tests\TestCase;

/** CRM Release 2: automations, visibility, limits, capture, import/merge, segments, reports, settings. */
class CrmReleaseTwoTest extends TestCase
{
    use CrmFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCrm();
    }

    private function teammate(User $workspace, string $crm = 'write', string $role = 'member'): array
    {
        $person = User::factory()->create();
        $member = SellerTeamMember::create(['owner_id' => $workspace->id, 'user_id' => $person->id, 'email' => $person->email, 'role' => $role,
            'accepted_at' => now(), 'permissions' => ['crm' => $crm]]);

        return [$person, $member];
    }

    public function test_new_lead_automation_assigns_round_robin_and_creates_a_task_only_when_conditions_match(): void
    {
        [$sara] = $this->teammate($this->rawda);
        $this->actingAs($this->rawda)->postJson($this->url('automations.store'), [
            'name' => 'Instagram leads', 'trigger' => 'lead_created', 'is_active' => true,
            'conditions' => [['field' => 'lead.source', 'operator' => 'equals', 'value' => 'instagram']],
            'actions' => [
                ['type' => 'assign_owner', 'mode' => 'round_robin', 'member_ids' => [$sara->id]],
                ['type' => 'create_task', 'subject' => 'Call {name}', 'due_minutes' => 15, 'assignee' => 'owner'],
                ['type' => 'add_tag', 'tag' => 'ig-lead'],
            ],
        ])->assertCreated();

        $this->postJson($this->url('leads.store'), ['name' => 'Noura Q', 'phone' => '0552148830', 'source' => 'instagram'])->assertCreated();
        $this->postJson($this->url('leads.store'), ['name' => 'Walk In', 'phone' => '0551112222', 'source' => 'walk_in'])->assertCreated();

        $ig = Lead::withoutGlobalScopes()->where('source', 'instagram')->firstOrFail();
        $walk = Lead::withoutGlobalScopes()->where('source', 'walk_in')->firstOrFail();
        $this->assertSame($sara->id, $ig->owner_id);
        $this->assertNotSame($sara->id, $walk->owner_id, 'condition kept the walk-in lead out');
        $task = Activity::withoutGlobalScopes()->where('type', 'task')->where('lead_id', $ig->id)->where('subject', 'Call Noura Q')->firstOrFail();
        $this->assertSame($sara->id, $task->owner_id);
        $this->assertSame(['ig-lead'], $ig->contact->tags);
        $this->assertSame(1, AutomationRun::withoutGlobalScopes()->where('status', 'done')->count());

        // Test run over the last 30 days counts the one matching lead, and changes nothing.
        $this->postJson($this->url('automations.preview'), ['trigger' => 'lead_created', 'conditions' => [['field' => 'lead.source', 'operator' => 'equals', 'value' => 'instagram']],
            'actions' => [['type' => 'add_tag', 'tag' => 'x']]])->assertOk()->assertJsonPath('matches', 1);
        // Another workspace never sees or triggers it.
        $this->actingAs($this->hala)->getJson($this->url('automations.index'))->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_idle_deal_automation_runs_once_per_idle_period(): void
    {
        $this->actingAs($this->rawda)->getJson($this->url('deals.index'))->assertOk();
        $stage = \App\Models\Crm\Stage::withoutGlobalScopes()->where('workspace_id', $this->rawda->id)->where('kind', 'open')->first();
        $deal = Deal::withoutGlobalScopes()->create(['workspace_id' => $this->rawda->id, 'pipeline_id' => $stage->pipeline_id, 'stage_id' => $stage->id, 'name' => 'Sofa',
            'value' => 100, 'status' => 'open', 'owner_id' => $this->rawda->id, 'stage_changed_at' => now()->subDays(10)]);
        Automation::withoutGlobalScopes()->create(['workspace_id' => $this->rawda->id, 'name' => 'Idle', 'trigger' => 'deal_idle', 'trigger_config' => ['days' => 7],
            'actions' => [['type' => 'create_task', 'subject' => 'Follow up on {deal}', 'due_minutes' => 60, 'assignee' => 'owner']], 'is_active' => true]);
        auth()->logout();

        $this->assertSame(1, app(AutomationEngine::class)->runIdle());
        $this->assertSame(0, app(AutomationEngine::class)->runIdle(), 'same idle period, no second task');
        $this->assertSame(1, Activity::withoutGlobalScopes()->where('deal_id', $deal->id)->where('subject', 'Follow up on Sofa')->count());
    }

    public function test_teammates_limited_to_own_records_see_only_theirs(): void
    {
        [$sara, $member] = $this->teammate($this->rawda);
        [$omar, $admin] = $this->teammate($this->rawda, 'write', 'admin');
        $mine = Contact::withoutGlobalScopes()->create(['workspace_id' => $this->rawda->id, 'first_name' => 'Mine', 'owner_id' => $sara->id]);
        $other = Contact::withoutGlobalScopes()->create(['workspace_id' => $this->rawda->id, 'first_name' => 'Other', 'owner_id' => $this->rawda->id]);
        CrmSettings::save($this->rawda->id, ['member_visibility' => 'own']);

        $this->actingAs($sara)->withSession(['seller_workspace_member' => $member->id]);
        $this->getJson($this->url('contacts.index'))->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.first_name', 'Mine');
        $this->getJson($this->url('contacts.show', $other->id))->assertNotFound();
        $this->getJson($this->url('contacts.show', $mine->id))->assertOk();
        // Duplicate checks still see the whole workspace, so no second record gets created.
        $other->update(['phone' => '0552148830']);
        $this->postJson($this->url('contacts.store'), ['first_name' => 'Dup', 'phone' => '+966552148830'])->assertStatus(409);

        // An Admin always sees everything; a per-member override wins over the default.
        $this->flushSession();
        $this->actingAs($omar)->withSession(['seller_workspace_member' => $admin->id])->getJson($this->url('contacts.index'))->assertJsonCount(2, 'data');
        CrmSettings::save($this->rawda->id, ['member_overrides' => [$member->id => 'all']]);
        $this->flushSession();
        $this->actingAs($sara)->withSession(['seller_workspace_member' => $member->id])->getJson($this->url('contacts.index'))->assertJsonCount(2, 'data');
    }

    public function test_plan_contact_limit_is_enforced_across_the_organization(): void
    {
        $bundle = Bundle::find(Subscription::where('user_id', $this->rawda->id)->value('bundle_id'));
        $bundle->update(['entitlement_settings' => array_merge($bundle->entitlement_settings, ['crm_contacts' => 1])]);

        $this->actingAs($this->rawda)->postJson($this->url('contacts.store'), ['first_name' => 'One'])->assertCreated();
        $this->postJson($this->url('contacts.store'), ['first_name' => 'Two'])->assertUnprocessable()->assertJsonValidationErrors('contacts');
        $this->getJson($this->url('settings.show'))->assertOk()->assertJsonPath('limits.limit', 1)->assertJsonPath('limits.used', 1);
    }

    public function test_meta_lead_ads_and_website_forms_become_leads_once(): void
    {
        SocialAccount::create(['user_id' => $this->rawda->id, 'platform' => 'facebook', 'platform_account_id' => 'page-1', 'name' => 'Rawda', 'access_token' => 'page-token']);
        Http::fake(['graph.facebook.com/*' => Http::response([
            'field_data' => [['name' => 'full_name', 'values' => ['Yousef Al-Shammari']], ['name' => 'phone_number', 'values' => ['+966502149907']], ['name' => 'room_type', 'values' => ['Majlis']]],
            'campaign_name' => 'Ramadan Majlis', 'form_id' => 'f1', 'ad_id' => 'a1', 'platform' => 'ig',
        ])]);
        $payload = ['object' => 'page', 'entry' => [['changes' => [['field' => 'leadgen', 'value' => ['leadgen_id' => 'L1', 'page_id' => 'page-1']]]]]];

        $this->assertSame(1, app(LeadCapture::class)->handleMetaWebhook($payload));
        $this->assertSame(0, app(LeadCapture::class)->handleMetaWebhook($payload), 'Meta retries are ignored');
        $lead = Lead::withoutGlobalScopes()->firstOrFail();
        $this->assertSame([$this->rawda->id, 'instagram', 'Ramadan Majlis', null], [$lead->workspace_id, $lead->source, $lead->campaign, $lead->owner_id]);
        $this->assertStringContainsString('room type: Majlis', $lead->interest);
        $this->assertSame('Yousef', $lead->contact->first_name);

        // Website form: the workspace's token; same phone → same person; repeated submission id → same lead.
        $url = $this->actingAs($this->rawda)->getJson($this->url('settings.show'))->json('capture.url');
        auth()->logout();
        $this->postJson($url, ['name' => 'Yousef S', 'phone' => '0502149907', 'message' => 'Outdoor set', 'submission_id' => 's-1'])->assertCreated();
        $this->postJson($url, ['name' => 'Yousef S', 'phone' => '0502149907', 'submission_id' => 's-1'])->assertCreated();
        $this->assertSame(2, Lead::withoutGlobalScopes()->count());
        $this->assertSame(1, Contact::withoutGlobalScopes()->count());
        $this->postJson(route('api.crm.capture', str_repeat('a', 48)), ['name' => 'X', 'phone' => '0500000000'])->assertNotFound();
    }

    public function test_csv_import_maps_columns_and_handles_duplicates_then_merge_keeps_history(): void
    {
        Storage::fake('local');
        $this->actingAs($this->rawda)->postJson($this->url('contacts.store'), ['first_name' => 'Noura', 'phone' => '0552148830'])->assertCreated();
        $csv = "Name,Mobile,Email,Company\nNoura Al-Qahtani,+966 55 214 8830,noura@x.test,\nKhalid Al-Mutairi,0507781192,k@x.test,Al Faisaliah Hotel\n,,,\n";
        $preview = $this->postJson($this->url('contacts.import.preview'), ['file' => UploadedFile::fake()->createWithContent('c.csv', $csv)])->assertOk()
            ->assertJsonPath('rows', 3)->assertJsonPath('mapping.0', 'full_name')->assertJsonPath('mapping.1', 'phone')->json();

        $this->postJson($this->url('contacts.import.run'), ['token' => $preview['token'], 'mapping' => ['full_name', 'phone', 'email', 'company'], 'duplicates' => 'update'])
            ->assertOk()->assertJson(['created' => 1, 'updated' => 1, 'skipped' => 1]);
        $noura = Contact::withoutGlobalScopes()->where('first_name', 'Noura')->firstOrFail();
        $this->assertSame('noura@x.test', $noura->email, 'empty field filled');
        $this->assertSame('Al Faisaliah Hotel', Contact::withoutGlobalScopes()->where('first_name', 'Khalid')->first()->company->name);

        // A forced duplicate, with a deal, merged into the original.
        $dupId = $this->postJson($this->url('contacts.store'), ['first_name' => 'N. Qahtani', 'phone' => '00966552148830', 'force' => true])->json('data.id');
        $this->getJson($this->url('deals.index'));
        $stage = \App\Models\Crm\Stage::withoutGlobalScopes()->where('workspace_id', $this->rawda->id)->first();
        $deal = $this->postJson($this->url('deals.store'), ['name' => 'Sofa', 'value' => 10, 'pipeline_id' => $stage->pipeline_id, 'stage_id' => $stage->id, 'contact_id' => $dupId])->json('data.id');
        $groups = $this->getJson($this->url('contacts.duplicate-groups'))->assertOk()->assertJsonCount(1, 'data');
        $this->assertContains('N. Qahtani', array_column($groups->json('data.0.contacts'), 'name'), 'the merge dialog shows names');
        $this->assertSame(1, collect($groups->json('data.0.contacts'))->firstWhere('name', 'N. Qahtani')['deals_count']);
        $this->postJson($this->url('contacts.merge'), ['primary_id' => $noura->id, 'ids' => [$dupId]])->assertOk();
        $this->assertSame($noura->id, Deal::withoutGlobalScopes()->find($deal)->contact_id);
        $this->assertSoftDeleted('crm_contacts', ['id' => $dupId]);
        // Another workspace can't merge into this one.
        $this->actingAs($this->hala)->postJson($this->url('contacts.merge'), ['primary_id' => $noura->id, 'ids' => [999]])->assertNotFound();
    }

    public function test_segment_goes_to_an_email_marketing_list_and_custom_fields_are_validated(): void
    {
        $this->actingAs($this->rawda)->postJson($this->url('contacts.store'), ['first_name' => 'A', 'email' => 'a@x.test', 'tags' => ['vip']])->assertCreated();
        $this->postJson($this->url('contacts.store'), ['first_name' => 'B', 'email' => 'b@x.test'])->assertCreated();
        $segment = $this->postJson($this->url('segments.store'), ['name' => 'VIP', 'filters' => ['tag' => 'vip']])->assertCreated()->json('data.id');
        $this->getJson($this->url('contacts.index', ['segment_id' => $segment]))->assertJsonCount(1, 'data');
        $this->postJson($this->url('segments.email-list', $segment), ['new_list' => 'VIP buyers'])->assertOk()->assertJson(['added' => 1, 'skipped' => 0]);
        $this->assertTrue(\App\Models\EmailMarketing\EmailSubscriber::where('user_id', $this->rawda->id)->where('email', 'a@x.test')->exists());

        $this->postJson($this->url('settings.fields.store'), ['label' => 'Room type', 'type' => 'dropdown', 'options' => ['Majlis', 'Bedroom']])->assertCreated();
        $this->postJson($this->url('contacts.store'), ['first_name' => 'C', 'custom_fields' => ['room_type' => 'Kitchen']])->assertUnprocessable();
        $id = $this->postJson($this->url('contacts.store'), ['first_name' => 'C', 'custom_fields' => ['room_type' => 'Majlis', 'not_defined' => 'x']])->assertCreated()->json('data.id');
        $this->assertSame(['room_type' => 'Majlis'], Contact::withoutGlobalScopes()->find($id)->custom_fields);
        $this->getJson($this->url('contacts.show', $id))->assertJsonPath('fields.0.key', 'room_type');
    }

    public function test_reports_and_lost_reason_rule(): void
    {
        $this->actingAs($this->rawda)->getJson($this->url('deals.index'));
        $stages = \App\Models\Crm\Stage::withoutGlobalScopes()->where('workspace_id', $this->rawda->id)->get()->keyBy('kind');
        $deal = $this->postJson($this->url('deals.store'), ['name' => 'Sofa', 'value' => 1200, 'pipeline_id' => $stages['open']->pipeline_id, 'stage_id' => $stages['open']->id])->json('data.id');
        $this->postJson($this->url('deals.move', $deal), ['stage_id' => $stages['won']->id])->assertOk();

        $this->getJson($this->url('reports.sales', ['months' => 3]))->assertOk()->assertJsonPath('kpis.revenue.value', 1200)->assertJsonPath('kpis.won.value', 1)
            ->assertJsonStructure(['by_month', 'stages', 'owners', 'lost_reasons']);
        $this->getJson($this->url('reports.sources', ['days' => 90]))->assertOk()->assertJsonStructure(['by_source', 'by_campaign', 'totals']);

        CrmSettings::save($this->rawda->id, ['require_lost_reason' => true]);
        $other = $this->postJson($this->url('deals.store'), ['name' => 'Rug', 'value' => 5, 'pipeline_id' => $stages['open']->pipeline_id, 'stage_id' => $stages['open']->id])->json('data.id');
        $this->postJson($this->url('deals.move', $other), ['stage_id' => $stages['lost']->id])->assertStatus(422);
        $this->postJson($this->url('deals.move', $other), ['stage_id' => $stages['lost']->id, 'lost_reason' => 'Price'])->assertOk();
    }

    public function test_buying_intent_detects_english_and_arabic(): void
    {
        foreach (['Price please 🙏', 'and do you deliver to Khobar?', 'بكم هذا؟', 'هل متوفر باللون الأبيض', 'كم السعر'] as $text) {
            $this->assertTrue(BuyingIntent::detect($text), $text);
        }
        foreach (['🔥🔥🔥', 'Beautiful!', 'ما شاء الله'] as $text) {
            $this->assertFalse(BuyingIntent::detect($text), $text);
        }
    }
}
