<?php

namespace App\Services;

use App\Models\SellerTeamMember;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SellerTeamService
{
    public function __construct(private PackageAccessService $access, private PackageUsageService $usage) {}

    /**
     * Seats are the organization's: the owner plus every distinct person with access
     * to (or invited to) any of its workspaces. A member joins one workspace only,
     * but the plan's team_members limit covers all of them together.
     */
    public function seats(User $owner): int
    {
        return 1 + SellerTeamMember::whereIn('owner_id', $this->organizationAccounts($owner))->occupyingSeat()->distinct()->count('email');
    }

    /** The paying owner's own account and all of its workspace accounts. */
    public function organizationAccounts(User $account): array
    {
        $billing = $this->access->billingUser($account) ?? $account;

        return User::where('workspace_owner_id', $billing->id)->pluck('id')->prepend($billing->id)->unique()->values()->all();
    }

    /** Someone who already holds a seat in another workspace of the organization needs no new one. */
    private function holdsSeat(User $owner, string $email): bool
    {
        return SellerTeamMember::whereIn('owner_id', $this->organizationAccounts($owner))->occupyingSeat()
            ->where('email', $email)->exists();
    }

    /** Workspace accounts this person may assign: the owner all of them, an Admin only their own workspace. */
    public function assignableAccounts(User $account, ?SellerTeamMember $viewer = null): array
    {
        return $viewer ? [$viewer->owner_id] : $this->organizationAccounts($account);
    }

    /** One workspace, one role (kept for the single-workspace API and existing callers). */
    public function invite(User $owner, string $email, array $permissions, string $role = SellerTeamMember::ROLE_MEMBER): array
    {
        [$rows, $token] = $this->assign($owner, $email, null, [$owner->id => ['role' => $role, 'permissions' => $permissions]]);

        return [$rows->first(), $token];
    }

    /**
     * Gives one person access to several workspaces, each with its own role and modules.
     * Every workspace is one seller_team_members row (owner_id = that workspace's account),
     * so the existing per-row middleware isolates them. One seat per person, organization-wide.
     *
     * $assignments: [workspace account id => ['role' => admin|member, 'permissions' => [module => none|read|write]]]
     * $invite: true creates a new invitation; false edits: workspaces left out (within scope) are removed.
     *
     * @return array{0: \Illuminate\Support\Collection<int, SellerTeamMember>, 1: ?string}
     */
    public function assign(User $account, string $email, ?string $name, array $assignments, ?SellerTeamMember $viewer = null, bool $invite = true): array
    {
        $scope = $this->assignableAccounts($account, $viewer);
        abort_if(array_diff(array_keys($assignments), $scope) !== [], 403, __('seller_team.workspace_not_allowed'));

        return $this->usage->locked($account, function ($subscription) use ($account, $email, $name, $assignments, $viewer, $invite, $scope) {
            $this->access->authorize($account, 'team_management');
            $email = Str::lower(trim($email));
            $this->rejectOrganizationEmail($account, $email);
            $existing = SellerTeamMember::whereIn('owner_id', $scope)->where('email', $email)->get()->keyBy('owner_id');
            $live = $existing->filter(fn (SellerTeamMember $m) => $m->accepted_at || $m->isPending());
            if ($invite && $live->keys()->intersect(array_keys($assignments))->isNotEmpty()) {
                throw ValidationException::withMessages(['email' => __('seller_team.duplicate')]);
            }
            abort_if(! $invite && $existing->isEmpty(), 404);
            if ($viewer) {
                // Admins manage Members only: never Admins (themselves included), never grant Admin.
                abort_if($live->contains(fn (SellerTeamMember $m) => $m->isAdmin() || $m->id === $viewer->id), 403, __('seller_team.admin_cannot_manage'));
                abort_if(collect($assignments)->contains(fn ($a) => $a['role'] === SellerTeamMember::ROLE_ADMIN), 403, __('seller_team.owner_only_admin'));
            }
            if ($assignments !== [] && ! $this->holdsSeat($account, $email)) {
                $this->usage->check($subscription, 'team_members', $this->seats($account));
            }
            // Someone who already joined this organization gets new workspaces straight away.
            $joined = SellerTeamMember::whereIn('owner_id', $this->organizationAccounts($account))->where('email', $email)
                ->whereNotNull('accepted_at')->whereNotNull('user_id')->first();
            $token = null;
            $rows = collect();
            foreach ($assignments as $accountId => $assignment) {
                $row = $existing[$accountId] ?? new SellerTeamMember(['owner_id' => $accountId, 'email' => $email]);
                $admin = $assignment['role'] === SellerTeamMember::ROLE_ADMIN;
                $attributes = ['role' => $assignment['role'], 'permissions' => $admin ? SellerTeamMember::fullPermissions() : $assignment['permissions']];
                if ($name !== null && $name !== '') {
                    $attributes['name'] = $name;
                }
                if (! $live->has($accountId)) {
                    if ($joined) {
                        $attributes += ['user_id' => $joined->user_id, 'accepted_at' => now(), 'token_hash' => null];
                    } else {
                        // One link accepts every pending workspace of this organization.
                        $hash = $token === null ? hash('sha256', $token = Str::random(64)) : null;
                        $attributes += ['user_id' => null, 'accepted_at' => null, 'expires_at' => now()->addDays(7), 'token_hash' => $hash];
                    }
                }
                $row->fill($attributes)->save();
                $rows->push($row);
            }
            if (! $invite) {
                // Workspaces left out (within scope) lose this person. (Eloquent's except() matches model ids, not keys.)
                $existing->filter(fn (SellerTeamMember $m) => ! array_key_exists((int) $m->owner_id, $assignments))->each->delete();
            }

            return [$rows, $token];
        });
    }

    /** Removes a person from every workspace in scope (all of them for the owner). */
    public function remove(User $account, SellerTeamMember $target, ?SellerTeamMember $viewer = null): void
    {
        $scope = $this->assignableAccounts($account, $viewer);
        abort_unless(in_array($target->owner_id, $this->organizationAccounts($account), true), 404);
        $this->usage->locked($account, function () use ($scope, $target, $viewer) {
            $rows = SellerTeamMember::whereIn('owner_id', $scope)->where('email', $target->email)->get();
            abort_if($rows->isEmpty(), 403, __('seller_team.admin_cannot_manage'));
            abort_if($viewer && $rows->contains(fn (SellerTeamMember $m) => $m->isAdmin() || $m->id === $viewer->id), 403, __('seller_team.admin_cannot_manage'));
            $rows->each->delete();
        });
    }

    private function rejectOrganizationEmail(User $account, string $email): void
    {
        $taken = User::whereIn('id', $this->organizationAccounts($account))->pluck('email')->map(fn ($e) => Str::lower((string) $e));
        if ($taken->contains($email)) {
            throw ValidationException::withMessages(['email' => __('seller_team.owner_email')]);
        }
    }

    public function accept(User $user, string $token): SellerTeamMember
    {
        $invitation = SellerTeamMember::where('token_hash', hash('sha256', $token))->firstOrFail();

        return $this->acceptInvitation($user, $invitation);
    }

    /** This person's pending invitations to the same organization (all workspaces of one invite). */
    private function pendingSiblings(SellerTeamMember $invitation)
    {
        return SellerTeamMember::whereIn('owner_id', $this->organizationAccounts($invitation->owner))
            ->where('email', $invitation->email)->whereNull('accepted_at')->where('expires_at', '>', now());
    }

    /**
     * Accepts every workspace the organization invited this person to. Also used from the
     * workspace picker, where the invitation is matched by the signed-in email instead of the token.
     */
    public function acceptInvitation(User $user, SellerTeamMember $invitation): SellerTeamMember
    {
        return $this->usage->locked($invitation->owner, function ($subscription) use ($user, $invitation) {
            $member = SellerTeamMember::whereKey($invitation->id)->lockForUpdate()->firstOrFail();
            abort_unless($member->isPending(), 404);
            abort_unless(Str::lower($user->email) === Str::lower($member->email) && ! in_array($user->id, $this->organizationAccounts($member->owner), true) && ! $user->isTeamMember(), 403);
            $this->access->authorize($member->owner, 'team_management');
            // A downgrade can invalidate invitations already occupying a seat.
            $this->usage->check($subscription, 'team_members', $this->seats($member->owner), 0);
            $this->pendingSiblings($member)->update(['user_id' => $user->id, 'accepted_at' => now(), 'token_hash' => null]);

            return $member->fresh();
        });
    }

    public function decline(User $user, SellerTeamMember $invitation): void
    {
        abort_unless(! $invitation->accepted_at && Str::lower($user->email) === Str::lower($invitation->email), 404);
        $this->pendingSiblings($invitation)->update(['expires_at' => now(), 'token_hash' => null]);
        $invitation->update(['expires_at' => now(), 'token_hash' => null]);
    }

    public function usable(SellerTeamMember $member): bool
    {
        $owner = $member->owner;
        if (! $member->accepted_at || ! $owner || ! $this->access->allows($owner, 'team_management', true)) {
            return false;
        }
        $subscription = $this->access->subscription($owner);
        $limit = $this->usage->limit($subscription, 'team_members');

        // Keep a deterministic subset active after a downgrade; never grant extra seats.
        // Counted across the organization's workspaces, one seat per person, earliest first.
        if ($limit === -1) {
            return true;
        }
        $firsts = SellerTeamMember::whereIn('owner_id', $this->organizationAccounts($owner))->whereNotNull('accepted_at')
            ->groupBy('email')->selectRaw('email, MIN(id) as first_id')->pluck('first_id', 'email');
        $mine = $firsts[$member->email] ?? $member->id;

        return 1 + $firsts->filter(fn ($id) => $id <= $mine)->count() <= $limit;
    }
}
