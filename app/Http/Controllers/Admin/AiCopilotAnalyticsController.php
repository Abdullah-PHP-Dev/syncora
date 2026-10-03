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

        // Same-length window just before this one, for the KPI deltas.
        $previous = CopilotMessage::where('user_id', Auth::id())
            ->whereBetween('created_at', [$since->copy()->subDays($days), $since]);
        $previousTotal = (clone $previous)->count();
        $previousAuto = (clone $previous)->where('resolution_type', 'auto_replied')->count();
        $previousRate = $previousTotal > 0 ? round(($previousAuto / $previousTotal) * 100) : null;

        // Per-day counts by outcome for the trend chart (one grouped query,
        // missing days filled with zeros).
        $daily = (clone $base)
            ->selectRaw('DATE(created_at) as day, resolution_type, count(*) as total')
            ->groupBy('day', 'resolution_type')
            ->get()
            ->groupBy('day');
        $trend = ['labels' => [], 'auto_replied' => [], 'suggested' => [], 'no_match' => []];
        for ($d = $since->copy()->startOfDay()->addDay(); $d->lte(now()); $d->addDay()) {
            $rows = $daily->get($d->toDateString(), collect())->pluck('total', 'resolution_type');
            $trend['labels'][] = $d->translatedFormat('M j');
            foreach (['auto_replied', 'suggested', 'no_match'] as $type) {
                $trend[$type][] = (int) ($rows[$type] ?? 0);
            }
        }

        // FAQs the Copilot matched most (auto-replied or suggested).
        $topFaqs = (clone $base)
            ->whereNotNull('faq_id')
            ->whereIn('resolution_type', ['auto_replied', 'suggested'])
            ->selectRaw('faq_id, count(*) as uses, round(avg(confidence)) as avg_confidence')
            ->groupBy('faq_id')
            ->orderByDesc('uses')
            ->limit(5)
            ->with('faq:id,question')
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
            'previousTotal'     => $previousTotal,
            'previousRate'      => $previousRate,
            'trend'             => $trend,
            'topFaqs'           => $topFaqs,
        ]);
    }
}
