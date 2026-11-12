<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Crm\TikTokLeads;
use Illuminate\Http\Request;

/**
 * TikTok LEAD webhook (Instant Form leads → CRM). The URL's secret segment is
 * derived from the TikTok app secret (TikTokLeads::callbackToken), so only the
 * subscription this app created can post here. TikTok delivers at least once;
 * LeadCapture skips leads it already has. Always answers 200 quickly.
 */
class CrmTikTokLeadController extends Controller
{
    public function __invoke(Request $request, string $token)
    {
        abort_unless(hash_equals(TikTokLeads::callbackToken(), $token), 404);
        if ($request->isMethod('post')) {
            TikTokLeads::handleWebhook($request->json()->all() ?: $request->all());
        }

        return response()->json(['ok' => true]);
    }
}
