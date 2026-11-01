<?php

namespace App\Support\Connections;

use App\Models\SocialConnection;
use App\Services\Connections\AuthorizationPlanner;
use App\Services\Connections\CapabilityCatalog;
use App\Services\Connections\ConnectionService;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * A guided authorization (docs/connection-capabilities.md §4): the consents
 * the planner chose for the capabilities a customer picked, one full-page
 * provider sign-in at a time, back on the Hub between them.
 *
 * The session only holds what was asked for and when each consent was
 * started. Whether a step finished, and which capabilities were granted, is
 * re-read from the user's connections every time: a step counts as done only
 * when its callback recorded the consent after the step began, and a
 * capability counts as connected only when the provider granted its scopes.
 */
class AuthorizationFlow
{
    private const SESSION_KEY = 'connections.authorization';

    /** Minutes a flow stays alive after its last step began. */
    private const TTL_MINUTES = 30;

    public function __construct(private ConnectionService $connections, private AuthorizationPlanner $planner, private CapabilityCatalog $catalog)
    {
    }

    /** Begin a flow; returns its plan (steps may be empty when nothing is needed). */
    public function start(int $userId, string $platform, array $capabilities): array
    {
        $plan = $this->planner->plan($userId, $platform, $capabilities);

        // One guided run at a time.
        app(ConnectionWizard::class)->finish();

        session([self::SESSION_KEY => [
            'user_id' => $userId,
            'platform' => $platform,
            'capabilities' => array_values(array_unique($capabilities)),
            'steps' => array_column($plan['steps'], 'key'),
            'attempts' => [],
            'skipped' => [],
            'expires_at' => now()->addMinutes(self::TTL_MINUTES)->timestamp,
        ]]);

        Log::info('Connection authorization started.', ['user_id' => $userId, 'platform' => $platform, 'capabilities' => $capabilities, 'steps' => array_column($plan['steps'], 'key')]);

        return $plan;
    }

    /** Start the next step's provider consent, or null when none is left. */
    public function continue(int $userId): ?Response
    {
        $state = $this->state($userId);

        if (! $state || ! $state['next']) {
            return null;
        }

        $run = session(self::SESSION_KEY);
        $step = $state['next']['key'];
        $run['attempts'][$step] = now()->timestamp;
        $run['expires_at'] = now()->addMinutes(self::TTL_MINUTES)->timestamp;
        session([self::SESSION_KEY => $run]);

        Log::info('Connection authorization step began.', ['user_id' => $userId, 'platform' => $run['platform'], 'step' => $step]);

        return $this->connections->connect($run['platform'], $step);
    }

    public function skip(int $userId): void
    {
        $next = $this->state($userId)['next'] ?? null;

        if ($next) {
            $run = session(self::SESSION_KEY);
            $run['skipped'][] = $next['key'];
            session([self::SESSION_KEY => $run]);
        }
    }

    public function finish(): void
    {
        session()->forget(self::SESSION_KEY);
    }

    /**
     * Progress for the Hub, or null when no flow is running for this user.
     *
     * @return array{platform: string, label: string, capabilities: string[],
     *               steps: array<int, array{key: string, label: string, description: string, capabilities: string[], state: string}>,
     *               next: ?array, complete: bool, done: int, total: int,
     *               results: array<int, array{key: string, status: string}>, missing: string[]}|null
     */
    public function state(int $userId): ?array
    {
        $run = session(self::SESSION_KEY);

        if (! $run || (int) $run['user_id'] !== $userId || $run['expires_at'] < now()->timestamp) {
            if ($run) {
                $this->finish();
            }

            return null;
        }

        $platform = $run['platform'];
        $driver = $this->connections->driver($platform);
        $connections = SocialConnection::where(['user_id' => $userId, 'platform' => $platform])->get();
        $rows = collect($this->catalog->forUser($userId, $platform, $connections))->keyBy('key');
        $driverSteps = collect($driver->steps())->keyBy('key');
        $selected = $run['capabilities'];

        $steps = [];

        foreach ($run['steps'] as $key) {
            $grants = collect(config("connections.catalog.{$platform}", []))
                ->filter(fn ($entry, $capability) => ($entry['step'] ?? null) === $key && in_array($capability, $selected, true))
                ->keys()
                ->values()
                ->all();

            $steps[] = [
                'key' => $key,
                'label' => $driverSteps[$key]['label'] ?? $key,
                'description' => $driverSteps[$key]['description'] ?? '',
                'capabilities' => $grants,
                'state' => $this->stepState($key, $grants, $run, $connections, $rows),
            ];
        }

        $next = collect($steps)->first(fn ($s) => ! in_array($s['state'], ['done', 'skipped'], true));
        $results = collect($selected)
            ->filter(fn ($capability) => $rows->has($capability))
            ->map(fn ($capability) => ['key' => $capability, 'status' => $rows[$capability]['status']])
            ->values()
            ->all();

        return [
            'platform' => $platform,
            'label' => $driver->label(),
            'capabilities' => $selected,
            'steps' => $steps,
            'next' => $next,
            'complete' => $next === null,
            'done' => collect($steps)->whereIn('state', ['done', 'skipped'])->count(),
            'total' => count($steps),
            'results' => $results,
            // Still authorizable after the run: offered as "Complete missing".
            'missing' => collect($results)
                ->filter(fn ($r) => in_array($r['status'], CapabilityCatalog::SELECTABLE, true))
                ->pluck('key')
                ->values()
                ->all(),
        ];
    }

    /** done | failed | skipped | pending */
    private function stepState(string $key, array $grants, array $run, $connections, $rows): string
    {
        // Granted already (e.g. finished in another tab): nothing to do.
        if ($grants && collect($grants)->every(fn ($c) => ($rows[$c]['status'] ?? null) === CapabilityCatalog::CONNECTED)) {
            return 'done';
        }

        $attempt = $run['attempts'][$key] ?? null;

        if ($attempt !== null) {
            // The callback records the consent (ConnectionRecorder) after the
            // step began; a cancelled or failed consent records nothing.
            $recorded = $connections->contains(fn ($c) => $c->step === $key
                && $c->isUsable()
                && $c->last_error === null
                && $c->updated_at !== null && $c->updated_at->timestamp >= $attempt);

            if ($recorded) {
                return 'done';
            }
        }

        if (in_array($key, $run['skipped'], true)) {
            return 'skipped';
        }

        return $attempt !== null ? 'failed' : 'pending';
    }
}
