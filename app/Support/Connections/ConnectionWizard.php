<?php

namespace App\Support\Connections;

use App\Models\SocialConnection;
use App\Services\Connections\ConnectionService;

/**
 * "Connect all recommended" (docs/connection-hub-design.md §10): walks the
 * user through each card's primary consent in turn - Meta, Google,
 * LinkedIn, X, TikTok. Server-driven: every consent is a full-page OAuth
 * redirect that lands back on the Hub, which then offers the next step.
 *
 * Session state is only the queue and what was skipped; whether a step is
 * done is re-read from the user's connections each time, so a cancelled
 * consent is simply offered again and a step finished elsewhere drops out.
 */
class ConnectionWizard
{
    public const ORDER = ['meta', 'google', 'linkedin', 'x', 'tiktok'];

    private const SESSION_KEY = 'connections.wizard';

    public function __construct(private ConnectionService $connections)
    {
    }

    /**
     * Recommended steps the user doesn't have working yet.
     *
     * @return array<int, array{platform: string, step: string, label: string}>
     */
    public function pending(int $userId): array
    {
        $usable = SocialConnection::where('user_id', $userId)
            ->whereIn('status', [SocialConnection::ACTIVE, SocialConnection::EXPIRING])
            ->get(['platform', 'step'])
            ->map(fn ($c) => "{$c->platform}:{$c->step}")
            ->all();

        $pending = [];

        foreach (array_intersect(self::ORDER, array_keys($this->connections->platforms())) as $platform) {
            $driver = $this->connections->driver($platform);
            $step = collect($driver->steps())->first(fn ($s) => $s['primary'] && $s['available']);

            if ($step && ! in_array("{$platform}:{$step['key']}", $usable, true)) {
                $pending[] = ['platform' => $platform, 'step' => $step['key'], 'label' => $driver->label()];
            }
        }

        return $pending;
    }

    /** Begin a run; returns the first step, or null when nothing is needed. */
    public function start(int $userId): ?array
    {
        $queue = $this->pending($userId);

        if ($queue === []) {
            $this->finish();

            return null;
        }

        session([self::SESSION_KEY => ['queue' => array_map(fn ($s) => $this->key($s), $queue), 'skipped' => []]]);

        return $queue[0];
    }

    /**
     * The Hub banner: progress and the next step. A completed run is
     * reported once (next = null) and then cleared.
     *
     * @return array{total: int, done: int, next: ?array}|null
     */
    public function state(int $userId): ?array
    {
        $run = session(self::SESSION_KEY);

        if (! $run) {
            return null;
        }

        $remaining = array_values(array_filter(
            $this->pending($userId),
            fn ($s) => in_array($this->key($s), $run['queue'], true) && ! in_array($this->key($s), $run['skipped'], true)
        ));

        if ($remaining === []) {
            $this->finish();
        }

        return [
            'total' => count($run['queue']),
            'done' => count($run['queue']) - count($remaining),
            'skipped' => count($run['skipped']),
            'next' => $remaining[0] ?? null,
        ];
    }

    public function skip(int $userId): void
    {
        $next = $this->state($userId)['next'] ?? null;

        if ($next) {
            $run = session(self::SESSION_KEY);
            $run['skipped'][] = $this->key($next);
            session([self::SESSION_KEY => $run]);
        }
    }

    public function finish(): void
    {
        session()->forget(self::SESSION_KEY);
    }

    private function key(array $step): string
    {
        return "{$step['platform']}:{$step['step']}";
    }
}
