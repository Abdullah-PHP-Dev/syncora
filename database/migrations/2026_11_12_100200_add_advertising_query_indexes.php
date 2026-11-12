<?php

use App\Support\Database\IndexMigration;

/** Ad campaigns: every campaigns page, insights merge and dashboard filters by user_id + platform. */
return new class extends IndexMigration
{
    protected array $indexes = [
        // user_id = ? AND platform = ?. Serves the user_id foreign key.
        ['ad_campaigns', 'ad_campaigns_user_platform_index', ['user_id', 'platform'], 'ad_campaigns_user_id_foreign'],
    ];
};
