<?php

namespace App\Services\Crm;

use App\Models\Crm\Setting;
use Illuminate\Support\Str;

/** Workspace CRM settings (S-01, S-07, S-10) with defaults. */
class CrmSettings
{
    public const DEFAULTS = [
        'member_visibility' => 'all',   // default for teammates: all | own
        'member_overrides' => [],       // seller_team_members.id => all | own
        'idle_days' => 7,
        'require_lost_reason' => false,
        'auto_create_contacts' => false, // new Inbox conversations become contacts
        // S-03: lists people pick from. Built-in entries are translation keys; anything else is the workspace's own label.
        'sources' => ['instagram', 'whatsapp', 'facebook', 'tiktok', 'snapchat', 'x', 'linkedin', 'google_ads', 'website', 'email', 'referral', 'phone', 'walk_in', 'other'],
        'extra_statuses' => [],          // open lead statuses between Contacted and Qualified, e.g. "Nurturing"
        'disqualify_reasons' => ['budget', 'not_interested', 'spam', 'duplicate', 'competitor', 'no_response', 'outside_area', 'other'],
        'lost_reasons' => ['price', 'competitor', 'no_budget', 'no_response', 'timing', 'product_fit'],
        'win_reasons' => ['price', 'quality', 'delivery', 'service', 'relationship', 'product_fit'],
        // S-06 lead scoring (see LeadScorer).
        'scoring' => null,
        // S-11 AI features.
        'ai' => ['brief' => true, 'ask' => true, 'qualification' => true, 'intent_signal' => true],
        // S-12 per-member notifications: user id => event => [in_app, email].
        'notifications' => [],
    ];

    private static array $memo = [];

    public static function for(int $workspaceId): array
    {
        return self::$memo[$workspaceId] ??= array_replace(self::DEFAULTS, Setting::forWorkspace($workspaceId)->first()?->settings ?? []);
    }

    public static function save(int $workspaceId, array $values): array
    {
        $row = Setting::forWorkspace($workspaceId)->first() ?? new Setting(['workspace_id' => $workspaceId]);
        $row->workspace_id = $workspaceId;
        // Lists replace wholesale (array_replace would merge them by index).
        $row->settings = array_merge(self::DEFAULTS, $row->settings ?? [], array_intersect_key($values, self::DEFAULTS));
        $row->save();
        unset(self::$memo[$workspaceId]);

        return self::for($workspaceId);
    }

    /** Secret token for the website capture endpoint (created on first use, rotatable). */
    public static function captureToken(int $workspaceId, bool $rotate = false): string
    {
        $row = Setting::forWorkspace($workspaceId)->first() ?? new Setting(['workspace_id' => $workspaceId, 'settings' => []]);
        $row->workspace_id = $workspaceId;
        if ($rotate || ! $row->capture_token) {
            $row->capture_token = Str::random(48);
            $row->save();
        }

        return $row->capture_token;
    }

    public static function flush(): void
    {
        self::$memo = [];
    }
}
