<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ties every platform Post row created from one PostController::
     * quickStore() submission together, so the listing can roll them up
     * into a single card. Nullable - posts from before this migration, and
     * from the full composer's store() (which isn't part of this grouping
     * change), simply have no group and are treated as a group of one via
     * COALESCE(group_id, id) wherever grouping is queried.
     */
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->uuid('group_id')->nullable()->after('user_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn('group_id');
        });
    }
};
