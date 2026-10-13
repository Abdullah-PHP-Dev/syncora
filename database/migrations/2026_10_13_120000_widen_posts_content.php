<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * posts.content was TEXT (65,535 bytes). Facebook accepts 63,206
 * characters per post (App\Support\PlatformTextLimits) - in Arabic or
 * with emoji that is well over 65,535 bytes, so a long post would fail to
 * save. MEDIUMTEXT holds 16 MB.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->mediumText('content')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->text('content')->nullable()->change();
        });
    }
};
