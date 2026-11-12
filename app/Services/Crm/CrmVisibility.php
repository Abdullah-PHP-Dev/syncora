<?php

namespace App\Services\Crm;

use App\Models\SellerTeamMember;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Record visibility (S-07): a teammate set to "own" sees only the records
 * they own (activities: assigned to or written by them). Owners and Admins
 * always see the whole workspace. Applied by BelongsToWorkspace to models
 * that declare CRM_VISIBILITY columns, and to route-bound records by
 * EnsureCrmWorkspace.
 */
class CrmVisibility
{
    /** The person whose records only are visible, or null for "all". */
    public static function restrictedTo(): ?int
    {
        if (! app()->bound('request') || ! Auth::hasUser()) {
            return null;
        }
        $member = request()->attributes->get('workspace_member');
        if (! $member instanceof SellerTeamMember || $member->isAdmin()) {
            return null;
        }
        $settings = CrmSettings::for((int) Auth::id());
        $mode = $settings['member_overrides'][$member->id] ?? $settings['member_visibility'];

        return $mode === 'own' ? (int) $member->user_id : null;
    }

    public static function apply(Builder $query, Model $model): void
    {
        $columns = defined($model::class.'::CRM_VISIBILITY') ? $model::CRM_VISIBILITY : [];
        if ($columns && ($person = self::restrictedTo())) {
            $query->where(function ($q) use ($columns, $person, $model) {
                foreach ($columns as $column) {
                    $q->orWhere($model->qualifyColumn($column), $person);
                }
            });
        }
    }

    public static function allows(Model $record): bool
    {
        $columns = defined($record::class.'::CRM_VISIBILITY') ? $record::CRM_VISIBILITY : [];
        $person = $columns ? self::restrictedTo() : null;

        return ! $person || collect($columns)->contains(fn ($c) => (int) $record->{$c} === $person);
    }
}
