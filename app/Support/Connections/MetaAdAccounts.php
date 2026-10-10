<?php

namespace App\Support\Connections;

/**
 * Which Meta ad accounts a connect keeps: active Business ad accounts in
 * good standing only. GET /me/adaccounts also lists personal ad accounts
 * (no Business) and accounts Meta disabled or closed - none of those can
 * run campaigns here, so they are skipped and reported.
 *
 * Marketing API, AdAccount: account_status 1 = ACTIVE (2 disabled,
 * 3 unsettled, 7 pending risk review, 8 pending settlement, 9 in grace
 * period, 100 pending closure, 101 closed); disable_reason 0 = none.
 */
class MetaAdAccounts
{
    public const FIELDS = 'id,name,account_id,account_status,disable_reason,currency,business';

    /** Why an account is skipped, or null when it is usable. */
    public static function exclusionReason(array $account): ?string
    {
        if ((int) ($account['account_status'] ?? 0) !== 1) {
            return 'inactive';
        }

        if ((int) ($account['disable_reason'] ?? 0) !== 0) {
            return 'disabled';
        }

        if (empty($account['business']['id'])) {
            return 'personal';
        }

        return null;
    }

    /** The bare numeric id the Ads API template (act_{accountId}) expects. */
    public static function bareId(array $account): string
    {
        return (string) ($account['account_id'] ?? preg_replace('/^act_/', '', (string) ($account['id'] ?? '')));
    }

    /** "Skipped 2 personal ad accounts and 1 disabled one." - or ''. */
    public static function skippedSummary(array $reasonCounts): string
    {
        $labels = ['personal' => 'personal', 'disabled' => 'disabled', 'inactive' => 'inactive'];
        $parts = [];

        foreach ($labels as $reason => $label) {
            $n = $reasonCounts[$reason] ?? 0;
            if ($n > 0) {
                $parts[] = $n . ' ' . $label . ($parts ? ($n === 1 ? ' one' : '') : ($n === 1 ? ' ad account' : ' ad accounts'));
            }
        }

        if (! $parts) {
            return '';
        }

        $last = array_pop($parts);

        return 'Skipped ' . ($parts ? implode(', ', $parts) . ' and ' . $last : $last) . ' - only active Business ad accounts are used.';
    }
}
