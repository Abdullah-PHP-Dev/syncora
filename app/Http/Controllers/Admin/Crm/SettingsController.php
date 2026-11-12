<?php

namespace App\Http\Controllers\Admin\Crm;

use App\Http\Controllers\Controller;
use App\Models\Crm\Contact;
use App\Models\Crm\Field;
use App\Models\SellerTeamMember;
use App\Models\SocialAccount;
use App\Services\Crm\CrmContext;
use App\Services\Crm\CrmLimits;
use App\Services\Crm\CrmSettings;
use App\Services\Crm\LeadScorer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** S-01 general, S-04 custom fields, S-05 tags, S-07/S-08 visibility, S-10 capture. */
class SettingsController extends Controller
{
    public function __construct(private CrmContext $context) {}

    public function show()
    {
        $ws = $this->context->workspaceId();

        return response()->json([
            'settings' => CrmSettings::for($ws),
            'defaults' => array_intersect_key(CrmSettings::DEFAULTS, array_flip(['sources', 'extra_statuses', 'disqualify_reasons', 'lost_reasons', 'win_reasons'])),
            'fields' => Field::orderBy('entity')->orderBy('sort_order')->get(),
            'tags' => $this->tags(),
            'team' => SellerTeamMember::with('user:id,name,email')->where('owner_id', $ws)->whereNotNull('accepted_at')->whereNotNull('user_id')->get()
                ->map(fn ($m) => ['id' => $m->id, 'name' => $m->user?->name ?? $m->email, 'role' => $m->role, 'crm' => $m->permission('crm')]),
            'capture' => [
                'url' => route('api.crm.capture', CrmSettings::captureToken($ws)),
                'meta_pages' => SocialAccount::with('connection')->where('user_id', $ws)->where('platform', 'facebook')->get()->map(fn ($p) => [
                    'id' => $p->id, 'name' => $p->name, 'granted' => \App\Services\Crm\MetaLeads::granted($p), 'has_token' => (bool) ($p->asset_token ?: $p->access_token),
                    'subscribed' => (bool) ($p->metadata['leadgen_subscribed'] ?? false), 'synced_at' => $p->metadata['leads_synced_at'] ?? null,
                ]),
                'tiktok_advertisers' => SocialAccount::where('user_id', $ws)->where('platform', 'tiktok')->where('has_ads_permission', true)->get()->map(fn ($a) => [
                    'id' => $a->id, 'name' => $a->name, 'subscribed' => (bool) ($a->metadata['crm_lead_subscription_id'] ?? null),
                    'error' => $a->metadata['crm_lead_subscription_error'] ?? null, 'synced_at' => $a->metadata['leads_synced_at'] ?? null,
                ]),
                'hub_url' => route('admin.connections.index'),
            ],
            'limits' => ['limit' => CrmLimits::limit(Auth::user()), 'used' => CrmLimits::used(Auth::user())],
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'member_visibility' => ['sometimes', Rule::in(['all', 'own'])],
            'member_overrides' => ['sometimes', 'array'], 'member_overrides.*' => [Rule::in(['all', 'own'])],
            'idle_days' => ['sometimes', 'integer', 'min:1', 'max:90'],
            'require_lost_reason' => ['sometimes', 'boolean'], 'auto_create_contacts' => ['sometimes', 'boolean'],
        ]);
        if (isset($data['member_overrides'])) {
            $valid = SellerTeamMember::where('owner_id', $this->context->workspaceId())->pluck('id')->all();
            $data['member_overrides'] = array_intersect_key($data['member_overrides'], array_flip($valid));
        }
        // S-03 lists. Lead statuses are stored on the lead (20 chars) and must not shadow the built-in ones.
        foreach (['sources' => 40, 'extra_statuses' => 20, 'disqualify_reasons' => 40, 'lost_reasons' => 80, 'win_reasons' => 80] as $list => $max) {
            if ($request->has($list)) {
                $request->validate([$list => ['present', 'array', 'max:30'], $list.'.*' => ['required', 'string', 'max:'.$max, 'distinct']]);
                $data[$list] = array_values(array_unique(array_map('trim', $request->input($list))));
            }
        }
        if (isset($data['extra_statuses']) && array_intersect($data['extra_statuses'], \App\Models\Crm\Lead::STATUSES)) {
            abort(422, __('crm.settings.status_reserved'));
        }
        foreach (['sources', 'disqualify_reasons'] as $list) {
            abort_if(isset($data[$list]) && ! $data[$list], 422, __('crm.settings.list_needs_one'));
        }
        if ($request->has('ai')) { // S-11
            $request->validate(['ai' => ['array'], 'ai.*' => ['boolean']]);
            $data['ai'] = collect(CrmSettings::DEFAULTS['ai'])->map(fn ($on, $key) => (bool) ($request->input('ai.'.$key) ?? $on))->all();
        }
        if ($request->has('scoring')) {
            $data['scoring'] = $this->validatedScoring($request);
        }

        return response()->json(['settings' => CrmSettings::save($this->context->workspaceId(), $data)]);
    }

