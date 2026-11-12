<?php

namespace App\Services\Crm;

use App\Models\Crm\Contact;
use App\Models\User;
use App\Services\PackageAccessService;
use App\Services\SellerTeamService;
use Illuminate\Validation\ValidationException;

/**
 * "CRM contacts" plan capacity, counted across the organization's workspaces
 * (plans are bought by the organization). Unset or -1 = unlimited, so plans
 * keep working until an admin sets a number.
 */
class CrmLimits
{
    public static function limit(User $account): int
    {
        $subscription = app(PackageAccessService::class)->subscription($account);
        $value = $subscription?->bundle?->entitlement_settings['crm_contacts'] ?? null;

        return is_numeric($value) ? (int) $value : -1;
    }

    public static function used(User $account): int
    {
        return Contact::withoutGlobalScopes()->whereNull('deleted_at')
            ->whereIn('workspace_id', app(SellerTeamService::class)->organizationAccounts($account))->count();
    }

    public static function ensureRoom(User $account, int $adding = 1): void
    {
        $limit = self::limit($account);
        if ($limit !== -1 && self::used($account) + $adding > $limit) {
            throw ValidationException::withMessages(['contacts' => __('crm.limits.reached', ['limit' => number_format($limit)])]);
        }
    }
}
