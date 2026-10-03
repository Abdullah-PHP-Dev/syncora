<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiCopilotSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * A seller's own AI Copilot configuration - whether it's on at all,
 * whether it's allowed to actually send a reply automatically (a
 * separate, stricter opt-in than just "on"), and the confidence
 * thresholds ProcessAiCopilotReply branches on. One row per seller
 * (AiCopilotSetting::forSeller()) - see that model's docblock for why
 * this isn't the app's existing installation-wide adminSetting() table.
 *
 * No tone/business-instructions setting here: every reply is the matched
 * FAQ's answer sent verbatim (AiCopilotService's own grounding rule), so
 * there's nothing for a tone/instructions setting to actually influence
 * without adding an LLM rephrasing step - which would reopen the
 * fabrication risk that rule exists to prevent. A prior pass added these
 * fields to the settings form without wiring them to anything; removed
 * rather than shipping a control with no effect.
 */
class AiCopilotSettingController extends Controller
{
    public function index()
    {
        $settings = AiCopilotSetting::forSeller(Auth::id());

        // What the Copilot answers from - shown so a seller enabling it
        // can see whether there's anything for it to match against yet.
        return view('admin.ai-copilot.settings', [
            'settings'           => $settings,
            'publishedFaqCount'  => \App\Models\Faq::ownedBy(Auth::id())->published()->count(),
            'hasBusinessProfile' => \App\Models\BusinessProfile::where('user_id', Auth::id())->exists(),
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'ai_enabled'                      => ['nullable', 'boolean'],
            'auto_reply_enabled'              => ['nullable', 'boolean'],
            'confidence_threshold_auto'       => ['required', 'integer', 'min:0', 'max:100'],
            'confidence_threshold_suggested'  => ['required', 'integer', 'min:0', 'max:100'],
        ]);

        // The auto-send threshold can never be lower than the suggestion
        // threshold - a match that only clears the "suggest to a human"
        // bar must never qualify as "confident enough to send
        // unattended" purely because of an inverted setting.
        abort_if($validated['confidence_threshold_auto'] < $validated['confidence_threshold_suggested'], 422,
            'The auto-reply confidence threshold cannot be lower than the suggestion threshold.');

        $settings = AiCopilotSetting::updateOrCreate(
            ['user_id' => Auth::id()],
            [
                'ai_enabled'                      => $request->boolean('ai_enabled'),
                'auto_reply_enabled'              => $request->boolean('auto_reply_enabled'),
                'confidence_threshold_auto'       => $validated['confidence_threshold_auto'],
                'confidence_threshold_suggested'  => $validated['confidence_threshold_suggested'],
            ]
        );

        return response()->json(['success' => true, 'settings' => $settings, 'message' => 'AI Copilot settings saved.']);
    }
}
