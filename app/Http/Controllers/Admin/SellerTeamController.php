<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SellerTeamMember;
use App\Models\User;
use App\Services\PackageAccessService;
use App\Services\PackageUsageService;
use App\Services\SellerTeamService;
use App\Models\Workspace;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;

class SellerTeamController extends Controller
{
    public function index(Request $request, SellerTeamService $teams, PackageAccessService $access, PackageUsageService $usage)
    {
        $account = $request->user();
        // An Admin sees and manages their own workspace only; the owner sees the whole organization.
        $viewer = $this->viewer($request);
        $scope = $teams->assignableAccounts($account, $viewer);
        $workspaces = $this->workspaces($scope);
        $members = SellerTeamMember::whereIn('owner_id', $scope)->with('user')->orderBy('id')->get();
        $subscription = $access->subscription($account);
        $organizationOwner = $access->billingUser($account) ?? $account;
        $data = [
            'members' => $members,
            'people' => $this->people($members, $viewer),
            'workspaces' => $workspaces,
            'seats' => $teams->seats($account),
            'limit' => $subscription ? $usage->limit($subscription, 'team_members') : 0,
            'canInvite' => $access->allows($account, 'team_management', true),
            'modules' => $this->modules(),
            // Modules the plan doesn't include: assignable, but nobody can use them until it does.
            'planLocked' => array_values(array_filter(array_keys($this->modules()), fn ($m) => ! $access->allows($account, $m))),
            'viewer' => $viewer,
            'canAssignAdmin' => ! $viewer,
            'organizationOwner' => $organizationOwner,
        ];

        return $request->expectsJson() ? response()->json($data) : view('admin.seller-team.index', $data);
    }

    public function store(Request $request, SellerTeamService $teams)
    {
        $viewer = $this->viewer($request);
        if ($request->has('workspaces')) {
            $data = $request->validate(['email' => ['required', 'email', 'max:255'], 'name' => ['nullable', 'string', 'max:120'], ...$this->workspaceRules()]);
            $assignments = $this->assignments($data['workspaces']);
        } else {
            // Single-workspace form/API: the workspace currently open.
            $data = $request->validate(['email' => ['required', 'email', 'max:255'], 'name' => ['nullable', 'string', 'max:120'], ...$this->permissionRules()]);
            $assignments = [$request->user()->id => ['role' => $data['role'] ?? SellerTeamMember::ROLE_MEMBER, 'permissions' => $data['permissions'] ?? []]];
        }
        [$rows, $token] = $teams->assign($request->user(), $data['email'], $data['name'] ?? null, $assignments, $viewer);
        $url = $token ? route('seller-workspaces.invitation', ['token' => $token]) : null;
        $message = $url ? __('seller_team.invitation_created') : __('seller_team.added_to_workspaces');

        return $request->expectsJson()
            ? response()->json(['member' => $rows->first(), 'members' => $rows->values(), 'invitation_url' => $url], 201)
            : back()->with('invitation_url', $url)->with('success', $message);
    }

    public function update(Request $request, SellerTeamMember $member, SellerTeamService $teams, PackageUsageService $usage, PackageAccessService $access)
    {
        $viewer = $this->viewer($request);
        abort_unless(in_array($member->owner_id, $teams->organizationAccounts($request->user()), true), 404);
        if ($request->has('workspaces')) {
            // Workspace assignments for this person: added, changed, or removed (within the viewer's scope).
            $data = $request->validate(['name' => ['nullable', 'string', 'max:120'], ...$this->workspaceRules()]);
            [, $token] = $teams->assign($request->user(), $member->email, $data['name'] ?? null, $this->assignments($data['workspaces']), $viewer, false);
            $url = $token ? route('seller-workspaces.invitation', ['token' => $token]) : null;

            return $request->expectsJson()
                ? response()->json(['members' => SellerTeamMember::whereIn('owner_id', $teams->assignableAccounts($request->user(), $viewer))->where('email', $member->email)->get(), 'invitation_url' => $url])
                : back()->with('invitation_url', $url)->with('success', __('seller_team.saved'));
        }
        abort_unless(in_array($member->owner_id, $teams->assignableAccounts($request->user(), $viewer), true), 404);
        $this->guardManageable($request, $member);
        $data = $request->validate($this->permissionRules());
        $role = $data['role'] ?? $member->role;
        abort_if($role === SellerTeamMember::ROLE_ADMIN && $viewer, 403, __('seller_team.owner_only_admin'));
        $usage->locked($request->user(), function () use ($access, $request, $member, $data, $role) {
            $access->authorize($request->user(), 'team_management');
            $member->update(['role' => $role, 'permissions' => $role === SellerTeamMember::ROLE_ADMIN ? SellerTeamMember::fullPermissions() : ($data['permissions'] ?? [])]);
        });

        return $request->expectsJson() ? response()->json(['member' => $member->fresh()]) : back()->with('success', __('seller_team.saved'));
    }

