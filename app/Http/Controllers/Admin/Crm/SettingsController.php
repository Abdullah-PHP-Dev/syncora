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

        return response()->json(['settings' => CrmSettings::save($this->context->workspaceId(), $data)]);
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
