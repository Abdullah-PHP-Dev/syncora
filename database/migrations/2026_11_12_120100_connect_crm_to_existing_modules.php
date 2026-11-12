<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * CRM hooks into existing modules without changing them:
 *  - conversations.crm_contact_id: the Inbox conversation's CRM person (nullable; messaging ignores it).
 *  - bundles.access_settings.crm: CRM starts with the same access each plan gives the Inbox,
 *    since CRM is built on conversations. Admins change it per plan afterwards.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('conversations') && ! Schema::hasColumn('conversations', 'crm_contact_id')) {
            Schema::table('conversations', function (Blueprint $table) {
                $table->foreignId('crm_contact_id')->nullable()->after('social_account_id')
                    ->constrained('crm_contacts')->nullOnDelete();
            });
        }

        if (Schema::hasTable('bundles') && Schema::hasColumn('bundles', 'access_settings')) {
            foreach (DB::table('bundles')->get(['id', 'access_settings', 'entitlement_settings']) as $bundle) {
                $settings = json_decode($bundle->access_settings ?? '[]', true) ?: [];
                if (isset($settings['crm'])) {
                    continue;
                }
                $settings['crm'] = $settings['inbox'] ?? ['mode' => 'disabled', 'tier' => 'none'];
                DB::table('bundles')->where('id', $bundle->id)->update(['access_settings' => json_encode($settings)]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('conversations', 'crm_contact_id')) {
            Schema::table('conversations', function (Blueprint $table) {
                $table->dropConstrainedForeignId('crm_contact_id');
            });
        }

        if (Schema::hasTable('bundles') && Schema::hasColumn('bundles', 'access_settings')) {
            foreach (DB::table('bundles')->get(['id', 'access_settings']) as $bundle) {
                $settings = json_decode($bundle->access_settings ?? '[]', true) ?: [];
                unset($settings['crm']);
                DB::table('bundles')->where('id', $bundle->id)->update(['access_settings' => json_encode($settings)]);
            }
        }
    }
};
