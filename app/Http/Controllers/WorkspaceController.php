<?php

namespace App\Http\Controllers;

use App\Models\SellerTeamMember;
use App\Models\Workspace;
use App\Services\SellerTeamService;
use App\Services\WorkspaceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Workspace picker (choose / create a brand or client workspace, answer invitations). */
class WorkspaceController extends Controller
{
    public function __construct(private WorkspaceService $workspaces) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $owned = $this->workspaces->owned($user);
        $joined = SellerTeamMember::where('user_id', $user->id)->whereNotNull('accepted_at')->with('owner', 'workspace')->get()->filter(fn ($m) => $m->owner)->values();
        $health = $this->workspaces->health($owned->pluck('account_id')->merge($joined->pluck('owner_id'))->unique()->values()->all());
        $active = $request->session()->has('seller_workspace_member') ? null
            : ($this->workspaces->active($request, $user) ?? $owned->firstWhere('account_id', $user->id));

        $cards = $owned->map(fn (Workspace $w) => [
            'id' => 'w'.$w->id,
            'name' => $w->name,
            'logo_url' => $w->logo_url,
            'kind' => $w->kind,
            'role' => 'owner',
            'industry' => $w->industry,
            'last_opened_at' => $w->last_opened_at?->toIso8601String(),
            'current' => $active && $active->id === $w->id,
            'open_url' => route('seller-workspaces.open', $w),
            'edit_url' => route('seller-workspaces.update', $w),
            'details' => $this->editable($w),
        ] + $health[$w->account_id])->concat($joined->map(fn (SellerTeamMember $m) => [
            'id' => 'm'.$m->id,
            'name' => $m->workspace_name,
            'kind' => 'joined',
            'role' => 'member',
            'industry' => null,
            'last_opened_at' => $m->accepted_at?->toIso8601String(),
            'current' => (int) $request->session()->get('seller_workspace_member') === $m->id,
            'open_url' => route('seller-workspaces.enter', $m),
        ] + $health[$m->owner_id]))->values();

        $limit = $this->workspaces->canOwn($user) ? $this->workspaces->limit($user) : 0;

        return view('workspaces.index', ['picker' => [
            'user' => ['name' => $user->name, 'first_name' => Str::before(trim($user->name), ' ')],
            'workspaces' => $cards,
            // One invitation per organization; accepting it joins every workspace it lists.
            'invitations' => $this->workspaces->invitations($user)
                ->groupBy(fn (SellerTeamMember $m) => $m->owner->workspace_owner_id ?? $m->owner_id)
                ->map(fn ($rows) => $rows->sortBy('id')->first()->setAttribute('workspace_list', $rows->sortBy('id')->map->workspace_name->implode(', ')))
                ->values()->map(fn (SellerTeamMember $m) => [
                'id' => $m->id,
                'workspace' => $m->workspace_list,
                'invited_at' => $m->created_at?->toIso8601String(),
                'accept_url' => route('seller-workspaces.respond', [$m, 'accept']),
                'decline_url' => route('seller-workspaces.respond', [$m, 'decline']),
            ])->values(),
            'can_create' => $this->workspaces->canOwn($user),
            'limit' => $limit,
            'owned' => $owned->count(),
            'upgrade_url' => route('admin.subscription.select'),
            'store_url' => route('seller-workspaces.store'),
            'options' => [
                'industries' => config('workspaces.industries'),
                'countries' => config('workspaces.countries'),
                'timezones' => config('workspaces.timezones'),
                'languages' => config('workspaces.languages'),
                'goals' => config('workspaces.goals'),
                'platform_order' => config('workspaces.platform_order'),
            ],
            'messages' => __('workspaces'),
            'locale' => app()->getLocale(),
        ]]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);

        $workspace = $this->workspaces->create($request->user(), $data);
        $this->workspaces->open($request, $workspace);

        return response()->json(['redirect' => route('admin.connections.index')], 201);
    }

    public function update(Request $request, Workspace $workspace): JsonResponse
    {
        $workspace = $this->workspaces->update($request->user(), $workspace, $this->validated($request));

        return response()->json(['workspace' => $this->editable($workspace), 'message' => __('workspaces.edit.saved')]);
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:150', 'not_regex:/[<>]/'],
            'kind' => ['required', Rule::in(['own', 'client'])],
            'industry' => ['nullable', Rule::in(config('workspaces.industries'))],
            'country' => ['nullable', Rule::in(array_keys(config('workspaces.countries')))],
            'timezone' => ['nullable', Rule::in(config('workspaces.timezones'))],
            'content_language' => ['nullable', Rule::in(config('workspaces.languages'))],
            'goals' => ['nullable', 'array'],
            'goals.*' => [Rule::in(array_keys(config('workspaces.goals')))],
            // Raster images only (SVG can carry scripts); the server names and stores the file.
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048', 'dimensions:min_width=64,min_height=64,max_width=4096,max_height=4096'],
            'remove_logo' => ['nullable', 'boolean'],
        ]);
        // Currency follows the country; never taken from input.
        $data['currency'] = isset($data['country']) ? config('workspaces.countries')[$data['country']] : null;

        return $data;
    }

    /** What the edit dialog prefills and the card shows after saving. */
    private function editable(Workspace $w): array
    {
        return [
            'name' => $w->name, 'logo_url' => $w->logo_url, 'kind' => $w->kind, 'industry' => $w->industry, 'country' => $w->country,
            'timezone' => $w->timezone, 'content_language' => $w->content_language, 'goals' => $w->goals ?? [],
        ];
    }

    public function open(Request $request, Workspace $workspace): RedirectResponse
    {
        $this->workspaces->open($request, $workspace);

        // "Finish setup" on a workspace with no platforms: straight to its Connections.
        if ($request->input('to') === 'setup') {
            return redirect()->route('admin.connections.index');
        }

        return redirect()->intended(route('dashboard'));
    }

    public function respond(Request $request, SellerTeamMember $member, string $action, SellerTeamService $teams): RedirectResponse
    {
        if ($action === 'decline') {
            $teams->decline($request->user(), $member);

            return redirect()->route('seller-workspaces.index')->with('success', __('workspaces.invitation.declined'));
        }
        $member = $teams->acceptInvitation($request->user(), $member);

        // Same as "Open" on a joined workspace.
        return app(\App\Http\Controllers\Admin\SellerTeamController::class)->enter($request, $member, $teams);
    }
}
