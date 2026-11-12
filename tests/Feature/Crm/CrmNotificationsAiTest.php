<?php

namespace Tests\Feature\Crm;

use App\Http\Middleware\EnsurePackageFeature;
use App\Models\Crm\Activity;
use App\Models\Crm\Lead;
use App\Models\Crm\Notification;
use App\Models\Crm\Stage;
use App\Models\SellerTeamMember;
use App\Models\User;
use App\Services\Crm\CrmSettings;
use App\Support\Settings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\Feature\Crm\Concerns\CrmFixtures;
use Tests\TestCase;

/** Bell alerts and preferences (G-02/S-12), AI insights/ask/qualification and AI toggles (AI-01/03/04, S-11). */
class CrmNotificationsAiTest extends TestCase
{
    use CrmFixtures;

    private User $sara;

    private SellerTeamMember $saraMember;

    private User $omar;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCrm();
        Mail::fake();
        $this->sara = User::factory()->create(['name' => 'Sara']);
        $this->saraMember = SellerTeamMember::create(['owner_id' => $this->rawda->id, 'user_id' => $this->sara->id, 'email' => $this->sara->email, 'role' => 'member',
            'accepted_at' => now(), 'permissions' => ['crm' => 'write']]);
        $this->omar = User::factory()->create(['name' => 'Omar']);
        SellerTeamMember::create(['owner_id' => $this->rawda->id, 'user_id' => $this->omar->id, 'email' => $this->omar->email, 'role' => 'admin',
            'accepted_at' => now(), 'permissions' => ['crm' => 'write']]);
    }

    private function asSara(): self
    {
        return $this->actingAs($this->sara)->withSession(['seller_workspace_member' => $this->saraMember->id]);
    }

    public function test_alerts_reach_the_right_person_follow_preferences_and_can_be_read(): void
    {
        // Assigned to Sara by Rawda → Sara is told; an unowned lead → the admin (not Rawda, who created it).
        $this->actingAs($this->rawda)->postJson($this->url('leads.store'), ['name' => 'Noura', 'phone' => '0552148830', 'owner_id' => $this->sara->id])->assertCreated();
        $this->postJson($this->url('leads.store'), ['name' => 'Nobody', 'phone' => '0551112222', 'owner_id' => null])->assertCreated();
        $this->assertSame(1, Notification::where('user_id', $this->sara->id)->where('event', 'lead_assigned')->count());
        $this->assertSame(1, Notification::where('user_id', $this->omar->id)->where('event', 'lead_unassigned')->count());
        $this->assertSame(0, Notification::where('user_id', $this->rawda->id)->count(), 'nobody is told about their own action');

        // The bell shows Sara her own CRM item inside the workspace, and reading it clears it.
        $bell = $this->asSara()->getJson($this->url('notifications'))->assertOk()->assertJsonPath('count', 1);
        $item = collect($bell->json('items'))->firstWhere('type', 'crm');
        $this->assertNotNull($item);
        $this->assertStringContainsString('Noura', $item['author']);
        $this->patchJson($item['read_url'])->assertOk();
        $this->assertNotNull(Notification::find($item['id'])->read_at);
        $this->assertSame(0, $this->getJson($this->url('notifications'))->json('count'));
        $other = Notification::where('user_id', $this->omar->id)->firstOrFail();
        $this->patchJson($this->url('notifications.read', $other->id))->assertOk();
        $this->assertNull($other->fresh()->read_at, 'one person cannot clear another person\'s alert');
        $this->flushSession();

        // Sara turns lead alerts off for herself; Rawda's choices are separate.
        $this->asSara()->putJson($this->url('settings.notifications.update'), ['events' => ['lead_assigned' => ['in_app' => false, 'email' => false]]])
            ->assertOk()->assertJsonPath('events.lead_assigned.in_app', false)->assertJsonPath('events.task_reminder.in_app', true);
        $this->flushSession();
        $this->actingAs($this->rawda)->getJson($this->url('settings.notifications'))->assertJsonPath('events.lead_assigned.in_app', true);
        $this->postJson($this->url('leads.store'), ['name' => 'Second', 'phone' => '0553334444', 'owner_id' => $this->sara->id])->assertCreated();
        $this->assertSame(1, Notification::where('user_id', $this->sara->id)->where('event', 'lead_assigned')->count());

        // Deal won → owner and managers hear about it.
        $this->getJson($this->url('pipelines.index'))->assertOk();
        $open = Stage::withoutGlobalScopes()->where('workspace_id', $this->rawda->id)->where('kind', 'open')->firstOrFail();
        $won = Stage::withoutGlobalScopes()->where('workspace_id', $this->rawda->id)->where('kind', 'won')->firstOrFail();
        $dealId = $this->postJson($this->url('deals.store'), ['name' => 'Sofa', 'value' => 5000, 'pipeline_id' => $open->pipeline_id, 'stage_id' => $open->id, 'owner_id' => $this->sara->id])->json('data.id');
        $this->assertTrue(Notification::where('user_id', $this->sara->id)->where('event', 'deal_assigned')->exists());
        $this->postJson($this->url('deals.move', $dealId), ['stage_id' => $won->id])->assertOk();
        $this->assertTrue(Notification::where('user_id', $this->sara->id)->where('event', 'deal_won')->exists());
        $this->assertTrue(Notification::where('user_id', $this->omar->id)->where('event', 'deal_won')->exists());
    }

    public function test_task_reminders_are_sent_once(): void
    {
        $task = Activity::withoutGlobalScopes()->create(['workspace_id' => $this->rawda->id, 'type' => 'task', 'subject' => 'Call Noura', 'owner_id' => $this->sara->id,
            'due_at' => now()->addMinutes(10), 'remind_at' => now()->subMinute()]);
        $this->artisan('crm:reminders')->assertSuccessful();
        $this->artisan('crm:reminders')->assertSuccessful();
        $this->assertSame(1, Notification::where('user_id', $this->sara->id)->where('event', 'task_reminder')->count());
        $this->assertTrue($task->fresh()->meta['reminded']);
    }

    public function test_ai_qualification_keeps_manual_answers_and_ai_can_be_switched_off(): void
    {
        $this->withoutMiddleware(EnsurePackageFeature::class);
        Settings::set('gemini_api_key', 'gemini-key');
        $answers = ['need' => ['met' => true, 'note' => 'Needs a 9-seat majlis'], 'budget' => ['met' => true, 'note' => 'Said 20k'],
            'timing' => ['met' => false, 'note' => ''], 'decision_maker' => ['met' => true, 'note' => 'Owner'], 'delivery_area' => ['met' => true, 'note' => 'Riyadh']];
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['candidates' => [['content' => ['parts' => [['text' => json_encode($answers)]]]]]])]);

        $leadId = $this->actingAs($this->rawda)->postJson($this->url('leads.store'), ['name' => 'Noura', 'phone' => '0552148830'])->json('data.id');
        $this->patchJson($this->url('leads.update', $leadId), ['qualification' => ['budget' => ['met' => false, 'note' => 'Unsure']]])->assertOk();
        $q = $this->postJson(route('admin.crm.ai.qualify', $leadId))->assertOk()->json('qualification');
        $this->assertSame(['met' => true, 'note' => 'Needs a 9-seat majlis', 'by' => 'ai'], $q['need']);
        $this->assertSame(['met' => false, 'note' => 'Unsure', 'by' => 'manual'], $q['budget']);
        $this->assertSame('ai', Lead::withoutGlobalScopes()->find($leadId)->qualification['delivery_area']['by']);
        $this->actingAs($this->hala)->postJson(route('admin.crm.ai.qualify', $leadId))->assertNotFound();

        $this->actingAs($this->rawda)->putJson($this->url('settings.update'), ['ai' => ['qualification' => false]])->assertOk();
        $this->assertTrue(CrmSettings::for($this->rawda->id)['ai']['brief']);
        $this->postJson(route('admin.crm.ai.qualify', $leadId))->assertForbidden();
    }

    public function test_insights_and_ask_crm_use_only_workspace_facts(): void
    {
        $this->withoutMiddleware(EnsurePackageFeature::class);
        Settings::set('gemini_api_key', 'gemini-key');
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['candidates' => [['content' => ['parts' => [['text' => json_encode(['answer' => 'Instagram brings most leads.', 'suggestions' => ['Reply faster']])]]]]]])]);

        $this->actingAs($this->rawda)->postJson($this->url('leads.store'), ['name' => 'Noura', 'phone' => '0552148830', 'source' => 'instagram', 'owner_id' => null])->assertCreated();
        $this->actingAs($this->hala)->postJson($this->url('leads.store'), ['name' => 'Other', 'phone' => '0559990000', 'source' => 'tiktok'])->assertCreated();

        $res = $this->actingAs($this->rawda)->getJson($this->url('insights'))->assertOk()->assertJsonPath('facts.leads.total', 1)->assertJsonPath('ai', true);
        $this->assertContains('unassigned', array_column($res->json('cards'), 'key'));

        $this->postJson(route('admin.crm.ai.ask'), ['question' => 'Where do leads come from?'])->assertOk()->assertJsonPath('data.answer', 'Instagram brings most leads.');
        Http::assertSent(fn ($r) => str_contains($r->body(), 'instagram') && ! str_contains($r->body(), 'tiktok') && ! str_contains($r->body(), '0552148830'));
    }
}
