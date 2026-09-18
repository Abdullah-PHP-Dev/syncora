<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Data-only migration (raw DB queries, no models) copying existing
     * stats off social_accounts into the two new detail tables before the
     * next migration drops the source columns.
     */
    public function up(): void
    {
        $accounts = DB::table('social_accounts')->select([
            'id', 'metadata', 'has_posting_permission', 'has_ads_permission',
            'followers_count', 'subscribers_count', 'likes_count',
            'views_count', 'impressions_count', 'following_count', 'media_count',
        ])->get();

        foreach ($accounts as $account) {
            if ($account->has_posting_permission) {
                $hasAnyStat = $account->followers_count !== null
                    || $account->subscribers_count !== null
                    || $account->likes_count !== null
                    || $account->views_count !== null
                    || $account->impressions_count !== null
                    || $account->following_count !== null
                    || $account->media_count !== null;

                if ($hasAnyStat) {
                    DB::table('social_account_post_details')->updateOrInsert(
                        ['social_account_id' => $account->id],
                        [
                            'followers_count' => $account->followers_count,
                            'subscribers_count' => $account->subscribers_count,
                            'likes_count' => $account->likes_count,
                            'views_count' => $account->views_count,
                            'impressions_count' => $account->impressions_count,
                            'following_count' => $account->following_count,
                            'media_count' => $account->media_count,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]
                    );
                }
            }

            if ($account->has_ads_permission) {
                $metadata = json_decode($account->metadata ?? '{}', true) ?: [];

                if (!empty($metadata['currency']) || !empty($metadata['business_id'])) {
                    DB::table('social_account_ad_details')->updateOrInsert(
                        ['social_account_id' => $account->id],
                        [
                            'currency' => $metadata['currency'] ?? null,
                            'business_id' => $metadata['business_id'] ?? null,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]
                    );
                }
            }
        }
    }

    public function down(): void
    {
        // Data-only - nothing to reverse, the source columns/data are still
        // intact on social_accounts until the next migration.
    }
};