    /** S-06 rules are free-form nested config: validate, then whitelist each rule's keys. */
    private function validatedScoring(Request $request): array
    {
        $fields = array_merge(\App\Services\Crm\AutomationEngine::FIELDS, LeadScorer::SIGNALS);
        $request->validate([
            'scoring' => ['array'], 'scoring.enabled' => ['boolean'], 'scoring.intent_signal' => ['boolean'],
            'scoring.bands.hot' => ['required', 'integer', 'min:1', 'max:100'], 'scoring.bands.warm' => ['required', 'integer', 'min:0', 'lt:scoring.bands.hot'],
            'scoring.rules' => ['present', 'array', 'max:40'],
            'scoring.rules.*.group' => ['required', Rule::in(LeadScorer::GROUPS)], 'scoring.rules.*.field' => ['required', Rule::in($fields)],
            'scoring.rules.*.operator' => ['required', Rule::in(\App\Services\Crm\AutomationEngine::OPERATORS)],
            'scoring.rules.*.value' => ['nullable', 'max:120'], 'scoring.rules.*.points' => ['required', 'integer', 'min:-100', 'max:100'],
            'scoring.rules.*.on' => ['boolean'],
        ]);
        $in = $request->input('scoring');

        return [
            'enabled' => (bool) ($in['enabled'] ?? true), 'intent_signal' => (bool) ($in['intent_signal'] ?? true),
            'bands' => ['hot' => (int) $in['bands']['hot'], 'warm' => (int) $in['bands']['warm']],
            'rules' => array_map(fn ($r) => [
                'group' => $r['group'], 'field' => $r['field'], 'operator' => $r['operator'],
                'value' => is_scalar($r['value'] ?? null) ? $r['value'] : null, 'points' => (int) $r['points'], 'on' => (bool) ($r['on'] ?? true),
            ], array_values($in['rules'] ?? [])),
        ];
    }

    /** S-12: the signed-in person's own alert choices (each teammate sets theirs). */
    public function notifications()
    {
        return response()->json(['events' => \App\Services\Crm\CrmNotifier::preferences($this->context->workspaceId(), $this->context->actorId())]);
    }

    public function saveNotifications(Request $request)
    {
        $request->validate(['events' => ['required', 'array'], 'events.*.in_app' => ['boolean'], 'events.*.email' => ['boolean']]);
        $mine = collect($request->input('events'))->only(\App\Services\Crm\CrmNotifier::EVENTS)
            ->map(fn ($v) => ['in_app' => (bool) ($v['in_app'] ?? true), 'email' => (bool) ($v['email'] ?? false)])->all();
        $all = CrmSettings::for($this->context->workspaceId())['notifications'] ?: [];
        $all[$this->context->actorId()] = $mine;
        CrmSettings::save($this->context->workspaceId(), ['notifications' => $all]);

        return $this->notifications();
    }

    /** S-06: scoring config with defaults, plus how each band converted over the last 90 days. */
    public function scoring()
    {
        $ws = $this->context->workspaceId();

        return response()->json(['scoring' => LeadScorer::config($ws), 'defaults' => LeadScorer::defaults(), 'backtest' => app(LeadScorer::class)->backtest($ws),
            'fields' => array_merge(\App\Services\Crm\AutomationEngine::FIELDS, LeadScorer::SIGNALS), 'operators' => \App\Services\Crm\AutomationEngine::OPERATORS]);
    }

