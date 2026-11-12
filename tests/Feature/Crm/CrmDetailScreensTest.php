<?php

namespace Tests\Feature\Crm;

use App\Models\Crm\Activity;
use App\Models\Crm\Contact;
use App\Models\Crm\Deal;
use App\Models\Crm\Lead;
use App\Models\Crm\Stage;
use App\Services\Crm\CrmSettings;
use Tests\Feature\Crm\Concerns\CrmFixtures;
use Tests\TestCase;

/** Lead profile (L-02), deal detail and won/lost (D-05/07/08), task priority (A-01/A-02), scoring (S-06), hot tab. */
class CrmDetailScreensTest extends TestCase
{
    use CrmFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCrm();
    }

    private function lead(array $data = []): Lead
    {
        $id = $this->actingAs($this->rawda)->postJson($this->url('leads.store'), $data + ['name' => 'Noura Q', 'phone' => '0552148830', 'source' => 'whatsapp'])
            ->assertCreated()->json('data.id');

        return Lead::withoutGlobalScopes()->findOrFail($id);
    }

    private function deal(?int $contactId = null): array
    {
        $this->actingAs($this->rawda)->getJson($this->url('pipelines.index'))->assertOk();
        $open = Stage::withoutGlobalScopes()->where('workspace_id', $this->rawda->id)->where('kind', 'open')->orderBy('sort_order')->firstOrFail();
        $id = $this->postJson($this->url('deals.store'), ['name' => 'Sofa set', 'value' => 9000, 'pipeline_id' => $open->pipeline_id, 'stage_id' => $open->id,
            'contact_id' => $contactId, 'source' => 'instagram', 'notes' => 'Wants grey fabric'])->assertCreated()->json('data.id');

        return [$id, $open];
    }

    public function test_lead_profile_returns_qualification_score_and_is_private_to_the_workspace(): void
    {
        $lead = $this->lead(['estimated_value' => 15000]);
        $res = $this->getJson($this->url('leads.show', $lead->id))->assertOk();
        $res->assertJsonPath('lead.qualification.budget.met', false)->assertJsonStructure(['lead' => ['score', 'band', 'score_breakdown'], 'statuses', 'tasks', 'timeline']);
        $this->assertGreaterThanOrEqual(30, $res->json('lead.score')); // value ≥ 10k (20) + WhatsApp source (10)
        $this->assertContains('lead.estimated_value', array_column($res->json('lead.score_breakdown'), 'field'));

        $this->patchJson($this->url('leads.update', $lead->id), ['qualification' => ['budget' => ['met' => true, 'note' => '15k'], 'bogus' => ['met' => true]]])->assertOk();
        $q = $lead->fresh()->qualification;
        $this->assertSame(['met' => true, 'note' => '15k', 'by' => 'manual'], $q['budget']);
        $this->assertArrayNotHasKey('bogus', $q);

        // Custom statuses from settings are accepted; unknown ones are not.
        CrmSettings::save($this->rawda->id, ['extra_statuses' => ['visit_booked']]);
        $this->patchJson($this->url('leads.update', $lead->id), ['status' => 'visit_booked'])->assertOk();
        $this->patchJson($this->url('leads.update', $lead->id), ['status' => 'whatever'])->assertStatus(422);

        $this->actingAs($this->hala)->getJson($this->url('leads.show', $lead->id))->assertNotFound();
        $this->actingAs($this->rawda)->get(route('admin.crm.leads.show', $lead->id))->assertOk();
    }

    public function test_won_modal_details_are_saved_and_create_the_onboarding_task(): void
    {
        $contact = Contact::withoutGlobalScopes()->findOrFail($this->lead()->contact_id);
        [$dealId] = $this->deal($contact->id);
        $this->getJson($this->url('deals.show', $dealId))->assertOk()->assertJsonPath('deal.notes', 'Wants grey fabric')->assertJsonPath('deal.source', 'instagram')
            ->assertJsonStructure(['deal' => ['stage_days', 'avg_stage_days', 'weighted'], 'stages', 'people', 'timeline']);

        $won = Stage::withoutGlobalScopes()->where('workspace_id', $this->rawda->id)->where('kind', 'won')->firstOrFail();
        $this->postJson($this->url('deals.move', $dealId), ['stage_id' => $won->id, 'value' => 8500, 'won_at' => '2026-11-10',
            'win_reasons' => ['price', 'delivery'], 'mark_customer' => false, 'onboarding_task' => 'Deliver sofa'])->assertOk()->assertJsonPath('data.status', 'won');

        $deal = Deal::withoutGlobalScopes()->findOrFail($dealId);
        $this->assertSame(8500.0, (float) $deal->value);
        $this->assertSame('2026-11-10', $deal->won_at->toDateString());
        $this->assertSame(['price', 'delivery'], $deal->win_reasons);
        $this->assertNotSame('customer', $contact->fresh()->lifecycle);
        $this->assertTrue(Activity::withoutGlobalScopes()->where('deal_id', $dealId)->where('type', 'task')->where('subject', 'Deliver sofa')->exists());
        $this->actingAs($this->hala)->getJson($this->url('deals.show', $dealId))->assertNotFound();
    }

    public function test_lost_modal_requires_a_reason_when_configured_and_schedules_reengagement(): void
    {
        [$dealId] = $this->deal();
        $lost = Stage::withoutGlobalScopes()->where('workspace_id', $this->rawda->id)->where('kind', 'lost')->firstOrFail();
        CrmSettings::save($this->rawda->id, ['require_lost_reason' => true]);
        $this->postJson($this->url('deals.move', $dealId), ['stage_id' => $lost->id])->assertStatus(422);

        $this->postJson($this->url('deals.move', $dealId), ['stage_id' => $lost->id, 'lost_reason' => 'Price too high', 'competitor' => 'IKEA',
            'notes' => 'Will revisit after Ramadan', 'reengage_days' => 90])->assertOk()->assertJsonPath('data.status', 'lost');

        $deal = Deal::withoutGlobalScopes()->findOrFail($dealId);
        $this->assertSame(['Price too high', 'IKEA'], [$deal->lost_reason, $deal->competitor]);
        $this->assertStringContainsString('Ramadan', $deal->notes);
        $task = Activity::withoutGlobalScopes()->where('deal_id', $dealId)->where('type', 'task')->firstOrFail();
        $this->assertSame('low', $task->priority);
        $this->assertTrue($task->due_at->between(now()->addDays(89), now()->addDays(91)));
    }

    public function test_logging_a_call_with_outcome_follow_up_and_stage_move(): void
    {
        $contactId = $this->lead()->contact_id;
        [$dealId, $open] = $this->deal($contactId);
        $next = Stage::withoutGlobalScopes()->where('pipeline_id', $open->pipeline_id)->where('kind', 'open')->where('sort_order', '>', $open->sort_order)->orderBy('sort_order')->firstOrFail();

        $this->postJson($this->url('contacts.log', $contactId), ['type' => 'call', 'subject' => 'Discovery call', 'outcome' => 'interested', 'duration_minutes' => 12,
            'deal_id' => $dealId, 'follow_up' => ['type' => 'task', 'subject' => 'Send quote', 'due_at' => now()->addDay()->toIso8601String(), 'remind_minutes' => 60],
            'move_stage_id' => $next->id])->assertCreated()->assertJsonPath('data.outcome', 'interested');

        $this->assertSame($next->id, Deal::withoutGlobalScopes()->find($dealId)->stage_id);
        $follow = Activity::withoutGlobalScopes()->where('subject', 'Send quote')->firstOrFail();
        $this->assertSame($dealId, $follow->deal_id);
        $this->assertNotNull($follow->remind_at);

        $this->postJson($this->url('activities.store'), ['type' => 'task', 'subject' => 'Urgent', 'priority' => 'high'])->assertCreated();
        $this->getJson($this->url('activities.index', ['priority' => 'high']))->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.subject', 'Urgent');
    }

    public function test_hot_tab_and_band_filter_follow_the_scoring_bands(): void
    {
        CrmSettings::save($this->rawda->id, ['scoring' => ['bands' => ['hot' => 25, 'warm' => 10]]]);
        $hot = $this->lead(['estimated_value' => 20000]); // 20 + 10 (WhatsApp) = 30
        $this->lead(['name' => 'Cold One', 'phone' => '0559998877', 'source' => 'walk_in']);

        $this->getJson($this->url('leads.index', ['band' => 'hot']))->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $hot->id);
        $res = $this->getJson($this->url('contacts.index', ['tab' => 'hot']))->assertOk();
        $this->assertSame([$hot->contact_id], array_column($res->json('data'), 'id'));
        $this->getJson($this->url('leads.index'))->assertOk()->assertJsonStructure(['kpis' => ['today', 'yesterday', 'avg_first_response', 'unassigned', 'qualified_rate']]);
    }

    public function test_lists_and_scoring_settings_are_validated_and_applied(): void
    {
        $this->actingAs($this->rawda);
        $this->putJson($this->url('settings.update'), ['extra_statuses' => ['qualified']])->assertStatus(422);
        $this->putJson($this->url('settings.update'), ['sources' => []])->assertStatus(422);
        $this->putJson($this->url('settings.update'), ['extra_statuses' => ['Visit booked'], 'lost_reasons' => ['Too far', 'price'], 'sources' => ['whatsapp', 'Exhibition']])->assertOk();
        $this->assertSame(['Too far', 'price'], CrmSettings::for($this->rawda->id)['lost_reasons']);

        // Scoring: bands must be ordered; unknown fields and extra keys are rejected or dropped.
        $rule = ['group' => 'intent', 'field' => 'lead.source', 'operator' => 'equals', 'value' => 'Exhibition', 'points' => 60, 'on' => true, 'evil' => 'x'];
        $this->putJson($this->url('settings.update'), ['scoring' => ['enabled' => true, 'bands' => ['hot' => 40, 'warm' => 50], 'rules' => [$rule]]])->assertStatus(422);
        $this->putJson($this->url('settings.update'), ['scoring' => ['enabled' => true, 'bands' => ['hot' => 50, 'warm' => 20], 'rules' => [['field' => 'users.password'] + $rule]]])->assertStatus(422);
        $this->putJson($this->url('settings.update'), ['scoring' => ['enabled' => true, 'intent_signal' => false, 'bands' => ['hot' => 50, 'warm' => 20], 'rules' => [$rule]]])->assertOk();
        $saved = CrmSettings::for($this->rawda->id)['scoring'];
        $this->assertArrayNotHasKey('evil', $saved['rules'][0]);

        $lead = $this->lead(['source' => 'Exhibition', 'name' => 'Expo Visitor', 'phone' => '0553334444']);
        $this->assertSame(60, $lead->fresh()->score);
        $this->getJson($this->url('leads.index', ['band' => 'hot']))->assertJsonCount(1, 'data');

        // Changing rules and recalculating updates stored scores; the back-test reports per band.
        $this->putJson($this->url('settings.update'), ['scoring' => ['enabled' => true, 'bands' => ['hot' => 50, 'warm' => 20], 'rules' => [array_merge($rule, ['points' => 25])]]])->assertOk();
        $res = $this->postJson($this->url('settings.rescore'))->assertOk();
        $this->assertGreaterThanOrEqual(1, $res->json('rescored'));
        $this->assertSame(25, $lead->fresh()->score);
        $this->assertSame(1, $res->json('backtest.warm.leads'));
        $this->getJson($this->url('settings.scoring'))->assertOk()->assertJsonPath('scoring.bands.hot', 50)->assertJsonStructure(['defaults' => ['rules'], 'fields', 'operators', 'backtest']);
        $this->actingAs($this->hala)->getJson($this->url('settings.scoring'))->assertOk()->assertJsonPath('scoring.bands.hot', 75);
    }

    public function test_lead_search_by_name_does_not_match_every_phone(): void
    {
        $this->lead(['name' => 'Khalid Al-Mutairi', 'phone' => '0507781192']);
        $this->lead(['name' => 'Hessa Web', 'phone' => '0593310284']);
        $this->getJson($this->url('leads.index', ['q' => 'Khalid']))->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.contact.name', 'Khalid Al-Mutairi');
        $this->getJson($this->url('leads.index', ['q' => '0593310284']))->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.contact.name', 'Hessa Web');
    }
}
