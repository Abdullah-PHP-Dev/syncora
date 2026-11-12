<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Crm\LeadCapture;
use Illuminate\Http\Request;

/**
 * Public lead capture for website forms and form tools (Zapier, Make,
 * Typeform webhooks…). The URL's secret token identifies the workspace;
 * rotating it in CRM settings revokes the old URL.
 */
class CrmCaptureController extends Controller
{
    public function __invoke(Request $request, string $token, LeadCapture $capture)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'phone' => ['required_without:email', 'nullable', 'string', 'max:40', 'regex:/^[+\d\s().-]{7,}$/'],
            'email' => ['required_without:phone', 'nullable', 'email', 'max:160'],
            'message' => ['nullable', 'string', 'max:2000'], 'source' => ['nullable', 'string', 'max:40'],
            'campaign' => ['nullable', 'string', 'max:160'], 'value' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'submission_id' => ['nullable', 'string', 'max:90'],
            'utm_campaign' => ['nullable', 'string', 'max:160'], 'utm_source' => ['nullable', 'string', 'max:40'],
        ]);
        // Standard UTM fields from landing pages fill campaign/source when those are not sent.
        $data['campaign'] = $data['campaign'] ?? $data['utm_campaign'] ?? null;
        $data['source'] = $data['source'] ?? (isset($data['utm_source']) ? mb_strtolower($data['utm_source']) : null);
        $id = $capture->captureForm($token, $data);
        abort_unless($id, 404);

        return response()->json(['ok' => true, 'lead_id' => $id], 201);
    }
}
