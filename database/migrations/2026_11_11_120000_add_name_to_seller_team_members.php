<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** The invitee's name, shown on the team page before they sign up. */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('seller_team_members') && ! Schema::hasColumn('seller_team_members', 'name')) {
            Schema::table('seller_team_members', function (Blueprint $table) {
                $table->string('name', 120)->nullable()->after('email');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('seller_team_members', 'name')) {
            Schema::table('seller_team_members', fn (Blueprint $table) => $table->dropColumn('name'));
        }
    }
};
