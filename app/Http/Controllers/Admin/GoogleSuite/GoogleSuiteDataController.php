<?php

namespace App\Http\Controllers\Admin\GoogleSuite;

use App\Http\Controllers\Controller;
use App\Models\GoogleProperty;
use App\Models\GoogleSuiteSetting;
use App\Services\Connections\ConnectionService;
use App\Services\GoogleSuite\Ga4Service;
use App\Services\GoogleSuite\GoogleSuitePresenter;
use App\Services\GoogleSuite\GoogleSuiteService;
use App\Services\GoogleSuite\SearchConsoleService;
use App\Support\Google\GoogleApiException;
use App\Support\Google\ReportRange;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * JSON (and CSV) endpoints behind the Google Suite pages. Every request is
 * validated, and every lookup runs through GoogleSuiteService with the
 * workspace owner's id - ids from another workspace 404.
 */
class GoogleSuiteDataController extends Controller
{
    private const EXPORT_ROWS = 5000;
    private const PREVIEW_ROWS = 50;

    public function __construct(private GoogleSuiteService $suite, private GoogleSuitePresenter $presenter)
    {
    }

    public function overview(Request $request): JsonResponse
    {
        return response()->json($this->suite->overview($this->userId($request), $this->range($request)));
    }

    public function ga4(Request $request): JsonResponse
    {
        return $this->respond($this->suite->ga4Dashboard($this->userId($request), $this->range($request)));
    }

    public function realtime(Request $request): JsonResponse
    {
        return $this->respond($this->suite->ga4Realtime($this->userId($request)));
    }

