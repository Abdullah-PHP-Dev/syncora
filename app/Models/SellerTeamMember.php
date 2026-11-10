<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SellerTeamMember extends Model
{
    public const ROLE_ADMIN = 'admin';

    public const ROLE_MEMBER = 'member';

    /**
     * Owner-managed pages an Admin may also use (inside the owner's plan).
     * Billing, profile, integrations and workspace settings stay with the owner.
     */
    public const ADMIN_ROUTES = [
        'dashboard', 'admin.tickets.*',
        'admin.connections.*', 'admin.social-accounts.*', 'admin.post-accounts.*',
        'admin.messaging.auth.*', 'admin.messaging.channels.*',
        'admin.seller-team.index', 'admin.seller-team.store', 'admin.seller-team.update', 'admin.seller-team.destroy',
    ];

    /** Pages every teammate may open inside a workspace (documentation, no workspace data). */
    public const MEMBER_ROUTES = ['admin.help-center.*'];

    /** Every route this teammate may open regardless of module permissions (the plan still applies). */
    public function allowsRoute(string $route): bool
    {
        return \Illuminate\Support\Str::is(self::MEMBER_ROUTES, $route) || ($this->isAdmin() && \Illuminate\Support\Str::is(self::ADMIN_ROUTES, $route));
    }

    protected $fillable = ['owner_id', 'user_id', 'email', 'name', 'role', 'token_hash', 'expires_at', 'accepted_at', 'permissions'];

    protected $hidden = ['token_hash'];

    protected $attributes = ['role' => self::ROLE_MEMBER];

    protected function casts(): array
    {
        return ['permissions' => 'array', 'expires_at' => 'datetime', 'accepted_at' => 'datetime'];
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /** The workspace this access is for (a member joins workspaces one row at a time). */
    public function workspace()
    {
        return $this->hasOne(Workspace::class, 'account_id', 'owner_id');
    }

    /** The workspace's name; a default workspace's account carries the owner's personal name. */
    public function getWorkspaceNameAttribute(): string
    {
        return (string) (rescue(fn () => $this->workspace?->name, null, false) ?? $this->owner?->name);
    }

    public function isPending(): bool
    {
        return ! $this->accepted_at && (bool) $this->expires_at?->isFuture();
    }

    public function scopeOccupyingSeat($query)
    {
        return $query->where(fn ($query) => $query->whereNotNull('accepted_at')->orWhere('expires_at', '>', now()));
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    /** none / read / write. An Admin manages every module; the owner's plan still decides which modules exist. */
    public function permission(string $module): string
    {
        return $this->isAdmin() ? 'write' : ($this->permissions[$module] ?? 'none');
    }

    public function canUse(string $module, bool $write = false): bool
    {
        $level = $this->permission($module);

        return $level === 'write' || ($level === 'read' && ! $write);
    }

    /** Every assignable module set to write (stored on Admin rows so older readers agree). */
    public static function fullPermissions(): array
    {
        return array_fill_keys(array_keys(array_diff_key(config('package_access.modules'), ['team_management' => true])), 'write');
    }
}