    /** Removes the person from every workspace the viewer manages (all of them for the owner). */
    public function destroy(Request $request, SellerTeamMember $member, SellerTeamService $teams)
    {
        // Removing access remains available after expiry or downgrade.
        $teams->remove($request->user(), $member, $this->viewer($request));

        return $request->expectsJson() ? response()->noContent() : back()->with('success', __('seller_team.removed'));
    }

    /**
     * The link the owner shares. Works signed out:
     *  join   - no account for the invited email yet: set a name and password here;
     *  signin - an account exists: sign in, then come straight back here;
     *  accept - signed in with the invited email;
     *  wrong  - signed in with another email;
     *  invalid- unknown, used or expired link.
     */
    public function invitation(Request $request, string $token)
    {
        $member = $this->pendingInvitation($token);
        if (! $member) {
            return response()->view('admin.seller-team.invitation', ['state' => 'invalid'], 404);
        }
        $teams = app(SellerTeamService::class);
        $access = SellerTeamMember::whereIn('owner_id', $teams->organizationAccounts($member->owner))->where('email', $member->email)
            ->whereNull('accepted_at')->where('expires_at', '>', now())->orderBy('id')->get();
        $inviter = app(PackageAccessService::class)->billingUser($member->owner) ?? $member->owner;
        $user = $request->user();
        $state = match (true) {
            ! $user => User::whereRaw('LOWER(email) = ?', [Str::lower($member->email)])->exists() ? 'signin' : 'join',
            Str::lower($user->email) === Str::lower($member->email) => 'accept',
            default => 'wrong',
        };
        if ($state === 'signin') {
            // After signing in, AuthenticatedSessionController sends them back here.
            $request->session()->put('team_invitation', $token);
        }

        return view('admin.seller-team.invitation', compact('member', 'token', 'access', 'inviter', 'state', 'user'));
    }

