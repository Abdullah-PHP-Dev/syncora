<?php

namespace App\Services;

use App\Models\BusinessProfile;
use App\Models\SellerTeamMember;
use App\Models\SocialAccount;
use App\Models\SocialConnection;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Brand / client workspaces. Each is a tenant account owned by the signed-in
 * seller (see the workspaces migration); UseSellerWorkspace runs requests as
 * that account while the owner has it open.
 */
class WorkspaceService
{
    public const SESSION_KEY = 'active_workspace';

    public function __construct(private PackageAccessService $access) {}

    /** Can this user own workspaces? Sellers only, never platform staff or workspace accounts. */
    public function canOwn(User $user): bool
    {
        return $user->hasRole('seller') && ! $user->isTeamMember() && ! $user->isWorkspaceAccount();
    }

    /** The owner's default workspace (their own account), created on demand. */
    public function ensureDefault(User $owner): Workspace
    {
        return Workspace::firstOrCreate(
            ['account_id' => $owner->id],
            ['owner_id' => $owner->id, 'name' => $owner->businessProfile?->business_name ?: $owner->name, 'kind' => 'own']
        );
    }

    /** @return Collection<int, Workspace> */
    public function owned(User $owner): Collection
    {
        if (! $this->canOwn($owner)) {
            return collect();
        }
        $this->ensureDefault($owner);

        return Workspace::where('owner_id', $owner->id)->orderByDesc('last_opened_at')->orderBy('id')->get();
    }

    /** Workspaces the plan allows, default included (-1 = unlimited). Plans without a value allow one. */
    public function limit(User $owner): int
    {
        $subscription = $this->access->subscription($owner);
        if (! $this->access->active($subscription) || ! $subscription->bundle) {
            return 1;
        }
        $value = $subscription->bundle->entitlement_settings['workspaces'] ?? null;

        return is_numeric($value) && ((int) $value === -1 || (int) $value >= 1) ? (int) $value : 1;
    }

    public function create(User $owner, array $data): Workspace
    {
        abort_unless($this->canOwn($owner), 403);
        $logo = isset($data['logo']) ? $this->storeLogo($data['logo'], $owner) : null;

        try {
            return $this->createRecords($owner, $data, $logo);
        } catch (\Throwable $e) {
            $this->deleteLogo($logo);
            throw $e;
        }
    }

    private function createRecords(User $owner, array $data, ?string $logo): Workspace
    {
        return DB::transaction(function () use ($owner, $data, $logo) {
            // Serialize creation per owner so two tabs can't both take the last slot.
            User::whereKey($owner->id)->lockForUpdate()->first();
            $this->ensureDefault($owner);
            $limit = $this->limit($owner);
            if ($limit !== -1 && Workspace::where('owner_id', $owner->id)->count() >= $limit) {
                throw ValidationException::withMessages(['workspace' => trans_choice('workspaces.limit', $limit, ['count' => $limit])]);
            }

            $name = trim(strip_tags($data['name']));
            $account = User::create([
                'name' => $name,
                // Never used to sign in or receive mail (User::routeNotificationForMail).
                'email' => 'workspace-'.Str::uuid().'@workspaces.socialeaz.invalid',
                'password' => Hash::make(Str::random(64)),
                'workspace_owner_id' => $owner->id,
            ]);
            $account->assignRole('seller');
            BusinessProfile::create(['user_id' => $account->id, 'business_name' => $name]);

            return Workspace::create([
                'owner_id' => $owner->id,
                'account_id' => $account->id,
                'name' => $name,
                'logo_path' => $logo,
                'kind' => $data['kind'],
                'industry' => $data['industry'] ?? null,
                'country' => $data['country'] ?? null,
                'currency' => $data['currency'] ?? null,
                'timezone' => $data['timezone'] ?? null,
                'content_language' => $data['content_language'] ?? null,
                'goals' => array_values($data['goals'] ?? []),
                'last_opened_at' => now(),
            ]);
        });
    }

    /** Change a workspace's details. Only its owner can; the default workspace keeps the person's own name. */
    public function update(User $owner, Workspace $workspace, array $data): Workspace
    {
        abort_unless($workspace->owner_id === $owner->id && $this->canOwn($owner), 404);
        $previousLogo = $workspace->logo_path;
        $newLogo = isset($data['logo']) ? $this->storeLogo($data['logo'], $owner) : null;
        $logo = $newLogo ?? (! empty($data['remove_logo']) ? null : $previousLogo);

        try {
            $saved = $this->updateRecords($workspace, $data, $logo);
        } catch (\Throwable $e) {
            $this->deleteLogo($newLogo);
            throw $e;
        }
        if ($previousLogo && $previousLogo !== $logo) {
            $this->deleteLogo($previousLogo);
        }

        return $saved;
    }

