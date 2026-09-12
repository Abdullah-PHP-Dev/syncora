<?php

namespace App\Services\AdServices;

use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\URL;

class SocialAdManagerService
{
    /**
     * Platform map (single source of truth)
     */
    private array $platformMap = [
        'facebook'  => FacebookAdService::class,
        // Own class now, not an alias to FacebookAdService - see
        // InstagramAdService's docblock for why: its own OAuth callback
        // URL (was silently reusing Facebook's), and a second, more
        // reliable source for Instagram account discovery. Campaign
        // CRUD is inherited unchanged - it genuinely runs through the
        // same Facebook ad account either way.
        'instagram' => InstagramAdService::class,
        'google'    => GoogleAdService::class,
        'youtube'   => YoutubeAdService::class,
        'tiktok'    => TiktokAdService::class,
        'snapchat'  => SnapchatAdService::class,
        'x'         => XAdService::class,
        'linkedin'  => LinkedinAdService::class,
    ];

    /**
     * Normalize platform. YouTube ads run through the same underlying
     * Google Ads customer/API as Search - there's no separate "YouTube Ads
     * account" - but it's kept as its own platform key (own service, own
     * DB rows) rather than aliased to 'google', since YoutubeAdService
     * builds a structurally different campaign (Demand Gen, not Search).
     */
    private function resolvePlatform(string $platform): string
    {
        return $platform;
    }

    /**
     * Validate platform
     */
    private function validatePlatform(string $platform): void
    {
        abort_unless(
            array_key_exists($platform, $this->platformMap),
            404
        );
    }

    /**
     * Get service instance
     */
    private function service(string $platform)
    {
        return app($this->platformMap[$platform]);
    }

    /**
     * Shared session setup
     */
    private function setSession(string $platform): array
    {
        $state = Str::uuid()->toString();

        Session::put([
            'ad_platform' => $platform,
            'ad_codeverifier'    => Str::random(64),
            'ad_state'    => $state,
            'previous_url'       => URL::previous(),
        ]);

        return [$state];
    }

    /**
     * Redirect
     */
    public function redirect(string $platform)
    {
        $platform = $this->resolvePlatform($platform);
        $this->validatePlatform($platform);

        [$state] = $this->setSession($platform);
   
        return $this->service($platform)->redirect($platform, $state);
    }

    /**
     * Callback
     */
    public function callback(string $platform)
    {
        $platform = $this->resolvePlatform($platform);
        $this->validatePlatform($platform);

        [$state] = $this->setSession($platform);

        return $this->service($platform)
            ->callback($platform, $state);
    }

    /**
     * Store
     */
    public function store(string $platform, $data)
    {
        $platform = $this->resolvePlatform($platform);
        $this->validatePlatform($platform);

        return $this->service($platform)
            ->store($platform, $data);
    }

    public function update(string $platform, $id, $data)
    {
        $platform = $this->resolvePlatform($platform);
        $this->validatePlatform($platform);

        return $this->service($platform)->update($platform, $id, $data);
    }

    public function destroy(string $platform, $id)
    {
        $platform = $this->resolvePlatform($platform);
        $this->validatePlatform($platform);

        return $this->service($platform)->destroy($platform, $id);
    }

    public function updateStatus(string $platform, $id, string $status)
    {
        $platform = $this->resolvePlatform($platform);
        $this->validatePlatform($platform);

        return $this->service($platform)->updateStatus($id, $status);
    }

    /**
     * "Sync Now" on the platform campaigns dashboard - pulls the latest
     * campaigns from the connected platform into ad_campaigns. Only the
     * platforms whose service implements syncCampaigns() (Facebook today)
     * can do this; the rest return a clear "not available yet" rather
     * than a 500, since the per-platform read integration doesn't exist
     * for them.
     */
    public function syncCampaigns(string $platform): array
    {
        $platform = $this->resolvePlatform($platform);
        $this->validatePlatform($platform);

        $service = $this->service($platform);

        if (!method_exists($service, 'syncCampaigns')) {
            return ['success' => false, 'error' => "Campaign sync isn't available for " . ucfirst($platform) . ' yet.'];
        }

        return $service->syncCampaigns();
    }
}