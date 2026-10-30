<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdReportSchedule;
use App\Models\User;
use App\Services\AdServices\FacebookReportService;
use App\Services\AdServices\SocialAdManagerService;
use App\Support\AdAccountSelection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\HeaderUtils;

/**
 * Facebook performance reports: CSV export of the current period and
 * scheduled email reports for the selected ad account. Every schedule is
 * scoped to the workspace owner (UseSellerWorkspace sets Auth to them).
 */
class AdReportController extends Controller
{
    public const MAX_SCHEDULES = 10;

    public function __construct(private SocialAdManagerService $ads, private FacebookReportService $reports)
    {
    }

    /** The email of the person actually signed in (a workspace member's own, not the owner's). */
    public static function loginEmail(Request $request): ?string
    {
        $member = $request->attributes->get('workspace_member');
        if ($member) {
            return optional(User::find($member->user_id))->email ?: $member->email;
        }

        return $request->user()?->email;
    }

    public static function present(AdReportSchedule $s): array
    {
        return [
            'id' => $s->id,
            'name' => $s->name,
            'frequency' => $s->frequency,
            'weekday' => $s->weekday,
            'day_of_month' => $s->day_of_month,
            'send_time' => $s->send_time,
            'timezone' => $s->timezone,
            'period_days' => $s->period_days,
            'recipient_email' => $s->recipient_email,
            'locale' => $s->locale,
            'status' => $s->status,
            'next_run_at' => optional($s->next_run_at)->toIso8601String(),
            'last_sent_at' => optional($s->last_sent_at)->toIso8601String(),
            'last_error' => $s->last_error,
        ];
    }

    /** CSV of the selected account's current period (same numbers as the page). */
    public function export(Request $request, string $platform)
    {
        abort_unless(in_array($platform, ['facebook', 'instagram'], true), 404);
        $days = (int) ($request->validate(['days' => ['nullable', 'integer', Rule::in(AdReportSchedule::PERIODS)]])['days'] ?? 30);

        $result = $this->reports->build($this->ads->service($platform), Auth::id(), $days);
        if (!$result['success']) {
            return response()->json(['success' => false, 'message' => $result['error']], 422);
        }

        $file = sprintf('%s-ads-%s-to-%s.csv', $platform, $result['insights']['since'], $result['insights']['until']);

        return new Response($result['csv'], 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $file),
            'Cache-Control' => 'no-store',
        ]);
    }

    public function store(Request $request, string $platform): JsonResponse
    {
        abort_unless(in_array($platform, ['facebook', 'instagram'], true), 404);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'frequency' => ['required', Rule::in(AdReportSchedule::FREQUENCIES)],
            'weekday' => ['required_if:frequency,weekly', 'nullable', 'integer', 'between:0,6'],
            'day_of_month' => ['required_if:frequency,monthly', 'nullable', 'integer', 'between:1,28'],
            'send_time' => ['required', 'date_format:H:i'],
            'timezone' => ['required', 'timezone:all'],
            'period_days' => ['required', 'integer', Rule::in(AdReportSchedule::PERIODS)],
        ]);

        $account = AdAccountSelection::resolve(Auth::id(), 'facebook');
        $email = self::loginEmail($request);
        if (!$account || !$email) {
            return response()->json(['success' => false, 'message' => __('ads_campaigns.reports.no_account')], 422);
        }
        if (AdReportSchedule::where('user_id', Auth::id())->where('social_account_id', $account->id)->count() >= self::MAX_SCHEDULES) {
            return response()->json(['success' => false, 'message' => __('ads_campaigns.reports.limit', ['max' => self::MAX_SCHEDULES])], 422);
        }

        $schedule = new AdReportSchedule($validated + [
            'user_id' => Auth::id(),
            'social_account_id' => $account->id,
            'platform' => $platform,
            'recipient_email' => $email,
            'locale' => app()->getLocale() === 'ar' ? 'ar' : 'en',
            'status' => 'active',
        ]);
        $schedule->weekday = $validated['frequency'] === 'weekly' ? $validated['weekday'] : null;
        $schedule->day_of_month = $validated['frequency'] === 'monthly' ? $validated['day_of_month'] : null;
        $schedule->next_run_at = $schedule->nextRunAfter();
        $schedule->save();

        return response()->json(['success' => true, 'report' => self::present($schedule), 'message' => __('ads_campaigns.reports.created')]);
    }

    public function update(Request $request, string $platform, int $id): JsonResponse
    {
        $schedule = $this->own($platform, $id);
        $validated = $request->validate(['status' => ['required', Rule::in(['active', 'paused'])]]);

        $schedule->status = $validated['status'];
        if ($validated['status'] === 'active') {
            $schedule->next_run_at = $schedule->nextRunAfter();
        }
        $schedule->save();

        return response()->json(['success' => true, 'report' => self::present($schedule)]);
    }

    public function destroy(string $platform, int $id): JsonResponse
    {
        $this->own($platform, $id)->delete();

        return response()->json(['success' => true]);
    }

    /** Send one report right away (doesn't move its schedule). */
    public function send(string $platform, int $id): JsonResponse
    {
        $schedule = $this->own($platform, $id);
        $result = $this->reports->deliver($schedule);

        return response()->json([
            'success' => $result['success'],
            'report' => self::present($schedule->fresh()),
            'message' => $result['success'] ? __('ads_campaigns.reports.sent', ['email' => $schedule->recipient_email]) : $result['error'],
        ], $result['success'] ? 200 : 422);
    }

    private function own(string $platform, int $id): AdReportSchedule
    {
        abort_unless(in_array($platform, ['facebook', 'instagram'], true), 404);

        return AdReportSchedule::where('user_id', Auth::id())->where('platform', $platform)->findOrFail($id);
    }
}
