<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('post_accounts', function (Blueprint $table) {
            $table->json('insights')->nullable()->after('views_count');
        });

        Schema::table('posts', function (Blueprint $table) {
            $table->json('analytics_data')->nullable()->after('performance_score');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('post_accounts', function (Blueprint $table) {
            $table->dropColumn('insights');
        });

        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn('analytics_data');
        });
    }
};
