<?php

namespace App\Models\Crm\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * Workspace isolation for CRM records.
 *
 * Inside a request the authenticated user IS the workspace account
 * (UseSellerWorkspace swaps it per request), so every query is limited to
 * that workspace and every new record is stamped with it. Route model binding
 * goes through the same scope, so another workspace's id is a 404.
 *
 * Without a signed-in user (queue jobs, listeners, console) nothing is
 * scoped automatically: that code must call forWorkspace() explicitly.
 */
trait BelongsToWorkspace
{
    public static function bootBelongsToWorkspace(): void
    {
        static::addGlobalScope('workspace', function (Builder $query) {
            if (Auth::hasUser()) {
                $query->where($query->getModel()->qualifyColumn('workspace_id'), Auth::id());
            }
        });

        // Teammates set to "own" see only their records (CRM_VISIBILITY columns).
        static::addGlobalScope('visibility', fn (Builder $query) => \App\Services\Crm\CrmVisibility::apply($query, $query->getModel()));

        static::creating(function ($model) {
            if (! $model->workspace_id && Auth::hasUser()) {
                $model->workspace_id = Auth::id();
            }
        });
    }

    /**
     * Bound without the workspace scope: binding runs before UseSellerWorkspace
     * swaps the identity. EnsureCrmWorkspace (on every CRM route) checks the
     * record's workspace after the swap.
     */
    public function resolveRouteBinding($value, $field = null)
    {
        return $this->withoutGlobalScope('workspace')->where($field ?? $this->getRouteKeyName(), $value)->first();
    }

    public function scopeForWorkspace(Builder $query, int $workspaceId): Builder
    {
        return $query->withoutGlobalScope('workspace')->where($this->qualifyColumn('workspace_id'), $workspaceId);
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(User::class, 'workspace_id');
    }
}