    public function searchConsole(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'type' => ['nullable', Rule::in(SearchConsoleService::SEARCH_TYPES)],
            'query' => ['nullable', 'string', 'max:200'],
            'page' => ['nullable', 'string', 'max:200'],
        ]);

        return $this->respond($this->suite->searchConsoleDashboard($this->userId($request), $this->range($request), array_filter($filters) + ['type' => 'web']));
    }

    public function inspect(Request $request): JsonResponse
    {
        $validated = $request->validate(['url' => ['required', 'url:http,https', 'max:2048']]);

        return $this->respond($this->suite->inspectUrl($this->userId($request), $validated['url'], app()->getLocale()));
    }

    /** Re-read which properties the Google account can access. */
    public function discover(Request $request): JsonResponse
    {
        try {
            $found = $this->suite->discover($this->userId($request));
        } catch (GoogleApiException $e) {
            return response()->json(['error' => $e->toArray()], 409);
        }

        return response()->json(['found' => $found, 'properties' => $this->properties($request)]);
    }

    public function select(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'service' => ['required', Rule::in(GoogleProperty::SERVICES)],
            'property_id' => ['nullable', 'integer'],
        ]);

        $property = $this->suite->select($this->userId($request), $validated['service'], $validated['property_id'] ?? null);

        return response()->json([
            'selected' => $property ? $this->presenter->property($property->fresh()) : null,
            'properties' => $this->properties($request),
        ]);
    }

    public function preferences(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'default_range' => ['required', Rule::in(GoogleSuiteSetting::RANGES)],
            'compare_previous' => ['required', 'boolean'],
            'widgets' => ['present', 'array'],
            'widgets.*' => [Rule::in(GoogleSuiteSetting::WIDGETS)],
            'sync_frequency' => ['required', Rule::in(GoogleSuiteSetting::SYNC_FREQUENCIES)],
        ]);
        $validated['widgets'] = array_values(array_unique($validated['widgets']));

        $saved = GoogleSuiteSetting::store($this->userId($request), $validated);

        return response()->json(['preferences' => collect($saved)->except('account')->all(), 'message' => __('google_suite.preferences.saved')]);
    }

    public function refresh(Request $request): JsonResponse
    {
        $results = $this->suite->sync($this->userId($request), manual: true);

        return response()->json(['results' => $results, 'properties' => $this->properties($request), 'synced_at' => now()->toIso8601String()]);
    }

    /** Ask Google whether the consent still works (token refresh + tokeninfo). */
    public function check(Request $request, ConnectionService $connections): JsonResponse
    {
        $connection = $this->suite->connection($this->userId($request));

        if (! $connection) {
            return response()->json(['account' => ['connected' => false]]);
        }

        try {
            $connections->validate($connection);
        } catch (\Throwable $e) {
            report($e);
        }

        return response()->json(['account' => $this->presenter->account($connection->fresh(), GoogleSuiteSetting::for($this->userId($request)))]);
    }

    public function reportPreview(Request $request): JsonResponse
    {
        [$service, $type, $filters] = $this->reportInput($request);

        return $this->respond($this->suite->report($this->userId($request), $service, $type, $this->range($request), self::PREVIEW_ROWS, $filters));
    }

    public function reportExport(Request $request): StreamedResponse|JsonResponse
    {
        [$service, $type, $filters] = $this->reportInput($request);
        $range = $this->range($request);
        $result = $this->suite->report($this->userId($request), $service, $type, $range, self::EXPORT_ROWS, $filters);

        if (isset($result['error'])) {
            return response()->json($result, 409);
        }

        $columns = $result['data']['columns'];
        $rows = $result['data']['rows'];
        $filename = sprintf('socialeaz-%s-%s-%s-to-%s.csv', $service === GoogleProperty::GA4 ? 'ga4' : 'search-console', str_replace('_', '-', $type), ...$range->current());

        return response()->streamDownload(function () use ($columns, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel reads Arabic correctly
            fputcsv($out, array_map(fn ($c) => __('google_suite.columns.' . $c['key']), $columns));

            foreach ($rows as $row) {
                fputcsv($out, array_map(fn ($c) => $this->csvCell($row[$c['key']] ?? ''), $columns));
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'no-store']);
    }

    /** Disconnect the Suite consent (owner only - UseSellerWorkspace blocks *.disconnect). */
    public function disconnect(Request $request, ConnectionService $connections): JsonResponse|RedirectResponse
    {
        $connection = $this->suite->connection($this->userId($request));
        abort_unless($connection, 404);

        $connections->disconnect($connection);

        return $request->expectsJson()
            ? response()->json(['message' => __('google_suite.settings.disconnected')])
            : redirect()->route('admin.google.integrations')->with('success', __('google_suite.settings.disconnected'));
    }

    private function reportInput(Request $request): array
    {
        $validated = $request->validate([
            'service' => ['required', Rule::in(GoogleProperty::SERVICES)],
            'type' => ['required', 'string'],
            'search_type' => ['nullable', Rule::in(SearchConsoleService::SEARCH_TYPES)],
        ]);
        $catalog = $validated['service'] === GoogleProperty::GA4 ? Ga4Service::REPORTS : SearchConsoleService::REPORTS;
        abort_unless(array_key_exists($validated['type'], $catalog), 422, __('google_suite.validation.report_type'));

        return [$validated['service'], $validated['type'], array_filter(['type' => $validated['search_type'] ?? null])];
    }

    private function range(Request $request): ReportRange
    {
        $validated = $request->validate([
            'range' => ['nullable', Rule::in([...array_keys(ReportRange::PRESETS), 'custom'])],
            'start' => ['nullable', 'required_if:range,custom', 'date_format:Y-m-d'],
            'end' => ['nullable', 'required_if:range,custom', 'date_format:Y-m-d'],
        ]);

        return ReportRange::make($validated['range'] ?? GoogleSuiteSetting::for($this->userId($request))['default_range'], $validated['start'] ?? null, $validated['end'] ?? null);
    }

    private function respond(array $result): JsonResponse
    {
        return response()->json($result);
    }

    private function properties(Request $request): array
    {
        $properties = GoogleProperty::ownedBy($this->userId($request))->orderBy('account_name')->orderBy('display_name')->get();

        return collect(GoogleProperty::SERVICES)->mapWithKeys(fn ($service) => [
            $service => $properties->where('service', $service)->values()->map(fn ($p) => $this->presenter->property($p))->all(),
        ])->all();
    }

    /** Spreadsheet formula injection guard (OWASP CSV injection). */
    private function csvCell(mixed $value): string
    {
        if (is_int($value) || is_float($value)) {
            return (string) (is_float($value) ? round($value, 6) : $value);
        }

        $value = (string) $value;

        return preg_match('/^[=+\-@\t\r]/', $value) ? "'" . $value : $value;
    }

    private function userId(Request $request): int
    {
        return (int) $request->user()->id;
    }
}
