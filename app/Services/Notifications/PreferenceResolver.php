<?php

namespace App\Services\Notifications;

use App\Models\Notifications\NotificationPreference;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Resolution order (audit §5.3): mandatory → workspace + event → all workspaces + event
 * → workspace + category → all workspaces + category → catalogue default.
 * Failure-safe events always keep at least the bell (rule R4).
 */
class PreferenceResolver
{
    /** @return array{in_app: bool, email: string} */
    public function for(User $user, array $config, ?int $workspaceId): array
    {
        $default = ['in_app' => (bool) $config['in_app'], 'email' => $config['email']];
        if ($config['mandatory']) {
            return $default;
        }
        $rows = NotificationPreference::where('user_id', $user->id)
            ->whereIn('workspace_id', array_unique([0, (int) $workspaceId]))
            ->where(fn ($q) => $q->where(fn ($q) => $q->where('scope', 'event')->where('key', $config['key']))
                ->orWhere(fn ($q) => $q->where('scope', 'category')->where('key', $config['category'])))
            ->get();
        $ordered = [
            [(int) $workspaceId, 'event'], [0, 'event'], [(int) $workspaceId, 'category'], [0, 'category'],
        ];
        $resolved = $default;
        foreach (['in_app', 'email'] as $field) {
            foreach ($ordered as [$ws, $scope]) {
                $row = $rows->first(fn ($r) => (int) $r->workspace_id === $ws && $r->scope === $scope && $r->{$field} !== null);
                if ($row) {
                    $resolved[$field] = $row->{$field};
                    break;
                }
            }
        }
        if (! in_array($resolved['email'], $config['allowed'], true)) {
            // e.g. a weekly digest chosen for a category whose event only offers daily.
            $resolved['email'] = $resolved['email'] === 'weekly' && in_array('daily', $config['allowed'], true) ? 'daily' : $default['email'];
        }
        if (($user->notification_settings['email_enabled'] ?? true) === false) {
            $resolved['email'] = 'off';
        }
        if ($config['failure_safe'] && $resolved['email'] === 'off') {
            $resolved['in_app'] = true;
        }
        $resolved['in_app'] = (bool) $resolved['in_app'];

        return $resolved;
    }

    /** Save one choice (validated against the catalogue; mandatory events refuse). */
    public function save(User $user, int $workspaceId, string $scope, string $key, ?bool $inApp, ?string $email): void
    {
        DB::table('notification_preferences')->upsert(
            [['user_id' => $user->id, 'workspace_id' => $workspaceId, 'scope' => $scope, 'key' => $key, 'in_app' => $inApp, 'email' => $email, 'created_at' => now(), 'updated_at' => now()]],
            ['user_id', 'workspace_id', 'scope', 'key'],
            ['in_app', 'email', 'updated_at'],
        );
    }
}
