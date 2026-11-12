<?php

namespace App\Http\Controllers\Admin\Crm;

use App\Http\Controllers\Controller;
use App\Models\Crm\Automation;
use App\Models\Crm\AutomationRun;
use App\Services\Crm\AutomationEngine;
use App\Services\Crm\CrmContext;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** AU-01 list and AU-02 builder. */
class AutomationController extends Controller
{
    public function __construct(private CrmContext $context, private AutomationEngine $engine) {}

    public function index()
    {
        return response()->json([
            'data' => Automation::orderByDesc('is_active')->orderByDesc('updated_at')->get(),
            'runs' => AutomationRun::with([])->latest('id')->limit(30)->get(['id', 'automation_id', 'subject_type', 'subject_id', 'status', 'result', 'created_at']),
            'email_lists' => \App\Models\EmailMarketing\EmailList::where('user_id', $this->context->workspaceId())->orderBy('name')->get(['id', 'name']),
            'schema' => ['triggers' => AutomationEngine::TRIGGERS, 'actions' => AutomationEngine::ACTIONS, 'fields' => AutomationEngine::FIELDS, 'operators' => AutomationEngine::OPERATORS],
        ]);
    }

    public function store(Request $request)
    {
        $automation = Automation::create($this->validated($request) + ['created_by' => $this->context->actorId()]);

        return response()->json(['data' => $automation], 201);
    }

    public function update(Request $request, Automation $automation)
    {
        $automation->update($this->validated($request));

        return response()->json(['data' => $automation->fresh()]);
    }

    public function toggle(Automation $automation)
    {
        $automation->update(['is_active' => ! $automation->is_active]);

        return response()->json(['data' => $automation]);
    }

    public function destroy(Automation $automation)
    {
        $automation->delete();

        return response()->json(['ok' => true]);
    }

    /** "Test run": how often this definition would have fired in the last 30 days. Changes nothing. */
    public function preview(Request $request)
    {
        return response()->json(['matches' => $this->engine->preview($this->validated($request, false))]);
    }

    private function validated(Request $request, bool $named = true): array
    {
        $data = $request->validate([
            'name' => [$named ? 'required' : 'nullable', 'string', 'max:120'],
            'trigger' => ['required', Rule::in(AutomationEngine::TRIGGERS)],
            'trigger_config' => ['nullable', 'array'], 'trigger_config.stage_id' => ['nullable', 'integer'], 'trigger_config.days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'conditions' => ['nullable', 'array', 'max:10'],
            'conditions.*.field' => ['required', Rule::in(AutomationEngine::FIELDS)],
            'conditions.*.operator' => ['required', Rule::in(AutomationEngine::OPERATORS)],
            'conditions.*.value' => ['nullable'],
            'actions' => ['required', 'array', 'min:1', 'max:10'],
            'actions.*.type' => ['required', Rule::in(AutomationEngine::ACTIONS)],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        // validate() returns only keys that have rules, so read each action's settings from the
        // input, keeping known keys of simple types. Ids are checked again when the action runs.
        $keep = ['type', 'subject', 'due_minutes', 'assignee', 'mode', 'member_ids', 'tag', 'lifecycle', 'status', 'stage_id', 'email_list_id', 'member_id', 'message'];
        $data['actions'] = array_map(function ($action) use ($keep) {
            $action = array_intersect_key((array) $action, array_flip($keep));
            $action['member_ids'] = array_values(array_map('intval', array_filter((array) ($action['member_ids'] ?? []), 'is_numeric')));
            foreach (['subject', 'tag', 'message'] as $text) {
                if (isset($action[$text])) {
                    $action[$text] = mb_substr((string) $action[$text], 0, 200);
                }
            }

            return array_filter($action, fn ($v) => is_scalar($v) || is_array($v) || $v === null);
        }, array_values((array) $request->input('actions', [])));

        return $data;
    }
}
