<?php

namespace App\Http\Controllers\Admin\Crm;

use App\Http\Controllers\Controller;
use App\Models\Crm\Contact;
use App\Models\Crm\Segment;
use App\Models\EmailMarketing\EmailList;
use App\Services\Crm\CrmContext;
use App\Services\Crm\EmailListBridge;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** C-11 saved segments, and sending a segment to an Email Marketing list. */
class SegmentController extends Controller
{
    public const FILTERS = ['q', 'lifecycle', 'source', 'owner_id', 'tag', 'has_conversation', 'company_id'];

    public function __construct(private CrmContext $context) {}

    public function index()
    {
        return response()->json([
            'data' => Segment::orderBy('name')->get()->map(fn ($s) => $s->toArray() + ['count' => ContactController::applyFilters(Contact::query(), $s->filters, $this->context)->count()]),
            'email_lists' => EmailList::where('user_id', $this->context->workspaceId())->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:120'], 'filters' => ['required', 'array']]);
        $data['filters'] = array_filter(array_intersect_key($data['filters'], array_flip(self::FILTERS)), fn ($v) => $v !== '' && $v !== null && $v !== false);

        return response()->json(['data' => Segment::create($data + ['created_by' => $this->context->actorId()])], 201);
    }

    public function destroy(Segment $segment)
    {
        $segment->delete();

        return response()->json(['ok' => true]);
    }

    public function toEmailList(Request $request, Segment $segment, EmailListBridge $bridge)
    {
        $ws = $this->context->workspaceId();
        $data = $request->validate([
            'email_list_id' => ['nullable', Rule::exists('email_lists', 'id')->where('user_id', $ws)],
            'new_list' => ['required_without:email_list_id', 'nullable', 'string', 'max:120'],
        ]);
        $list = isset($data['email_list_id']) ? EmailList::find($data['email_list_id']) : EmailList::create(['user_id' => $ws, 'name' => $data['new_list']]);
        $contacts = ContactController::applyFilters(Contact::query(), $segment->filters, $this->context)->whereNotNull('email')->limit(10000)->get();

        return response()->json(['list' => ['id' => $list->id, 'name' => $list->name]] + $bridge->add($list, $contacts));
    }
}
