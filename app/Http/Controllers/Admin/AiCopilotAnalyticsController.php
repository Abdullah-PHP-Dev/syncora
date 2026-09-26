<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CopilotMessage;
use App\Models\KnowledgeGapReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * KPIs over the AI Copilot's existing audit trail (CopilotMessage, logged
 * by every run of ProcessAiCopilotReply/CopilotController) - pure
 * aggregation, no new tracking. Scoped to the current seller only; there's
 * no cross-seller "platform-wide AI performance" view since every
 * copilot_messages row already belongs to exactly one seller's own
 * Knowledge Base and conversations.
 */
class AiCopilotAnalyticsController extends Controller
{
    public function index(Request $request)
    {
        $days = max(1, min(365, (int) $request->integer('days', 30)));
        $since = now()->subDays($days);

        $base = CopilotMessage::where('user_id', Auth::id())->where('created_at', '>=', $since);

        $total = (clone $base)->count();
        $autoReplied = (clone $base)->where('resolution_type', 'auto_replied')->count();
        $suggested = (clone $base)->where('resolution_type', 'suggested')->count();
        $noMatch = (clone $base)->where('resolution_type', 'no_match')->count();
        $averageConfidence = $total > 0 ? round((clone $base)->avg('confidence')) : 0;
        $resolutionRate = $total > 0 ? round(($autoReplied / $total) * 100) : 0;

        $topGaps = KnowledgeGapReport::ownedBy(Auth::id())
            ->whereNotIn('status', ['ignored', 'resolved', 'faq_created'])
            ->orderByDesc('occurrence_count')
            ->limit(5)
            ->get();

        return view('admin.ai-copilot.analytics', [
            'days'              => $days,
            'total'             => $total,
            'autoReplied'       => $autoReplied,
            'suggested'         => $suggested,
            'noMatch'           => $noMatch,
            'averageConfidence' => $averageConfidence,
            'resolutionRate'    => $resolutionRate,
            'topGaps'           => $topGaps,
        ]);
    }
}
