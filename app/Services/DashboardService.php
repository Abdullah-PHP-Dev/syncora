<?php

namespace App\Services;

use App\Models\User;

class DashboardService
{
    public function viewName(User $user): string
    {
        if ($user->hasRole('admin')) {
            return 'admin.team-dashboard';
        }

        if ($user->hasRole('customer_support')) {
            return 'team.support-dashboard';
        }

        abort_unless($user->hasRole('seller'), 403);

        return 'admin.dashboard';
    }

    public function url(User $user): string
    {
        // Validate access using the same policy as dashboard rendering.
        $this->viewName($user);

        return route('dashboard', absolute: false);
    }
}
