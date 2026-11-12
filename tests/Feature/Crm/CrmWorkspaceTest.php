<?php

namespace Tests\Feature\Crm;

use App\Models\Bundle;
use App\Models\Crm\Activity;
use App\Models\Crm\Contact;
use App\Models\Crm\ContactIdentity;
use App\Models\Crm\Deal;
use App\Models\Crm\Lead;
use App\Models\Messaging\Conversation;
use App\Models\SellerTeamMember;
use App\Models\SocialAccount;
use App\Models\User;
use Tests\Feature\Crm\Concerns\CrmFixtures;
use Tests\TestCase;

/**
 * CRM Release 1: workspace isolation, one-person-one-record, lead → deal,
 * Inbox linking, and access through the existing plan + team permissions.
 */
class CrmWorkspaceTest extends TestCase
{
    use CrmFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCrm();
    }

    public function test_records_are_isolated_per_workspace(): void
    {
        $id = $this->actingAs($this->rawda)->postJson($this->url('contacts.store'), ['first_name' => 'Noura', 'phone' => '+966 55 214 8830'])
            ->assertCreated()->json('data.id');

        $this->assertSame($this->rawda->id, Contact::withoutGlobalScopes()->find($id)->workspace_id);

        $this->actingAs($this->hala)->getJson($this->url('contacts.index'))->assertOk()->assertJsonCount(0, 'data');
        $this->actingAs($this->hala)->getJson($this->url('contacts.show', $id))->assertNotFound();
        $this->actingAs($this->hala)->putJson($this->url('contacts.update', $id), ['first_name' => 'X'])->assertNotFound();
        $this->actingAs($this->hala)->deleteJson($this->url('contacts.destroy', $id))->assertNotFound();
        $this->actingAs($this->hala)->getJson($this->url('search', ['q' => 'Noura']))->assertOk()->assertJsonCount(0, 'contacts');
        // Another workspace's ids are rejected inside payloads too.
        $this->actingAs($this->hala)->postJson($this->url('leads.store'), ['contact_id' => $id])->assertUnprocessable();

        $this->assertSame('Noura', Contact::withoutGlobalScopes()->find($id)->first_name);
    }

    public function test_phone_in_any_format_is_one_person(): void
    {
        $this->actingAs($this->rawda)->postJson($this->url('contacts.store'), ['first_name' => 'Noura', 'phone' => '+966 55 214 8830'])->assertCreated();

        $this->actingAs($this->rawda)->postJson($this->url('contacts.store'), ['first_name' => 'Noura Q', 'phone' => '0552148830'])
            ->assertStatus(409)->assertJsonCount(1, 'duplicates');
        $this->actingAs($this->rawda)->getJson($this->url('contacts.duplicates', ['phone' => '00966552148830']))->assertJsonCount(1, 'data');

        // The same number in another workspace is a different customer.
        $this->actingAs($this->hala)->postJson($this->url('contacts.store'), ['first_name' => 'Noura', 'phone' => '0552148830'])->assertCreated();
        // An explicit "create anyway" is the user's decision.
        $this->actingAs($this->rawda)->postJson($this->url('contacts.store'), ['first_name' => 'Noura Q', 'phone' => '0552148830', 'force' => true])->assertCreated();
    }

    public function test_lead_reuses_the_person_and_converts_to_a_deal(): void
    {
        $contactId = $this->actingAs($this->rawda)->postJson($this->url('contacts.store'), ['first_name' => 'Dana', 'phone' => '0559031187'])->json('data.id');

        $leadId = $this->actingAs($this->rawda)->postJson($this->url('leads.store'), [
            'name' => 'Dana Al-Subaie', 'phone' => '+966 55 903 1187', 'source' => 'instagram', 'estimated_value' => 11900, 'follow_up' => true,
        ])->assertCreated()->json('data.id');

        $lead = Lead::withoutGlobalScopes()->find($leadId);
        $this->assertSame($contactId, $lead->contact_id, 'matched on phone, no second contact');
        $this->assertSame(1, Contact::withoutGlobalScopes()->count());
        $this->assertSame(1, Activity::withoutGlobalScopes()->where('type', 'task')->where('lead_id', $leadId)->count());

        $this->actingAs($this->rawda)->getJson($this->url('deals.index'))->assertOk(); // creates the default pipeline
        $stage = \App\Models\Crm\Stage::withoutGlobalScopes()->where('workspace_id', $this->rawda->id)->where('name', 'Qualified')->firstOrFail();

        $this->actingAs($this->rawda)->postJson($this->url('leads.convert', $leadId), [
            'create_deal' => true, 'deal_name' => 'Grey L-shape majlis', 'value' => 11900, 'stage_id' => $stage->id, 'company_name' => 'Subaie Home',
        ])->assertOk()->assertJsonPath('data.status', 'converted');

        $deal = Deal::withoutGlobalScopes()->firstOrFail();
        $this->assertSame([$contactId, $leadId, 30, 'open'], [$deal->contact_id, $deal->lead_id, $deal->probability, $deal->status]);
        $this->assertSame('opportunity', Contact::withoutGlobalScopes()->find($contactId)->lifecycle);
        $this->assertNotNull(Contact::withoutGlobalScopes()->find($contactId)->company_id);
        $this->actingAs($this->rawda)->postJson($this->url('leads.convert', $leadId), ['create_deal' => false])->assertStatus(422);

        // Winning the deal makes the person a customer and is on the record's history.
        $won = \App\Models\Crm\Stage::withoutGlobalScopes()->where('workspace_id', $this->rawda->id)->where('kind', 'won')->firstOrFail();
        $this->actingAs($this->rawda)->postJson($this->url('deals.move', $deal->id), ['stage_id' => $won->id])->assertOk()->assertJsonPath('data.status', 'won');
        $this->assertSame('customer', Contact::withoutGlobalScopes()->find($contactId)->lifecycle);
        $this->assertTrue(Activity::withoutGlobalScopes()->where('type', 'event')->where('subject', 'deal_won')->where('deal_id', $deal->id)->exists());

        // Another workspace can neither see nor move it.
        $this->actingAs($this->hala)->postJson($this->url('deals.move', $deal->id), ['stage_id' => $won->id])->assertNotFound();
    }

    public function test_inbox_conversation_links_and_future_chats_follow(): void
    {
        $account = SocialAccount::create(['user_id' => $this->rawda->id, 'platform' => 'whatsapp', 'platform_account_id' => 'wa-1', 'name' => 'Rawda WA']);
        $first = Conversation::create(['social_account_id' => $account->id, 'platform' => 'whatsapp', 'customer_external_id' => '966553184410', 'customer_name' => 'Yousef']);

        $this->actingAs($this->hala)->getJson($this->url('inbox.show', $first->id))->assertNotFound();
        $this->actingAs($this->rawda)->getJson($this->url('inbox.show', $first->id))->assertOk()->assertJsonPath('linked', false);

        $this->actingAs($this->rawda)->postJson($this->url('inbox.link', $first->id), ['first_name' => 'Yousef', 'as_lead' => true, 'remember' => true])
            ->assertCreated()->assertJsonPath('linked', true)->assertJsonPath('contact.phone', '966553184410');

        $contactId = $first->fresh()->crm_contact_id;
        $this->assertNotNull($contactId);
        $this->assertTrue(ContactIdentity::withoutGlobalScopes()->where('external_id', '966553184410')->exists());
        $this->assertSame(1, Lead::withoutGlobalScopes()->where('contact_id', $contactId)->count());

        // A new chat from the same number links itself as it arrives (no request, no auth).
        auth()->logout();
        // Same customer writing to the workspace's second WhatsApp number.
        $other = SocialAccount::create(['user_id' => $this->rawda->id, 'platform' => 'whatsapp', 'platform_account_id' => 'wa-2', 'name' => 'Rawda WA 2']);
        $second = Conversation::create(['social_account_id' => $other->id, 'platform' => 'whatsapp', 'customer_external_id' => '966553184410', 'external_conversation_id' => 'c2']);
        $this->assertSame($contactId, $second->fresh()->crm_contact_id);

        // The profile shows its conversations; Hala never can.
        $this->actingAs($this->rawda)->getJson($this->url('contacts.show', $contactId))->assertOk()->assertJsonCount(2, 'conversations');
    }

    public function test_access_follows_the_plan_and_the_team_permission(): void
    {
        // Plan without CRM.
        $bundle = Bundle::find(\App\Models\Subscription::where('user_id', $this->hala->id)->value('bundle_id'));
        $bundle->update(['access_settings' => array_merge($bundle->access_settings, ['crm' => ['mode' => 'disabled', 'tier' => 'none']])]);
        $this->actingAs($this->hala)->getJson($this->url('contacts.index'))->assertForbidden()->assertJsonPath('code', 'package_feature_denied');

        // Teammate with CRM read only, inside Rawda's workspace.
        $sara = User::factory()->create(['name' => 'Sara']);
        $member = SellerTeamMember::create(['owner_id' => $this->rawda->id, 'user_id' => $sara->id, 'email' => $sara->email, 'role' => 'member',
            'accepted_at' => now(), 'permissions' => ['crm' => 'read']]);
        $this->actingAs($sara)->withSession(['seller_workspace_member' => $member->id]);
        $this->getJson($this->url('contacts.index'))->assertOk();
        $this->postJson($this->url('contacts.store'), ['first_name' => 'Blocked'])->assertForbidden();

        $member->update(['permissions' => ['crm' => 'write']]);
        $this->postJson($this->url('contacts.store'), ['first_name' => 'Allowed'])->assertCreated();
        $contact = Contact::withoutGlobalScopes()->where('first_name', 'Allowed')->firstOrFail();
        $this->assertSame([$this->rawda->id, $sara->id], [$contact->workspace_id, $contact->created_by], 'stored in the workspace, authored by the person');
        // Records can be assigned to her: owners are workspace members with CRM access.
        $this->putJson($this->url('contacts.update', $contact->id), ['owner_id' => $sara->id])->assertOk()->assertJsonPath('data.owner.id', $sara->id);
        $member->update(['permissions' => ['crm' => 'none']]);
        $this->getJson($this->url('contacts.index'))->assertForbidden();
    }

    public function test_every_crm_page_renders_in_both_languages(): void
    {
        $contact = $this->actingAs($this->rawda)->postJson($this->url('contacts.store'), ['first_name' => 'Noura'])->json('data.id');
        $company = $this->actingAs($this->rawda)->postJson($this->url('companies.store'), ['name' => 'Hala Coffee'])->json('data.id');
        $pages = ['overview' => [], 'contacts.index' => [], 'contacts.show' => $contact, 'companies.index' => [], 'companies.show' => $company,
            'leads.index' => [], 'deals.index' => [], 'activities.index' => [], 'settings.index' => []];

        foreach (['en', 'ar'] as $locale) {
            app()->setLocale($locale);
            foreach ($pages as $name => $params) {
                $this->actingAs($this->rawda)->get(route('admin.crm.'.$name, $params))->assertOk()->assertSee('<crm-app', false);
            }
        }
        // API data for the overview and the board.
        $this->getJson($this->url('overview'))->assertOk()->assertJsonStructure(['kpis', 'performance', 'funnel', 'sources', 'tasks', 'recent', 'attention']);
        $this->getJson($this->url('deals.index'))->assertOk()->assertJsonCount(6, 'pipeline.stages');
        // Another workspace's profile page is a 404, not an empty shell.
        $this->actingAs($this->hala)->get(route('admin.crm.contacts.show', $contact))->assertNotFound();
    }
}
