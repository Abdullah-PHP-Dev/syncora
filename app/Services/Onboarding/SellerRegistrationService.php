<?php

namespace App\Services\Onboarding;

use App\Models\BusinessProfile;
use App\Models\User;
use App\Services\SubscriptionService;
use App\Services\WorkspaceService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Self-service sign-up: one account = one organization = its default workspace.
 *
 * The seller user IS the tenant every module scopes by (user_id); team members
 * enter it through SellerTeamMember (UseSellerWorkspace) and see its name as the
 * workspace name. So the organization name lives on users.name and the
 * business profile, and no separate organization/workspace rows are created.
 */
class SellerRegistrationService
{
    public function __construct(private SubscriptionService $subscriptions) {}

    /**
     * @throws FreePlanUnavailable when the administrator has no active free plan;
     *                             nothing is persisted in that case.
     */
    public function register(string $organization, string $email, string $password): User
    {
        $organization = trim(strip_tags($organization));

        return DB::transaction(function () use ($organization, $email, $password) {
            // Checked inside the transaction so a plan deactivated mid-request rolls back too.
            if (! $this->subscriptions->freePlan()) {
                throw new FreePlanUnavailable;
            }

            $user = User::create([
                'name' => $organization,
                'email' => $email,
                'password' => Hash::make($password),
            ]);
            $user->assignRole('seller');

            BusinessProfile::create(['user_id' => $user->id, 'business_name' => $organization]);
            // The account is the organization's default workspace; more can be added within the plan.
            app(WorkspaceService::class)->ensureDefault($user);

            // Existing mechanism: subscription + trial cycle from the plan's own
            // trial_days; entitlements are read live from the bundle afterwards.
            $this->subscriptions->assignFreeTrial($user);

            return $user;
        });
    }
}
