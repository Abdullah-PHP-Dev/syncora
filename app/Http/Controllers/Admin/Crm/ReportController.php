<?php

namespace App\Http\Controllers\Admin\Crm;

use App\Http\Controllers\Controller;
use App\Models\Crm\Deal;
use App\Services\Crm\CrmReports;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** R-01 Sales performance, R-02 Lead sources & ROI. */
class ReportController extends Controller
{
    public function __construct(private CrmReports $reports) {}

    public function sales(Request $request)
    {
        $months = in_array($request->integer('months'), [3, 6, 12], true) ? $request->integer('months') : 6;

        return response()->json($this->reports->sales($months, $request->integer('pipeline_id') ?: null));
    }

    public function sources(Request $request)
    {
        return response()->json($this->reports->sources($this->days($request)));
    }

    /** Separate call: it reads live ad platforms, so it can be slow. */
    public function spend(Request $request)
    {
        $days = $this->days($request);

        return response()->json(['data' => $this->reports->spend($days, $this->reports->sources($days)['by_source']->all())]);
    }

    public function export(Request $request): StreamedResponse
    {
        $months = in_array($request->integer('months'), [3, 6, 12], true) ? $request->integer('months') : 6;
        $query = Deal::with(['stage:id,name', 'owner:id,name', 'contact:id,first_name,last_name', 'company:id,name'])
            ->where('created_at', '>=', now()->startOfMonth()->subMonthsNoOverflow($months - 1))->orderBy('id');

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['id', 'deal', 'value', 'currency', 'stage', 'status', 'owner', 'contact', 'company', 'source', 'created_at', 'won_at', 'lost_at', 'lost_reason']);
            $query->chunk(500, function ($deals) use ($out) {
                foreach ($deals as $d) {
                    fputcsv($out, [$d->id, $d->name, $d->value, $d->currency, $d->stage?->name, $d->status, $d->owner?->name, $d->contact?->full_name,
                        $d->company?->name, $d->source, $d->created_at, $d->won_at, $d->lost_at, $d->lost_reason]);
                }
            });
            fclose($out);
        }, 'crm-deals-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function days(Request $request): int
    {
        return in_array($request->integer('days'), [30, 90, 180, 365], true) ? $request->integer('days') : 90;
    }
}