    /** A new teammate: the private link proves the email, so the account is created, verified and joined in one step. */
    public function join(Request $request, string $token, SellerTeamService $teams)
    {
        $member = $this->pendingInvitation($token);
        abort_unless($member, 404);
        if (User::whereRaw('LOWER(email) = ?', [Str::lower($member->email)])->exists()) {
            return redirect()->route('seller-workspaces.invitation', $token);
        }
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120', 'not_regex:/[<>]/'],
            'password' => ['required', 'confirmed', \Illuminate\Validation\Rules\Password::defaults()],
            'terms' => ['accepted'],
        ]);
        try {
            // A login for joining the team only: no organization, workspace, plan or seller role
            // of its own (a seller account gets a default workspace created automatically).
            $user = User::create(['name' => trim(strip_tags($data['name'])), 'email' => $member->email, 'password' => Hash::make($data['password'])]);
        } catch (\Illuminate\Database\UniqueConstraintViolationException) {
            return redirect()->route('seller-workspaces.invitation', $token);
        }
        $user->forceFill(['email_verified_at' => now()])->save();
        Auth::login($user);
        $request->session()->regenerate();
        $member = $teams->acceptInvitation($user, $member);

        return $this->enter($request, $member, $teams);
    }

    public function accept(Request $request, string $token, SellerTeamService $teams)
    {
        if (! $request->user()) {
            return redirect()->route('seller-workspaces.invitation', $token);
        }
        $member = $teams->accept($request->user(), $token);
        $request->session()->forget('team_invitation');

        // Straight into the workspace they were invited to (as "Open" on the picker).
        return $this->enter($request, $member, $teams)->with('success', __('seller_team.accepted'));
    }

    private function pendingInvitation(string $token): ?SellerTeamMember
    {
        return SellerTeamMember::where('token_hash', hash('sha256', $token))->whereNull('accepted_at')->where('expires_at', '>', now())->with('owner')->first();
    }

    public function enter(Request $request, SellerTeamMember $member, SellerTeamService $teams)
    {
        abort_unless($member->user_id === $request->user()->id && $teams->usable($member), 403);
        // Start with the first readable module; avoid an owner-only dashboard.
        $landing = ['publishing' => 'admin.posts.dashboard', 'advertising' => 'admin.ads.dashboard', 'inbox' => 'admin.chats.dashboard', 'comments' => 'admin.comments.dashboard', 'media' => 'admin.media-gallery.index', 'email_marketing' => 'admin.email.dashboard', 'ai_agent' => 'admin.ai-copilot.analytics.index'];
        $access = app(PackageAccessService::class);
        foreach ($landing as $module => $route) {
            if ($member->canUse($module) && $access->allows($member->owner, $module) && Route::has($route)) {
                $request->session()->put('seller_workspace_member', $member->id);
                $request->session()->forget(\App\Services\WorkspaceService::SESSION_KEY);

                return redirect()->route($route);
            }
        }

        return back()->withErrors(['permissions' => __('seller_team.no_modules')]);
    }

    public function leave(Request $request)
    {
        $request->session()->forget(['seller_workspace_member', \App\Services\WorkspaceService::SESSION_KEY]);

        return redirect()->route('seller-workspaces.index');
    }

    private function modules(): array
    {
        return array_diff_key(config('package_access.modules'), ['team_management' => true]);
    }

    private function permissionRules(): array
    {
        return [
            'role' => ['nullable', Rule::in([SellerTeamMember::ROLE_ADMIN, SellerTeamMember::ROLE_MEMBER])],
            // An Admin gets every module the plan includes, so no per-module list is needed.
            'permissions' => ['exclude_if:role,'.SellerTeamMember::ROLE_ADMIN, 'required', 'array:'.implode(',', array_keys($this->modules()))],
            'permissions.*' => ['exclude_if:role,'.SellerTeamMember::ROLE_ADMIN, 'required', Rule::in(['none', 'read', 'write'])],
        ];
    }

    private function workspaceRules(): array
    {
        $modules = implode(',', array_keys($this->modules()));

        return [
            'workspaces' => ['required', 'array', 'min:1'],
            'workspaces.*' => ['array'],
            'workspaces.*.enabled' => ['nullable', 'boolean'],
            'workspaces.*.role' => ['nullable', Rule::in([SellerTeamMember::ROLE_ADMIN, SellerTeamMember::ROLE_MEMBER])],
            // API: permissions[module] = none|read|write. Form: modules[module] checkbox + levels[module].
            'workspaces.*.permissions' => ['nullable', 'array:'.$modules],
            'workspaces.*.permissions.*' => [Rule::in(['none', 'read', 'write'])],
            'workspaces.*.modules' => ['nullable', 'array:'.$modules],
            'workspaces.*.levels' => ['nullable', 'array:'.$modules],
            'workspaces.*.levels.*' => [Rule::in(['read', 'write'])],
        ];
    }

    /** The selected workspaces as [account id => [role, permissions]]; a Member needs at least one module. */
    private function assignments(array $workspaces): array
    {
        $assignments = [];
        foreach ($workspaces as $accountId => $workspace) {
            if (array_key_exists('enabled', $workspace) && ! filter_var($workspace['enabled'], FILTER_VALIDATE_BOOLEAN)) {
                continue;
            }
            $role = $workspace['role'] ?? SellerTeamMember::ROLE_MEMBER;
            $permissions = $workspace['permissions'] ?? [];
            foreach ($workspace['modules'] ?? [] as $module => $checked) {
                if (filter_var($checked, FILTER_VALIDATE_BOOLEAN)) {
                    $permissions[$module] = $workspace['levels'][$module] ?? 'write';
                }
            }
            $permissions = array_map(fn ($module) => $permissions[$module] ?? 'none', array_combine(array_keys($this->modules()), array_keys($this->modules())));
            if ($role === SellerTeamMember::ROLE_MEMBER && ! array_intersect($permissions, ['read', 'write'])) {
                throw ValidationException::withMessages(['workspaces' => __('seller_team.choose_module')]);
            }
            $assignments[(int) $accountId] = ['role' => $role, 'permissions' => $permissions];
        }
        if ($assignments === []) {
            throw ValidationException::withMessages(['workspaces' => __('seller_team.choose_workspace')]);
        }

        return $assignments;
    }

    /** The organization's workspaces in scope, the default one first. */
    private function workspaces(array $accounts): Collection
    {
        $named = rescue(fn () => Workspace::whereIn('account_id', $accounts)->get()->keyBy('account_id'), collect(), false);

        return User::whereIn('id', $accounts)->get(['id', 'name', 'workspace_owner_id'])
            ->map(fn (User $u) => ['account_id' => $u->id, 'name' => $named[$u->id]->name ?? $u->name, 'logo_url' => $named[$u->id]->logo_url ?? null, 'default' => $u->workspace_owner_id === null])
            ->sortBy([['default', 'desc'], ['name', 'asc']])->values();
    }

    /** One entry per person, with their access in each workspace. */
    private function people(Collection $members, ?SellerTeamMember $viewer): Collection
    {
        return $members->groupBy('email')->map(function (Collection $rows, string $email) use ($viewer) {
            $state = $rows->contains(fn ($r) => $r->accepted_at) ? 'joined' : ($rows->contains(fn ($r) => $r->isPending()) ? 'pending' : 'expired');

            return [
                'email' => $email,
                'name' => $rows->first(fn ($r) => $r->user)?->user->name ?? $rows->first(fn ($r) => $r->name)?->name ?? Str::before($email, '@'),
                'state' => $state,
                'first' => $rows->first(),
                'rows' => $rows->keyBy('owner_id'),
                'self' => $viewer && $rows->contains('id', $viewer->id),
                // An Admin manages Members only.
                'manageable' => ! $viewer || ! $rows->contains(fn ($r) => $r->isAdmin() || $r->id === $viewer->id),
            ];
        })->values();
    }

    /** The team member (Admin) acting inside the owner's workspace; null for the owner. */
    private function viewer(Request $request): ?SellerTeamMember
    {
        return $request->attributes->get('workspace_member');
    }

    /** An Admin manages Members only: never another Admin (or themselves), and never the owner. */
    private function guardManageable(Request $request, SellerTeamMember $member): void
    {
        $viewer = $this->viewer($request);
        abort_if($viewer && ($member->isAdmin() || $member->id === $viewer->id), 403, __('seller_team.admin_cannot_manage'));
    }
}
