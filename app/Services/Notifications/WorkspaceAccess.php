<?php

namespace App\Services\Notifications;

use App\Models\SellerTeamMember;
use App\Models\User;
use App\Models\Workspace;
use App\Services\SellerTeamService;
use App\Services\WorkspaceService;
use Illuminate\Http\Request;

/**
 * Who may hear about a workspace, and how a notification link opens it.
 *
 * A workspace is its own users row ("account"); its owner is a person (the account
 * itself for the default workspace, else users.workspace_owner_id). Teammates reach
 * it through accepted seller_team_members rows (owner_id = the account).
 */
class WorkspaceAccess
{
    /** The person who owns this account (an account row of a brand workspace is not a person). */
    public function owner(int $accountId): ?User
    {
        $account = User::find($accountId);

        return $account?->workspace_owner_id ? User::find($account->workspace_owner_id) : $account;
    }

    /** Accepted teammate row for this person in this workspace, if any. */
    public function membership(int $accountId, int $userId): ?SellerTeamMember
    {
        return SellerTeamMember::where('owner_id', $accountId)->where('user_id', $userId)->whereNotNull('accepted_at')->first();
    }

    /** Owner, or an accepted teammate who may at least read the module (when one is given). */
    public function canHear(int $accountId, User $user, ?string $module = null): bool
    {
        if ($this->owner($accountId)?->id === $user->id) {
            return true;
        }
        $member = $this->membership($accountId, $user->id);

        return $member !== null && ($module === null || $member->canUse($module));
    }

    /** @return User[] the owner and accepted admins */
    public function admins(int $accountId): array
    {
        $ids = SellerTeamMember::where('owner_id', $accountId)->where('role', SellerTeamMember::ROLE_ADMIN)
            ->whereNotNull('accepted_at')->whereNotNull('user_id')->pluck('user_id');

        return array_values(array_filter(array_merge([$this->owner($accountId)], User::whereIn('id', $ids)->get()->all())));
    }

    /** @return User[] the owner and accepted teammates with access to the module */
    public function members(int $accountId, ?string $module): array
    {
        $members = SellerTeamMember::where('owner_id', $accountId)->whereNotNull('accepted_at')->whereNotNull('user_id')->get()
            ->filter(fn (SellerTeamMember $m) => $module === null || $m->canUse($module));

        return array_values(array_filter(array_merge([$this->owner($accountId)], User::whereIn('id', $members->pluck('user_id'))->get()->all())));
    }

    /**
     * Opens the workspace for this person in their session (as the workspace picker would).
     * Returns false when they no longer have access.
     */
    public function enter(Request $request, User $user, ?int $accountId): bool
    {
        if (! $accountId) {
            return true;
        }
        $session = $request->session();
        if ($accountId === $user->id) {
            $session->forget('seller_workspace_member');
            app(WorkspaceService::class)->openDefault($request, $user);

            return true;
        }
        $owned = rescue(fn () => Workspace::where('account_id', $accountId)->where('owner_id', $user->id)->first(), null, false);
        if ($owned) {
            app(WorkspaceService::class)->open($request, $owned);

            return true;
        }
        $member = $this->membership($accountId, $user->id);
        if ($member && app(SellerTeamService::class)->usable($member)) {
            $session->put('seller_workspace_member', $member->id);
            $session->forget(WorkspaceService::SESSION_KEY);

            return true;
        }

        return false;
    }
}
