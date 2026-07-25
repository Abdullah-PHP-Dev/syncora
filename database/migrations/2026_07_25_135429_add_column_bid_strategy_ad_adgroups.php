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
        Schema::table('ad_adgroups', function (Blueprint $table) {
            $table->string('bid_strategy')->nullable()->after('optimization_goal');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ad_adgroups', function (Blueprint $table) {
            $table->dropColumn('bid_strategy');
        });
    }
};
