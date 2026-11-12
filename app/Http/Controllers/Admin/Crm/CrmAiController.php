<?php

namespace App\Http\Controllers\Admin\Crm;

use App\Http\Controllers\Controller;
use App\Models\Crm\Contact;
use App\Models\Crm\Lead;
use App\Services\Crm\CrmAiService;
use App\Services\Crm\CrmContext;
use App\Services\Crm\CrmInsights;
use Illuminate\Http\Request;

/** CRM AI: each call uses one AI credit (routes are admin.crm.ai.*, package_access ai_content). */
class CrmAiController extends Controller
{
    public function __construct(private CrmAiService $ai, private CrmContext $context) {}

    public function brief(Contact $contact)
    {
        return response()->json(['data' => $this->ai->brief($contact, app()->getLocale())]);
    }

    /** AI-03 */
    public function askContact(Request $request, Contact $contact)
    {
        $data = $request->validate(['question' => ['required', 'string', 'max:500']]);

        return response()->json(['data' => $this->ai->askContact($contact, $data['question'], app()->getLocale())]);
    }

    /** AI-04: AI answers fill criteria nobody has set by hand; manual answers are kept. */
    public function qualify(Lead $lead)
    {
        $answers = $this->ai->qualify($lead, app()->getLocale());
        $current = $lead->qualification ?? [];
        foreach (LeadController::CRITERIA as $key) {
            if (($current[$key]['by'] ?? null) === 'manual' || ! isset($answers[$key])) {
                continue;
            }
            $current[$key] = ['met' => (bool) ($answers[$key]['met'] ?? false), 'note' => mb_substr((string) ($answers[$key]['note'] ?? ''), 0, 300) ?: null, 'by' => 'ai'];
        }
        $lead->forceFill(['qualification' => $current])->save();

        return response()->json(['qualification' => $current]);
    }

    /** AI-01 */
    public function ask(Request $request, CrmInsights $insights)
    {
        $data = $request->validate(['question' => ['required', 'string', 'max:500']]);
        $ws = $this->context->workspaceId();

        return response()->json(['data' => $this->ai->askCrm($ws, $insights->facts($ws), $data['question'], app()->getLocale())]);
    }
}
