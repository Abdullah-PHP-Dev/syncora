<?php

namespace App\Http\Controllers\Admin\Crm;

use App\Http\Controllers\Controller;
use App\Models\Crm\Activity;
use App\Models\Crm\Contact;
use App\Models\Messaging\Message;
use App\Services\Crm\ContactMatcher;
use App\Services\Crm\CrmContext;
use App\Services\Crm\Timeline;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ContactController extends Controller
{
    use Presents;

    public function __construct(private CrmContext $context, private ContactMatcher $matcher, private Timeline $timeline) {}

    public function index(Request $request)
    {
        $contacts = $this->filtered($request)->with(['owner:id,name', 'company:id,name'])
            ->orderBy(...$this->sort($request))->paginate(25);

        return response()->json([
            'data' => $contacts->getCollection()->map(fn ($c) => $this->contactRow($c)),
            'meta' => ['total' => $contacts->total(), 'page' => $contacts->currentPage(), 'last_page' => $contacts->lastPage()],
            'counts' => [
                'all' => Contact::count(),
                'mine' => Contact::where('owner_id', $this->context->actorId())->count(),
                'customers' => Contact::where('lifecycle', 'customer')->count(),
                'unassigned' => Contact::whereNull('owner_id')->count(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        if (! $request->boolean('force') && ($dupes = $this->matcher->duplicates($data['phone'] ?? null, $data['email'] ?? null))->isNotEmpty()) {
            return response()->json(['message' => __('crm.contacts.duplicate_found'), 'duplicates' => $dupes], 409);
        }
        $contact = Contact::create($data + ['created_by' => $this->context->actorId(), 'owner_id' => $data['owner_id'] ?? $this->context->actorId(), 'last_activity_at' => now()]);
        $this->timeline->event('contact_created', ['contact_id' => $contact->id]);

        return response()->json(['data' => $this->contactRow($contact->load('owner', 'company'))], 201);
    }

    /** Contact 360°: record, deals, leads, timeline (CRM activity + Inbox messages) and conversations. */
    public function show(Contact $contact)
    {
        $contact->load(['owner:id,name', 'company:id,name', 'deals.stage', 'deals.owner:id,name', 'leads.owner:id,name', 'identities']);
        $conversations = $contact->conversations()->with('channel:id,platform,name,username')->latest('last_message_at')->get();

        $activities = $contact->activities()->with(['owner:id,name', 'author:id,name', 'deal:id,name'])->latest()->limit(100)->get()
            ->map(fn ($a) => ['kind' => 'activity', 'at' => $a->created_at] + $this->activityRow($a));
        // Inbox history is read from the existing messages table, never copied.
        $messages = Message::whereIn('conversation_id', $conversations->pluck('id'))->latest('created_at')->limit(30)->get()
            ->map(fn ($m) => ['kind' => 'message', 'at' => $m->created_at, 'id' => 'm'.$m->id, 'direction' => $m->direction,
                'body' => mb_substr((string) $m->body, 0, 500), 'conversation_id' => $m->conversation_id, 'created_at' => $m->created_at?->toIso8601String()]);

        return response()->json([
            'contact' => $this->contactRow($contact) + [
                'language' => $contact->language, 'preferred_channel' => $contact->preferred_channel,
                'whatsapp_consent' => $contact->whatsapp_consent, 'custom_fields' => $contact->custom_fields ?? [],
                'company_id' => $contact->company_id, 'owner_id' => $contact->owner_id,
                'identities' => $contact->identities->map->only(['platform', 'handle', 'external_id']),
            ],
            'deals' => $contact->deals->map(fn ($d) => $this->dealRow($d)),
            'leads' => $contact->leads->map(fn ($l) => $this->leadRow($l->setRelation('contact', null))),
            'conversations' => $conversations->map(fn ($c) => [
                'id' => $c->id, 'platform' => $c->platform, 'preview' => $c->last_message_preview, 'unread' => $c->unread_count,
                'last_message_at' => $c->last_message_at?->toIso8601String(), 'url' => route('admin.chats.dashboard', ['conversation' => $c->id]),
            ]),
            'email_lists' => $contact->email ? \App\Models\EmailMarketing\EmailSubscriber::where('user_id', $this->context->workspaceId())
                ->where('email', $contact->email)->with('lists:id,name')->first()?->lists->map->only(['id', 'name'])->values() ?? [] : [],
            'fields' => \App\Models\Crm\Field::where('entity', 'contact')->orderBy('sort_order')->get(['key', 'label', 'type', 'options']),
            'timeline' => $activities->concat($messages)->sortByDesc('at')->values()->map(fn ($r) => collect($r)->except('at')),
        ]);
    }

    public function update(Request $request, Contact $contact)
    {
        $data = $this->validated($request, $contact);
        $before = $contact->only(['lifecycle', 'owner_id']);
        $contact->update($data);
        foreach ($before as $field => $old) {
            if ($contact->wasChanged($field)) {
                $this->timeline->event($field.'_changed', ['contact_id' => $contact->id], ['from' => $old, 'to' => $contact->{$field}]);
            }
        }

        return response()->json(['data' => $this->contactRow($contact->load('owner', 'company'))]);
    }

    public function destroy(Contact $contact)
    {
        $contact->conversations()->update(['crm_contact_id' => null]);
        $contact->delete();

        return response()->json(['ok' => true]);
    }

    public function duplicates(Request $request)
    {
        return response()->json(['data' => $this->matcher->duplicates($request->query('phone'), $request->query('email'), $request->integer('except') ?: null)]);
    }

    /** Bulk bar: assign owner, add tags, change lifecycle, delete. */
    public function bulk(Request $request)
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'max:500'], 'ids.*' => ['integer'],
            'action' => ['required', Rule::in(['assign', 'tag', 'lifecycle', 'delete'])],
            'owner_id' => ['nullable', 'required_if:action,assign', Rule::in($this->context->memberIds())],
            'tags' => ['nullable', 'required_if:action,tag', 'array'], 'tags.*' => ['string', 'max:40'],
            'lifecycle' => ['nullable', 'required_if:action,lifecycle', Rule::in(Contact::LIFECYCLES)],
        ]);
        $contacts = Contact::whereIn('id', $data['ids'])->get();
        foreach ($contacts as $contact) {
            match ($data['action']) {
                'assign' => $contact->update(['owner_id' => $data['owner_id']]),
                'tag' => $contact->update(['tags' => array_values(array_unique(array_merge($contact->tags ?? [], $data['tags'])))]),
                'lifecycle' => $contact->update(['lifecycle' => $data['lifecycle']]),
                'delete' => tap($contact, fn ($c) => $c->conversations()->update(['crm_contact_id' => null]))->delete(),
            };
        }

        return response()->json(['updated' => $contacts->count()]);
    }

    public function export(Request $request): StreamedResponse
    {
        $query = $this->filtered($request)->with(['owner:id,name', 'company:id,name'])->orderBy('id');

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // Excel opens UTF-8 (Arabic names) correctly with a BOM.
            fputcsv($out, ['id', 'first_name', 'last_name', 'arabic_name', 'phone', 'email', 'company', 'lifecycle', 'source', 'score', 'owner', 'tags', 'created_at']);
            $query->chunk(500, function ($rows) use ($out) {
                foreach ($rows as $c) {
                    fputcsv($out, [$c->id, $c->first_name, $c->last_name, $c->arabic_name, $c->phone, $c->email, $c->company?->name, $c->lifecycle,
                        $c->source, $c->score, $c->owner?->name, implode('|', $c->tags ?? []), $c->created_at?->toDateTimeString()]);
                }
            });
            fclose($out);
        }, 'contacts-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Log a note, call, meeting or email on the contact (and touch last activity). */
    public function log(Request $request, Contact $contact)
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(['note', 'call', 'meeting', 'email', 'task'])],
            'subject' => ['nullable', 'string', 'max:200'], 'body' => ['nullable', 'string', 'max:5000'],
            'due_at' => ['nullable', 'date'], 'owner_id' => ['nullable', Rule::in($this->context->memberIds())],
            'deal_id' => ['nullable', 'integer'],
        ]);
        abort_if(empty($data['subject']) && empty($data['body']), 422, __('crm.activities.empty'));
        $activity = Activity::create($data + [
            'contact_id' => $contact->id, 'created_by' => $this->context->actorId(),
            'owner_id' => $data['owner_id'] ?? $this->context->actorId(),
            'completed_at' => $data['type'] === 'task' ? null : now(),
            'deal_id' => isset($data['deal_id']) ? $contact->deals()->whereKey($data['deal_id'])->value('id') : null,
        ]);
        $contact->update(['last_activity_at' => now()]);

        return response()->json(['data' => $this->activityRow($activity->load('owner', 'author'))], 201);
    }

    private function filtered(Request $request)
    {
        $filters = $request->query();
        if ($request->query('segment_id') && ($segment = \App\Models\Crm\Segment::find($request->query('segment_id')))) {
            $filters = array_merge($segment->filters, array_filter($filters, fn ($v) => $v !== '' && $v !== null));
        }

        return self::applyFilters(Contact::query(), $filters, $this->context);
    }

    /** The contact list filters (also what a saved segment stores). */
    public static function applyFilters($query, array $f, CrmContext $context)
    {
        $q = trim((string) ($f['q'] ?? ''));
        $digits = preg_replace('/\D+/', '', $q);

        return $query
            ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w->where('first_name', 'like', "%{$q}%")->orWhere('last_name', 'like', "%{$q}%")
                ->orWhere('arabic_name', 'like', "%{$q}%")->orWhere('email', 'like', "%{$q}%")
                ->when(strlen($digits) >= 3, fn ($x) => $x->orWhere('phone_key', 'like', "%{$digits}%"))))
            ->when(($f['tab'] ?? null) === 'mine', fn ($query) => $query->where('owner_id', $context->actorId()))
            ->when(($f['tab'] ?? null) === 'customers', fn ($query) => $query->where('lifecycle', 'customer'))
            ->when(($f['tab'] ?? null) === 'unassigned', fn ($query) => $query->whereNull('owner_id'))
            ->when($f['lifecycle'] ?? null, fn ($query, $v) => $query->whereIn('lifecycle', (array) $v))
            ->when($f['owner_id'] ?? null, fn ($query, $v) => $query->where('owner_id', $v))
            ->when($f['source'] ?? null, fn ($query, $v) => $query->where('source', $v))
            ->when($f['company_id'] ?? null, fn ($query, $v) => $query->where('company_id', $v))
            ->when($f['tag'] ?? null, fn ($query, $v) => $query->whereJsonContains('tags', $v))
            ->when(filter_var($f['has_conversation'] ?? false, FILTER_VALIDATE_BOOLEAN), fn ($query) => $query->whereHas('conversations'));
    }

    private function sort(Request $request): array
    {
        $column = in_array($request->query('sort'), ['first_name', 'score', 'created_at', 'last_activity_at'], true) ? $request->query('sort') : 'created_at';

        return [$column, $request->query('dir') === 'asc' ? 'asc' : 'desc'];
    }

    private function validated(Request $request, ?Contact $contact = null): array
    {
        $partial = $contact ? 'sometimes' : 'required';
        $fields = \App\Models\Crm\Field::where('entity', 'contact')->get()->keyBy('key');
        $custom = [];
        foreach ($fields as $key => $field) {
            $custom['custom_fields.'.$key] = match ($field->type) {
                'number' => ['nullable', 'numeric'], 'date' => ['nullable', 'date'],
                'dropdown' => ['nullable', Rule::in($field->options ?? [])], default => ['nullable', 'string', 'max:500'],
            };
        }

        $data = $request->validate($custom + [
            'first_name' => [$partial, 'string', 'max:80'], 'last_name' => ['nullable', 'string', 'max:80'],
            'arabic_name' => ['nullable', 'string', 'max:160'], 'email' => ['nullable', 'email', 'max:160'],
            'phone' => ['nullable', 'string', 'max:40', 'regex:/^[+\d\s().-]{7,}$/'], 'job_title' => ['nullable', 'string', 'max:120'],
            'company_id' => ['nullable', Rule::exists('crm_companies', 'id')->where('workspace_id', $this->context->workspaceId())->whereNull('deleted_at')],
            'lifecycle' => ['sometimes', Rule::in(Contact::LIFECYCLES)], 'source' => ['nullable', 'string', 'max:40'],
            'owner_id' => ['nullable', Rule::in($this->context->memberIds())], 'language' => ['nullable', Rule::in(['ar', 'en'])],
            'preferred_channel' => ['nullable', 'string', 'max:20'], 'whatsapp_consent' => ['sometimes', 'boolean'],
            'score' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'tags' => ['nullable', 'array', 'max:20'], 'tags.*' => ['string', 'max:40'],
            'custom_fields' => ['nullable', 'array', 'max:30'],
        ]);
        if (array_key_exists('custom_fields', $data)) {
            // Only defined fields are stored; values of fields not in this form are kept.
            $data['custom_fields'] = array_replace($contact?->custom_fields ?? [], array_intersect_key($data['custom_fields'] ?? [], $fields->all()));
        }

        return $data;
    }
}
