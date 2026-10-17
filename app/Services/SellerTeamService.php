<?php

namespace App\Services;

use App\Models\SellerTeamMember;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SellerTeamService
{
    public function __construct(private PackageAccessService $access, private PackageUsageService $usage) {}

    public function seats(User $owner): int
    {
        return 1 + SellerTeamMember::where('owner_id', $owner->id)->occupyingSeat()->count();
    }

    public function invite(User $owner, string $email, array $permissions): array
    {
        return $this->usage->locked($owner, function ($subscription) use ($owner, $email, $permissions) {
            $this->access->authorize($owner, 'team_management');
            $email = Str::lower(trim($email));
            if (Str::lower($owner->email) === $email) {
                throw ValidationException::withMessages(['email' => __('seller_team.owner_email')]);
            }
            $member = SellerTeamMember::where('owner_id', $owner->id)->where('email', $email)->first();
            if ($member && ($member->accepted_at || $member->expires_at?->isFuture())) {
                throw ValidationException::withMessages(['email' => __('seller_team.duplicate')]);
            }
            $this->usage->check($subscription, 'team_members', $this->seats($owner));
            $token = Str::random(64);
            $member ??= new SellerTeamMember(['owner_id' => $owner->id, 'email' => $email]);
            $member->fill(['permissions' => $permissions, 'token_hash' => hash('sha256', $token), 'expires_at' => now()->addDays(7), 'accepted_at' => null, 'user_id' => null])->save();

            return [$member, $token];
        });
    }

    public function accept(User $user, string $token): SellerTeamMember
    {
        $invitation = SellerTeamMember::where('token_hash', hash('sha256', $token))->firstOrFail();

        return $this->usage->locked($invitation->owner, function ($subscription) use ($user, $invitation, $token) {
            $member = SellerTeamMember::whereKey($invitation->id)->lockForUpdate()->firstOrFail();
            abort_unless(hash_equals((string) $member->token_hash, hash('sha256', $token)) && ! $member->accepted_at && $member->expires_at?->isFuture(), 404);
            abort_unless(Str::lower($user->email) === Str::lower($member->email) && $user->id !== $member->owner_id && ! $user->isTeamMember(), 403);
            $this->access->authorize($member->owner, 'team_management');
            // A downgrade can invalidate invitations already occupying a seat.
            $this->usage->check($subscription, 'team_members', $this->seats($member->owner), 0);
            $member->update(['user_id' => $user->id, 'accepted_at' => now(), 'token_hash' => null]);

            return $member;
        });
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
        return $limit === -1 || 1 + SellerTeamMember::where('owner_id', $owner->id)->whereNotNull('accepted_at')->where('id', '<=', $member->id)->count() <= $limit;
    }
}
