<?php

namespace App\Services\Notifications\Emitters;

use App\Models\SellerTeamMember;
use App\Models\User;
use App\Services\Notifications\Notify;
use Illuminate\Support\Collection;

/** TEAM-01/02/05/06/07: invitations by email (no more copying links by hand), joins, removals, access changes. */
class TeamNotifications
{
    /**
     * @param  Collection<int, SellerTeamMember>  $rows      rows saved by this assignment
     * @param  Collection<int, SellerTeamMember>  $before    rows that existed before (keyed by workspace account)
     */
    public static function assigned(User $account, string $email, Collection $rows, Collection $before, ?string $token): void
    {
        $actor = Support::actorId();
        $inviter = User::whereKey($actor)->value('name') ?? (app(\App\Services\PackageAccessService::class)->billingUser($account) ?? $account)->name;
        $names = $rows->map(fn (SellerTeamMember $m) => $m->workspace_name)->filter()->unique()->implode(', ');
        $first = $rows->first();

        if ($token !== null && $first) {
            Notify::event('team.invitation', [
                'workspace' => (int) $first->owner_id, 'subject' => $first, 'actor' => $actor, 'emails' => [$email],
                'params' => ['inviter' => $inviter, 'workspace' => $names, 'role_key' => $first->role],
                'dates' => ['expires' => $first->expires_at],
                'dedup' => substr(hash('sha256', $token), 0, 16),
                // The one-time link is used for this email only: it travels encrypted in the job and is never stored.
                'transient' => ['url' => route('seller-workspaces.invitation', ['token' => $token])],
            ]);
        }
        foreach ($rows as $row) {
            $previous = $before->get($row->owner_id);
            if (! $row->user_id || ! $row->accepted_at) {
                continue;
            }
            if (! $previous || (! $previous->accepted_at && ! $previous->isPending())) {
                Notify::event('team.member_added', [
                    'workspace' => (int) $row->owner_id, 'subject' => $row, 'actor' => $actor, 'users' => [$row->user_id],
                    'params' => ['workspace' => $row->workspace_name, 'role_key' => $row->role, 'inviter' => $inviter],
                    'url' => Support::url('seller-workspaces.index'), 'dedup' => 'added:'.now()->timestamp,
                ]);
            } elseif ($previous->role !== $row->role || $previous->permissions != $row->permissions) {
                Notify::event('team.access_changed', [
                    'workspace' => (int) $row->owner_id, 'subject' => $row, 'actor' => $actor, 'users' => [$row->user_id],
                    'params' => ['workspace' => $row->workspace_name, 'role_key' => $row->role],
                    'url' => Support::url('seller-workspaces.index'), 'dedup' => 'changed:'.now()->timestamp,
                ]);
            }
        }
    }

    /** @param Collection<int, SellerTeamMember> $rows about to be deleted */
    public static function removed(Collection $rows): void
    {
        $joined = $rows->filter(fn (SellerTeamMember $m) => $m->user_id && $m->accepted_at);
        if ($joined->isEmpty()) {
            return;
        }
        // No workspace: they no longer belong to it, so it can't be scoped there.
        Notify::event('team.member_removed', [
            'subject' => $joined->first(), 'actor' => Support::actorId(), 'users' => [$joined->first()->user_id],
            'params' => ['workspace' => $joined->map(fn ($m) => $m->workspace_name)->unique()->implode(', ')],
            'url' => Support::url('seller-workspaces.index'), 'dedup' => 'removed:'.now()->timestamp,
        ]);
    }

    /** @param Collection<int, SellerTeamMember> $rows the pending rows just accepted */
    public static function accepted(User $user, Collection $rows): void
    {
        foreach ($rows as $row) {
            Notify::event('team.invitation_accepted', [
                'workspace' => (int) $row->owner_id, 'subject' => $row, 'actor' => $user->id,
                'params' => ['name' => $user->name, 'workspace' => $row->workspace_name],
                'url' => Support::url('admin.seller-team.index'), 'dedup' => 'accepted',
            ]);
        }
    }
}
