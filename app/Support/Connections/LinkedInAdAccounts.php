<?php

namespace App\Support\Connections;

/**
 * Which LinkedIn ad accounts a connect keeps: ACTIVE, owned by a Company
 * Page, not a test account, and where the member's role can run
 * campaigns. adAccountUsers?q=authenticatedUser also returns draft /
 * cancelled accounts, accounts owned by a person (reference
 * urn:li:person:...) and accounts the member can only view.
 *
 * LinkedIn Marketing API, Ad Accounts: status ACTIVE | DRAFT | CANCELED |
 * PENDING_DELETION | REMOVED; reference = owning organization or person;
 * test = test account. Ad Account Users: role ACCOUNT_BILLING_ADMIN |
 * ACCOUNT_MANAGER | CAMPAIGN_MANAGER | CREATIVE_MANAGER | VIEWER.
 */
class LinkedInAdAccounts
{
    public const MANAGING_ROLES = ['ACCOUNT_BILLING_ADMIN', 'ACCOUNT_MANAGER', 'CAMPAIGN_MANAGER'];

    /** Why an account is skipped ($detail = GET adAccounts/{id}, [] when it failed), or null. */
    public static function exclusionReason(array $detail, ?string $role): ?string
    {
        return match (true) {
            $detail === [] => 'unreachable',
            ($detail['status'] ?? null) !== 'ACTIVE' => 'inactive',
            ! empty($detail['test']) => 'test',
            self::organizationId($detail) === null => 'personal',
            $role !== null && ! in_array($role, self::MANAGING_ROLES, true) => 'view_only',
            default => null,
        };
    }

    /** The Company Page the account belongs to (sponsored posts are authored as it). */
    public static function organizationId(array $detail): ?string
    {
        return preg_match('#urn:li:organization:(\d+)#', (string) ($detail['reference'] ?? ''), $m) ? $m[1] : null;
    }

    /** "Skipped 1 personal ad account and 2 inactive ones." - or ''. */
    public static function skippedSummary(array $reasonCounts): string
    {
        $labels = [
            'personal' => ['personal ad account', 'personal ad accounts'],
            'inactive' => ['draft or cancelled account', 'draft or cancelled accounts'],
            'view_only' => ['account where you can only view', 'accounts where you can only view'],
            'test' => ['test account', 'test accounts'],
            'unreachable' => ['account LinkedIn would not share details for', 'accounts LinkedIn would not share details for'],
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

        return 'Skipped ' . ($parts ? implode(', ', $parts) . ' and ' . $last : $last) . ' - only active Company Page ad accounts you manage are used.';
    }
}
