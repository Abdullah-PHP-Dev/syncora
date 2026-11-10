<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Team members are either an Admin (everything the plan includes, except billing) or a Member (per-module access). */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('seller_team_members') && ! Schema::hasColumn('seller_team_members', 'role')) {
            Schema::table('seller_team_members', function (Blueprint $table) {
                $table->string('role', 20)->default('member')->after('email');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('seller_team_members', 'role')) {
            Schema::table('seller_team_members', fn (Blueprint $table) => $table->dropColumn('role'));
        }
    }
};