    /** Recalculate every open contact's score with the saved rules (capped per call). */
    public function rescore()
    {
        $scorer = app(LeadScorer::class);
        $count = 0;
        Contact::where(fn ($q) => $q->whereHas('leads', fn ($l) => $l->whereNotIn('status', ['converted', 'unqualified']))
            ->orWhere('last_activity_at', '>=', now()->subDays(90)))->orderByDesc('last_activity_at')->limit(2000)->get()
            ->each(function ($c) use ($scorer, &$count) { $scorer->rescore($c); $count++; });

        return response()->json(['rescored' => $count, 'backtest' => $scorer->backtest($this->context->workspaceId())]);
    }

    public function rotateCapture()
    {
        return response()->json(['url' => route('api.crm.capture', CrmSettings::captureToken($this->context->workspaceId(), true))]);
    }

    /** Lead ads: (re)subscribe Pages / advertisers, or backfill now. */
    public function leads(Request $request)
    {
        $data = $request->validate(['action' => ['required', Rule::in(['subscribe', 'sync'])], 'account_id' => ['required', 'integer']]);
        $account = SocialAccount::where('user_id', $this->context->workspaceId())->whereIn('platform', ['facebook', 'tiktok'])->findOrFail($data['account_id']);
        @set_time_limit(180);
        $result = match ([$account->platform, $data['action']]) {
            ['facebook', 'subscribe'] => \App\Services\Crm\MetaLeads::subscribe($account),
            ['facebook', 'sync'] => \App\Services\Crm\MetaLeads::sync($account),
            ['tiktok', 'subscribe'] => \App\Services\Crm\TikTokLeads::subscribe($account, true),
            ['tiktok', 'sync'] => \App\Services\Crm\TikTokLeads::sync($account),
        };

        return response()->json(['result' => $result]);
    }

    public function storeField(Request $request)
    {
        $data = $this->validatedField($request);
        $data['key'] = Str::slug($data['label'], '_') ?: 'field_'.Str::random(5);
        abort_if(Field::where('entity', $data['entity'])->where('key', $data['key'])->exists(), 422, __('crm.settings.field_exists'));

        return response()->json(['data' => Field::create($data + ['sort_order' => Field::max('sort_order') + 1])], 201);
    }

    public function updateField(Request $request, Field $field)
    {
        $field->update(collect($this->validatedField($request))->except('entity')->all());

        return response()->json(['data' => $field]);
    }

    public function destroyField(Field $field)
    {
        $field->delete(); // Stored values stay on records (hidden) so re-adding the field restores them.

        return response()->json(['ok' => true]);
    }

    /** Rename or remove a tag on every contact in the workspace. */
    public function tag(Request $request)
    {
        $data = $request->validate(['from' => ['required', 'string', 'max:40'], 'to' => ['nullable', 'string', 'max:40']]);
        $changed = 0;
        Contact::withoutGlobalScope('visibility')->whereJsonContains('tags', $data['from'])->chunkById(200, function ($contacts) use ($data, &$changed) {
            foreach ($contacts as $contact) {
                $tags = array_values(array_unique(array_filter(array_map(fn ($t) => $t === $data['from'] ? ($data['to'] ?: null) : $t, $contact->tags ?? []))));
                $contact->update(['tags' => $tags ?: null]);
                $changed++;
            }
        });

        return response()->json(['changed' => $changed, 'tags' => $this->tags()]);
    }

    private function tags(): array
    {
        $counts = [];
        Contact::withoutGlobalScope('visibility')->whereNotNull('tags')->select(['id', 'tags'])->chunkById(1000, function ($rows) use (&$counts) {
            foreach ($rows as $row) {
                foreach ($row->tags ?? [] as $tag) {
                    $counts[$tag] = ($counts[$tag] ?? 0) + 1;
                }
            }
        });
        arsort($counts);

        return collect($counts)->map(fn ($n, $tag) => ['tag' => (string) $tag, 'count' => $n])->values()->all();
    }

    private function validatedField(Request $request): array
    {
        return $request->validate([
            'entity' => ['sometimes', Rule::in(['contact', 'company', 'deal'])],
            'label' => ['required', 'string', 'max:80'], 'type' => ['required', Rule::in(Field::TYPES)],
            'options' => ['nullable', 'array', 'max:50', 'required_if:type,dropdown'], 'options.*' => ['string', 'max:60'],
        ]) + ['entity' => $request->input('entity', 'contact')];
    }
}
