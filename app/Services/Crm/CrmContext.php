<?php

namespace App\Services\Crm;

use App\Models\SellerTeamMember;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Who and where, for CRM code. The workspace is the account the request runs
 * as; the actor is the real person (the owner, or the teammate working inside
 * the workspace), which is who owns and authors records.
 */
class CrmContext
{
    public function workspaceId(): int
    {
        return (int) Auth::id();
    }

    public function actorId(): int
    {
        // No person acting (webhooks, scheduler): the organization owner.
        return (int) (request()->attributes->get('workspace_actor')?->id ?? (Auth::user()?->workspace_owner_id ?: Auth::id()));
    }

    /** People who can own CRM records here: the organization owner and teammates with CRM access. */
    public function members(): array
    {
        $account = Auth::user();
        $ownerId = $account->workspace_owner_id ?: $account->id;
        $people = collect([User::find($ownerId)])->filter();

        $team = SellerTeamMember::with('user')->where('owner_id', $account->id)
            ->whereNotNull('accepted_at')->whereNotNull('user_id')->get()
            ->filter(fn (SellerTeamMember $m) => $m->user && $m->permission('crm') !== 'none')
            ->map->user;

        return $people->concat($team)->unique('id')->values()
            ->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name])->all();
    }

    public function memberIds(): array
    {
        return array_column($this->members(), 'id');
    }
}
