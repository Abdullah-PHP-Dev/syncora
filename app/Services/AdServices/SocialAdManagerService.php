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
        'instagram' => FacebookAdService::class,
        'google'    => GoogleAdService::class,
        'youtube'   => YoutubeAdService::class,
        'tiktok'    => TiktokAdService::class,
        'snapchat'  => SnapchatAdService::class,
        'x'         => XAdService::class,
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

        return $this->service($platform)
            ->redirect($platform, $state);
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
}