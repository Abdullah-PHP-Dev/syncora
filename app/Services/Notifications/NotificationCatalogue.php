<?php

namespace App\Services\Notifications;

/** Reads config/notifications.php and fills in the defaults every event shares. */
class NotificationCatalogue
{
    public const CADENCES = ['immediate', 'daily', 'weekly', 'off'];

    private array $memo = [];

    public function has(string $event): bool
    {
        return array_key_exists($event, config('notifications.events', []));
    }

    public function get(string $event): array
    {
        if (isset($this->memo[$event])) {
            return $this->memo[$event];
        }
        // Event keys contain dots, so read the array rather than config("...events.$event").
        $raw = config('notifications.events', [])[$event] ?? null;
        if (! is_array($raw)) {
            throw new \InvalidArgumentException("Unknown notification event [$event].");
        }
        $priority = (int) ($raw['priority'] ?? 2);
        $mandatory = (bool) ($raw['mandatory'] ?? false);
        $config = $raw + [
            'category' => 'general',
            'priority' => $priority,
            'mandatory' => $mandatory,
            'in_app' => true,
            'email' => $priority <= 2 ? 'immediate' : 'daily',
            'recipients' => ['owner'],
            'module' => config('notifications.categories.'.($raw['category'] ?? '').'.module'),
            'include_actor' => false,
            'include_inactive' => false,
            'failure_safe' => false,
            'fallback' => false,
            'staff' => false,
            'group' => null,
            'delay' => null,
            'tone' => 'info',
            'section' => $priority <= 1 ? 'attention' : 'highlights',
        ];
        // A critical event is never held for a digest; a mandatory one cannot be switched off.
        $config['allowed'] = $raw['allowed'] ?? match (true) {
            $mandatory => [$config['email']],
            $priority === 0 => ['immediate', 'off'],
            default => self::CADENCES,
        };
        $config['key'] = $event;

        return $this->memo[$event] = $config;
    }

    /** @return array<string, array> every event, keyed by name */
    public function all(): array
    {
        return collect(array_keys(config('notifications.events', [])))->mapWithKeys(fn ($e) => [$e => $this->get($e)])->all();
    }

    public function categories(): array
    {
        return config('notifications.categories', []);
    }

    public function queueFor(array $config): string
    {
        return config('notifications.queues.'.($config['priority'] === 0 ? 'critical' : 'default'));
    }
}
