<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * post_comments.user_avatar_url was VARCHAR(255), but Facebook's signed
 * CDN avatar URLs run to ~600 characters, so saving a comment or reply
 * published from the post preview threw "Data too long" after Facebook
 * had already accepted it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('post_comments', function (Blueprint $table) {
            $table->text('user_avatar_url')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Long URLs can't be shortened usefully - drop them so the narrower column fits.
        \DB::table('post_comments')->whereRaw('CHAR_LENGTH(user_avatar_url) > 255')->update(['user_avatar_url' => null]);

        Schema::table('post_comments', function (Blueprint $table) {
            $table->string('user_avatar_url')->nullable()->change();
        });
    }
};
