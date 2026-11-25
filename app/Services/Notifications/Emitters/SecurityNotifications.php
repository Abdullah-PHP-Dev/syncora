<?php

namespace App\Services\Notifications\Emitters;

use App\Models\User;
use App\Services\Notifications\Notify;
use Illuminate\Support\Facades\DB;

/** SEC-02/04/05/07/08/09/10. Personal events: no workspace, the person themselves hears about it. */
class SecurityNotifications
{
    public static function userUpdated(User $user): void
    {
        if ($user->isWorkspaceAccount()) {
            return;
        }
        if ($user->wasChanged('password') && $user->getOriginal('password')) {
            Notify::event('security.password_changed', [
                'subject' => $user, 'users' => [$user->id], 'actor' => Support::actorId(),
                'params' => ['device' => Support::device()], 'dates' => ['when' => now()],
                'url' => Support::url('password.request'), 'dedup' => (string) now()->timestamp,
            ]);
        }
        if ($user->wasChanged('is_active') && $user->getOriginal('is_active') !== null) {
            $suspended = $user->is_active === false;
            Notify::event($suspended ? 'security.account_suspended' : 'security.account_reactivated', [
                'subject' => $user, 'users' => [$user->id], 'url' => Support::url($suspended ? 'home' : 'login'), 'dedup' => (string) now()->timestamp,
            ]);
        }
        if ($user->wasChanged('email_verified_at') && $user->email_verified_at && ! $user->getOriginal('email_verified_at')) {
            Notify::event('security.welcome', [
                'subject' => $user, 'users' => [$user->id], 'params' => ['name' => $user->name],
                'url' => Support::url('admin.connections.index'), 'dedup' => 'once',
            ]);
        }
    }

    /** Sanctum token or MCP sign-in grant created. */
    public static function tokenCreated($token): void
    {
        $user = $token->tokenable;
        if (! $user instanceof User) {
            return;
        }
        $workspace = $user->isWorkspaceAccount() ? $user->id : null;
        Notify::event('security.api_token_created', [
            'subject' => $user, 'workspace' => $workspace, 'users' => [$user->workspace_owner_id ?: $user->id],
            'params' => ['name' => $token->name], 'dates' => ['when' => now()],
            'url' => Support::url('admin.profiles.show', ['profile' => $user->id]), 'dedup' => 'token:'.$token->id,
        ]);
    }

    public static function tokenDeleted($token): void
    {
        $user = $token->tokenable;
        // Expired MCP grants are cleaned up by the system; only tell people about deliberate removals.
        if (! $user instanceof User || ($token->expires_at && $token->expires_at->isPast())) {
            return;
        }
        Notify::event('security.api_token_revoked', [
            'subject' => $user, 'users' => [$user->workspace_owner_id ?: $user->id], 'params' => ['name' => $token->name], 'dedup' => 'token:'.$token->id,
        ]);
    }

    /** SEC-05: a sign-in from a browser/OS + network not seen for this person before (first sign-in is never "new"). */
    public static function login(User $user): void
    {
        if ($user->isWorkspaceAccount()) {
            return;
        }
        $label = Support::device();
        $fingerprint = hash('sha256', $label.'|'.Support::network());
        $known = DB::table('user_login_devices')->where('user_id', $user->id);
        $hadAny = (clone $known)->exists();
        $inserted = DB::table('user_login_devices')->insertOrIgnore([
            'user_id' => $user->id, 'fingerprint' => $fingerprint, 'label' => $label, 'last_seen_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        if (! $inserted) {
            (clone $known)->where('fingerprint', $fingerprint)->update(['last_seen_at' => now()]);

            return;
        }
        if ($hadAny) {
            Notify::event('security.new_login', [
                'subject' => $user, 'users' => [$user->id], 'params' => ['device' => $label, 'network' => Support::network()],
                'dates' => ['when' => now()], 'url' => Support::url('admin.profiles.show', ['profile' => $user->id]), 'dedup' => $fingerprint,
            ]);
        }
    }
}
