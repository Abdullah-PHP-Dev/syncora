<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Workspaces: one per brand or client.
 *
 * Every module already scopes its data by the tenant user (user_id), so a
 * workspace is its own tenant account (account_id) owned by the person who
 * created it (owner_id). The owner's original account is their default
 * workspace (account_id = owner_id). Re-runnable; no existing data moves.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'workspace_owner_id')) {
            Schema::table('users', function (Blueprint $table) {
                // Set only on workspace accounts: who owns (and pays for) them.
                $table->foreignId('workspace_owner_id')->nullable()->after('id')->constrained('users')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('workspaces')) {
            Schema::create('workspaces', function (Blueprint $table) {
                $table->id();
                $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('account_id')->unique()->constrained('users')->cascadeOnDelete();
                $table->string('name', 150);
                $table->string('kind', 20)->default('own'); // own | client
                $table->string('industry', 50)->nullable();
                $table->string('country', 2)->nullable();
                $table->string('currency', 3)->nullable();
                $table->string('timezone', 64)->nullable();
                $table->string('content_language', 10)->nullable(); // ar | en | both
                $table->json('goals')->nullable();
                $table->timestamp('last_opened_at')->nullable();
                $table->timestamps();
                $table->index(['owner_id', 'last_opened_at']);
            });
        }

        // Every existing seller's account becomes their default workspace.
        if (Schema::hasTable('roles') && Schema::hasTable('model_has_roles')) {
            $sellers = DB::table('users')
                ->join('model_has_roles', fn ($j) => $j->on('model_has_roles.model_id', '=', 'users.id')->where('model_has_roles.model_type', 'App\\Models\\User'))
                ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                ->where('roles.name', 'seller')
                ->whereNull('users.workspace_owner_id')
                ->whereNotExists(fn ($q) => $q->from('workspaces')->whereColumn('workspaces.account_id', 'users.id'))
                ->select('users.id', 'users.name')
                ->distinct()
                ->get();
            $profiles = Schema::hasTable('business_profiles')
                ? DB::table('business_profiles')->whereIn('user_id', $sellers->pluck('id'))->pluck('business_name', 'user_id')
                : collect();
            foreach ($sellers as $seller) {
                DB::table('workspaces')->insert([
                    'owner_id' => $seller->id, 'account_id' => $seller->id,
                    'name' => mb_substr($profiles[$seller->id] ?? '' ?: $seller->name, 0, 150), 'kind' => 'own',
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }

        // The administrator sets each plan's workspace allowance in Plans
        // (-1 = unlimited). Plans without a value allow only the default workspace.
        if (Schema::hasTable('package_features') && ! DB::table('package_features')->where('key', 'workspaces')->exists()) {
            DB::table('package_features')->insert([
                'key' => 'workspaces', 'name_en' => 'Workspaces', 'name_ar' => 'مساحات العمل',
                'description' => 'Brands or clients one subscription can manage, including the default workspace.',
                'type' => 'capacity', 'is_active' => true, 'version' => 1,
                'sort_order' => (int) DB::table('package_features')->max('sort_order') + 1,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('package_features')) {
            DB::table('package_features')->where('key', 'workspaces')->delete();
        }
        Schema::dropIfExists('workspaces');
        if (Schema::hasColumn('users', 'workspace_owner_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropConstrainedForeignId('workspace_owner_id');
            });
        }
    }
};
