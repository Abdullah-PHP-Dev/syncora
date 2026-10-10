<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Meta ad accounts connected through the Connection Hub were stored as
 * "act_123", while the Ads API builds act_{accountId} from the bare id -
 * every Ads call for those accounts went to act_act_123 and failed.
 * Rows are renamed in place (campaigns keep their social_account_id).
 * When the same account already exists under its bare id (connected via
 * both flows), the act_ copy is retired the same way skipped accounts are:
 * no ads permission, not linked to a connection - kept, not deleted.
 */
return new class extends Migration
{
    public function up(): void
    {
        $rows = DB::table('social_accounts')
            ->where('platform', 'facebook')
            ->where('platform_account_id', 'like', 'act%')
            ->get(['id', 'user_id', 'platform_account_id'])
            // Prefix check here: LIKE escaping of "_" differs between databases.
            ->filter(fn ($row) => str_starts_with($row->platform_account_id, 'act_'));

        foreach ($rows as $row) {
            $bare = substr($row->platform_account_id, 4);

            $duplicate = DB::table('social_accounts')
                ->where('platform', 'facebook')
                ->where('user_id', $row->user_id)
                ->where('platform_account_id', $bare)
                ->exists();

            DB::table('social_accounts')->where('id', $row->id)->update($duplicate
                ? ['has_ads_permission' => false, 'social_connection_id' => null]
                : ['platform_account_id' => $bare]);
        }
    }

    public function down(): void
    {
        // Not reversible: the bare id is the correct form.
    }
};
