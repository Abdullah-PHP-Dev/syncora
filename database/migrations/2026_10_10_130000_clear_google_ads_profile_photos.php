<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Google Ads accounts were saved with the connecting person's own Google
 * profile photo (Google Ads customers have no logo), which made ad
 * accounts look like personal profiles. Cleared so the Google logo shows.
 * platform = 'google' rows are Google Ads customers only (YouTube channels
 * are platform = 'youtube').
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('social_accounts')->where('platform', 'google')->whereNotNull('avatar_url')->update(['avatar_url' => null]);
    }

    public function down(): void
    {
        // The photos can't be restored and aren't wanted back.
    }
};
