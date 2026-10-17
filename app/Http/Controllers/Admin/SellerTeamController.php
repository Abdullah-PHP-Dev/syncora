<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SellerTeamMember;
use App\Models\User;
use App\Services\PackageAccessService;
use App\Services\PackageUsageService;
use App\Services\SellerTeamService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;

class SellerTeamController extends Controller
{
    public function index(Request $request, SellerTeamService $teams, PackageAccessService $access, PackageUsageService $usage)
    {
        $owner = $request->user();
        $members = SellerTeamMember::where('owner_id', $owner->id)->with('user')->orderBy('id')->get();
        $subscription = $access->subscription($owner);
        $data = ['members' => $members, 'seats' => $teams->seats($owner), 'limit' => $subscription ? $usage->limit($subscription, 'team_members') : 0, 'canInvite' => $access->allows($owner, 'team_management', true), 'modules' => $this->modules()];

        return $request->expectsJson() ? response()->json($data) : view('admin.seller-team.index', $data);
    }

    public function store(Request $request, SellerTeamService $teams)
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:255'], ...$this->permissionRules()]);
        [$member, $token] = $teams->invite($request->user(), $data['email'], $data['permissions']);
        $url = route('seller-workspaces.invitation', ['token' => $token]);

        return $request->expectsJson() ? response()->json(['member' => $member, 'invitation_url' => $url], 201) : back()->with('invitation_url', $url)->with('success', __('seller_team.invitation_created'));
    }

    public function update(Request $request, SellerTeamMember $member, PackageUsageService $usage, PackageAccessService $access)
    {
        abort_unless($member->owner_id === $request->user()->id, 404);
        $data = $request->validate($this->permissionRules());
        $usage->locked($request->user(), function () use ($access, $request, $member, $data) {
            $access->authorize($request->user(), 'team_management');
            $member->update(['permissions' => $data['permissions']]);
        });

        return $request->expectsJson() ? response()->json(['member' => $member->fresh()]) : back()->with('success', __('seller_team.saved'));
    }

    public function destroy(Request $request, SellerTeamMember $member)
    {
        abort_unless($member->owner_id === $request->user()->id, 404);
        // Removing access remains available after expiry or downgrade.
        DB::transaction(function () use ($request, $member) {
            User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $member->delete();
        });

        return $request->expectsJson() ? response()->noContent() : back()->with('success', __('seller_team.removed'));
    }

    public function workspaces(Request $request)
    {
        $memberships = SellerTeamMember::where('user_id', $request->user()->id)->whereNotNull('accepted_at')->with('owner')->get();

        return view('admin.seller-team.workspaces', compact('memberships'));
    }

    public function invitation(Request $request, string $token)
    {
        $member = SellerTeamMember::where('token_hash', hash('sha256', $token))->whereNull('accepted_at')->where('expires_at', '>', now())->with('owner')->firstOrFail();

        return view('admin.seller-team.invitation', compact('member', 'token'));
    }

    public function accept(Request $request, string $token, SellerTeamService $teams)
    {
        $teams->accept($request->user(), $token);

        return redirect()->route('seller-workspaces.index')->with('success', __('seller_team.accepted'));
    }

    public function enter(Request $request, SellerTeamMember $member, SellerTeamService $teams)
    {
        abort_unless($member->user_id === $request->user()->id && $teams->usable($member), 403);
        // Start with the first readable module; avoid an owner-only dashboard.
        $landing = ['publishing' => 'admin.posts.dashboard', 'advertising' => 'admin.ads.dashboard', 'inbox' => 'admin.chats.dashboard', 'comments' => 'admin.comments.dashboard', 'media' => 'admin.media-gallery.index', 'email_marketing' => 'admin.email.dashboard', 'ai_agent' => 'admin.ai-copilot.analytics.index'];
        $access = app(PackageAccessService::class);
        foreach ($landing as $module => $route) {
            if (in_array($member->permissions[$module] ?? 'none', ['read', 'write'], true) && $access->allows($member->owner, $module) && Route::has($route)) {
                $request->session()->put('seller_workspace_member', $member->id);

                return redirect()->route($route);
            }
        }

        return back()->withErrors(['permissions' => __('seller_team.no_modules')]);
    }

    public function leave(Request $request)
    {
        $request->session()->forget('seller_workspace_member');

        return redirect()->route('seller-workspaces.index');
    }

    private function modules(): array
    {
        return array_diff_key(config('package_access.modules'), ['team_management' => true]);
    }

    private function permissionRules(): array
    {
        return ['permissions' => ['required', 'array:'.implode(',', array_keys($this->modules()))], 'permissions.*' => ['required', Rule::in(['none', 'read', 'write'])]];
    }
}