    private function updateRecords(Workspace $workspace, array $data, ?string $logo): Workspace
    {
        return DB::transaction(function () use ($workspace, $data, $logo) {
            $name = trim(strip_tags($data['name']));
            $workspace->update([
                'logo_path' => $logo,
                'name' => $name,
                'kind' => $data['kind'],
                'industry' => $data['industry'] ?? null,
                'country' => $data['country'] ?? null,
                'currency' => $data['currency'] ?? null,
                'timezone' => $data['timezone'] ?? null,
                'content_language' => $data['content_language'] ?? null,
                'goals' => array_values($data['goals'] ?? []),
            ]);
            // A brand/client account is only this workspace: keep its name in step
            // (team members see it as the workspace name).
            if (! $workspace->isDefault()) {
                $workspace->account->update(['name' => $name]);
            }
            BusinessProfile::updateOrCreate(['user_id' => $workspace->account_id], ['business_name' => $name]);

            return $workspace->fresh();
        });
    }

    /** Public file, named by the server (never by the uploader). */
    private function storeLogo(UploadedFile $file, User $owner): string
    {
        $extension = $file->guessExtension() ?: 'png';
        $path = 'uploads/workspace-logos/'.$owner->id.'/'.Str::uuid().'.'.$extension;
        Storage::disk(config('workspaces.logo_disk'))->put($path, file_get_contents($file->getRealPath()), ['visibility' => 'public']);

        return $path;
    }

    private function deleteLogo(?string $path): void
    {
        if ($path) {
            rescue(fn () => Storage::disk(config('workspaces.logo_disk'))->delete($path), null, false);
        }
    }

    /** Open one of the user's own workspaces (owner check here, never from input alone). */
    public function open(Request $request, Workspace $workspace): void
    {
        abort_unless($workspace->owner_id === $request->user()->id && $this->canOwn($request->user()), 404);
        $request->session()->forget('seller_workspace_member');
        $request->session()->put(self::SESSION_KEY, $workspace->id);
        $workspace->forceFill(['last_opened_at' => now()])->save();
    }

    /** The workspace this (real, signed-in) user opened, re-checked against ownership. */
    public function active(Request $request, User $actor): ?Workspace
    {
        $id = $request->session()->get(self::SESSION_KEY);
        $workspace = $id && $this->canOwn($actor) ? Workspace::whereKey($id)->where('owner_id', $actor->id)->first() : null;
        if ($id && ! $workspace) {
            $request->session()->forget(self::SESSION_KEY);
        }

        return $workspace;
    }

    /** Sign-in / sign-up with a single workspace: open it, so it is named everywhere. */
    public function openDefault(Request $request, User $user): void
    {
        $default = $this->canOwn($user) ? Workspace::where('account_id', $user->id)->where('owner_id', $user->id)->first() : null;
        if ($default) {
            $request->session()->put(self::SESSION_KEY, $default->id);
        }
    }

    /** More than one place to work: show the picker after sign-in. */
    public function needsPicker(User $user): bool
    {
        // Read-only: signing in never creates anything.
        return ($this->canOwn($user) && Workspace::where('owner_id', $user->id)->count() > 1)
            || SellerTeamMember::where('user_id', $user->id)->whereNotNull('accepted_at')->exists()
            || $this->invitations($user)->isNotEmpty();
    }

    /** @return Collection<int, SellerTeamMember> */
    public function invitations(User $user): Collection
    {
        return SellerTeamMember::where('email', Str::lower($user->email))->whereNull('accepted_at')
            ->where('expires_at', '>', now())->with('owner')->latest('id')->get()
            ->filter(fn ($m) => $m->owner && $m->owner_id !== $user->id)->values();
    }

    /**
     * Connection health per tenant account, for the picker cards.
     *
     * @param  int[]  $accountIds
     * @return array<int, array{platforms: string[], attention: int}>
     */
    public function health(array $accountIds): array
    {
        $platforms = SocialAccount::whereIn('user_id', $accountIds)->select('user_id', 'platform')->distinct()->get()
            ->groupBy('user_id')->map(fn ($rows) => $rows->pluck('platform')->unique()->values()->all());
        $attention = SocialConnection::whereIn('user_id', $accountIds)->where('status', '!=', SocialConnection::REVOKED)->get()
            ->filter(fn ($c) => $c->needsAttention())->countBy('user_id');

        return collect($accountIds)->mapWithKeys(fn ($id) => [$id => [
            'platforms' => $platforms[$id] ?? [],
            'attention' => (int) ($attention[$id] ?? 0),
        ]])->all();
    }
}
