<?php

namespace App\Support\Connections;

/**
 * Which Google Ads customers a connect keeps: enabled client accounts that
 * can run campaigns. customers:listAccessibleCustomers also returns
 * manager (MCC) accounts, test accounts and cancelled / suspended / closed
 * ones; Google refuses the detail query for accounts that aren't enabled
 * or reachable - none of those can run campaigns here.
 *
 * Google Ads API, Customer: status ENABLED | CANCELED | SUSPENDED | CLOSED.
 */
class GoogleAdsCustomers
{
    public const QUERY = 'SELECT customer.id, customer.descriptive_name, customer.currency_code, customer.manager, customer.test_account, customer.status FROM customer LIMIT 1';

    /** Why a customer is skipped (detail = googleAds:search customer row, [] when the query failed), or null. */
    public static function exclusionReason(array $detail): ?string
    {
        return match (true) {
            $detail === [] => 'unreachable',
            ! empty($detail['manager']) => 'manager',
            ! empty($detail['testAccount']) => 'test',
            ($detail['status'] ?? null) !== 'ENABLED' => 'inactive',
            default => null,
        };
    }

    /** "Skipped 1 manager account and 2 inactive ones." - or ''. */
    public static function skippedSummary(array $reasonCounts): string
    {
        $labels = [
            'manager' => ['manager (MCC) account', 'manager (MCC) accounts'],
            'inactive' => ['cancelled or suspended account', 'cancelled or suspended accounts'],
            'test' => ['test account', 'test accounts'],
            'unreachable' => ['account Google would not share details for', 'accounts Google would not share details for'],
        ];
        $parts = [];

        foreach ($labels as $reason => [$one, $many]) {
            $n = $reasonCounts[$reason] ?? 0;
            if ($n > 0) {
                $parts[] = $n . ' ' . ($n === 1 ? $one : $many);
            }
        }

        if (! $parts) {
            return '';
        }

        $last = array_pop($parts);

        return 'Skipped ' . ($parts ? implode(', ', $parts) . ' and ' . $last : $last) . ' - only enabled Google Ads accounts are used.';
    }
}
