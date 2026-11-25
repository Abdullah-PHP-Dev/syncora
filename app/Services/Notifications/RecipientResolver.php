<?php

namespace App\Services\Notifications;

use App\Models\Notifications\NotificationEvent;
use App\Models\User;

/**
 * Turns an event into the people who should hear about it (rule R2: never "everyone").
 * Strategies come from the catalogue; every person is then checked against the
 * workspace (owner or accepted teammate with the module), activity and the actor rule.
 *
 * @return array<int, array{key: string, user: ?User, email: ?string}>
 */
class RecipientResolver
{
    public function __construct(private WorkspaceAccess $access) {}

    public function resolve(NotificationEvent $event, array $config): array
    {
        $payload = $event->payload ?? [];
        $workspace = $event->workspace_id ? (int) $event->workspace_id : null;
        $recipients = [];

        foreach ($config['recipients'] as $strategy) {
            $found = [];
            foreach ($this->strategy($strategy, $event, $config, $workspace, $payload) as $candidate) {
                if (is_string($candidate)) {
                    $found['e:'.$candidate] = ['key' => 'e:'.$candidate, 'user' => null, 'email' => $candidate];

                    continue;
                }
                $person = $this->person($candidate);
                if ($person && $this->allowed($person, $event, $config, $workspace)) {
                    $found['u:'.$person->id] = ['key' => 'u:'.$person->id, 'user' => $person, 'email' => $person->email];
                }
            }
            $recipients += $found;
            if ($config['fallback'] && $found !== []) {
                break;
            }
        }

        return array_values($recipients);
    }

    /** Still allowed at send time? (a removed teammate or a suspended user hears nothing more) */
    public function stillAllowed(?User $user, NotificationEvent $event, array $config): bool
    {
        if (! $user) {
            return true;
        }
        $fresh = $user->fresh();

        return $fresh && $this->allowed($fresh, $event, $config, $event->workspace_id ? (int) $event->workspace_id : null, true);
    }

    private function strategy(string $strategy, NotificationEvent $event, array $config, ?int $workspace, array $payload): array
    {
        return match ($strategy) {
            'users' => User::whereIn('id', $payload['users'] ?? [])->get()->all(),
            'emails' => $payload['emails'] ?? [],
            'owner' => $workspace ? array_filter([$this->access->owner($workspace)]) : [],
            'admins' => $workspace ? $this->access->admins($workspace) : [],
            'members' => $workspace ? $this->access->members($workspace, $config['module']) : [],
            // By role name (Spatie's role() scope throws when a role doesn't exist yet). Inactive staff are dropped by allowed().
            'staff' => User::whereHas('roles', fn ($q) => $q->whereIn('name', ['admin', 'customer_support']))->get()->all(),
            default => [],
        };
    }

    /** A brand workspace's account row stands for its owner. */
    private function person(User $user): ?User
    {
        return $user->workspace_owner_id ? User::find($user->workspace_owner_id) : $user;
    }

    private function allowed(User $person, NotificationEvent $event, array $config, ?int $workspace, bool $atSend = false): bool
    {
        if ($person->is_active === false && ! $config['include_inactive']) {
            return false;
        }
        if (! $atSend && ! $config['include_actor'] && $event->actor_id && (int) $event->actor_id === $person->id) {
            return false;
        }
        if ($config['staff']) {
            return rescue(fn () => $person->isTeamMember(), false, false);
        }

        return $workspace === null || $this->access->canHear($workspace, $person, $config['module']);
    }
}
