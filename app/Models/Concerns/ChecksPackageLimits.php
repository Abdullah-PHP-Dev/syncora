<?php

namespace App\Models\Concerns;

use App\Models\MediaAsset;
use App\Models\Post;
use App\Models\SocialAccount;
use App\Services\PackageUsageService;

trait ChecksPackageLimits
{
    public function save(array $options = [])
    {
        $check = ! $this->exists;
        if ($this instanceof SocialAccount) {
            $check = $check || $this->isDirty(['has_posting_permission', 'has_ads_permission', 'has_messaging_permission']);
        } elseif ($this instanceof Post) {
            // Imported completed posts are history, not new publishing usage.
            $check = ! $this->exists
                ? ! in_array($this->status ?? 'draft', ['draft', 'failed'], true) && ! $this->post_id
                : in_array($this->status, ['pending', 'scheduled'], true) && $this->isDirty(['status', 'schedule_at']);
        } elseif ($this instanceof MediaAsset) {
            $check = $check || $this->isDirty('size');
        }
        if (! $check) {
            return parent::save($options);
        }

        return app(PackageUsageService::class)->persist($this, fn () => parent::save($options));
    }
}
