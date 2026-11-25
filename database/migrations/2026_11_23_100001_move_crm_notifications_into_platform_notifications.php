<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * CRM alerts move into the platform notification system:
 *  - each person's CRM alert choices (crm_settings.settings.notifications) become
 *    notification_preferences rows for that workspace;
 *  - unread CRM bell items from the last 30 days are copied into user_notifications.
 *
 * Nothing is deleted: crm_settings and crm_notifications keep their data, so a rollback
 * (which removes only the copied rows) loses nothing. Re-running skips rows already copied.
 */
return new class extends Migration
{
    private const EVENTS = ['lead_assigned', 'lead_unassigned', 'deal_assigned', 'deal_won', 'deal_lost', 'task_reminder'];

    public function up(): void
    {
        if (! Schema::hasTable('notification_preferences') || ! Schema::hasTable('crm_settings')) {
            return;
        }

        DB::table('crm_settings')->orderBy('id')->each(function ($row) {
            $settings = json_decode((string) $row->settings, true) ?: [];
            foreach ((array) ($settings['notifications'] ?? []) as $userId => $events) {
                if (! DB::table('users')->where('id', (int) $userId)->exists()) {
                    continue;
                }
                foreach ((array) $events as $event => $choice) {
                    if (! in_array($event, self::EVENTS, true) || ! is_array($choice)) {
                        continue;
                    }
                    DB::table('notification_preferences')->insertOrIgnore([
                        'user_id' => (int) $userId, 'workspace_id' => (int) $row->workspace_id, 'scope' => 'event', 'key' => 'crm.'.$event,
                        'in_app' => array_key_exists('in_app', $choice) ? (bool) $choice['in_app'] : null,
                        'email' => array_key_exists('email', $choice) ? ($choice['email'] ? 'immediate' : 'off') : null,
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
            }
        });

        if (! Schema::hasTable('crm_notifications') || ! Schema::hasTable('user_notifications')) {
            return;
        }
        DB::table('crm_notifications')->whereNull('read_at')->where('created_at', '>=', now()->subDays(30))->orderBy('id')
            ->each(function ($n) {
                $key = 'legacy-crm:'.$n->id;
                if (DB::table('user_notifications')->where('user_id', $n->user_id)->where('group_key', $key)->exists()
                    || ! DB::table('users')->where('id', $n->user_id)->exists()) {
                    return;
                }
                DB::table('user_notifications')->insert([
                    'user_id' => $n->user_id, 'workspace_id' => $n->workspace_id, 'event' => 'crm.'.$n->event, 'category' => 'crm',
                    'priority' => in_array($n->event, ['lead_assigned', 'lead_unassigned', 'task_reminder'], true) ? 1 : 2,
                    'params' => json_encode(['legacy_title' => $n->title, 'legacy_body' => $n->body]), 'url' => $n->url,
                    'group_key' => $key, 'group_count' => 1, 'created_at' => $n->created_at, 'updated_at' => now(),
                ]);
            });
    }

    public function down(): void
    {
        if (Schema::hasTable('user_notifications')) {
            DB::table('user_notifications')->where('group_key', 'like', 'legacy-crm:%')->delete();
        }
        if (Schema::hasTable('notification_preferences')) {
            DB::table('notification_preferences')->where('scope', 'event')->where('key', 'like', 'crm.%')->delete();
        }
    }
};
