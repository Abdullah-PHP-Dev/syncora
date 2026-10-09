<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * posts.error_message was VARCHAR(255): a provider error longer than that
 * (YouTube returns whole JSON documents) threw "Data too long" while
 * saving the failure itself, leaving the post pending. Post::
 * cleanErrorMessage() also caps messages at ~1000 characters.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->text('error_message')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Shorten anything longer first so the narrower column fits.
        \DB::table('posts')->whereRaw('CHAR_LENGTH(error_message) > 255')
            ->update(['error_message' => \DB::raw('LEFT(error_message, 255)')]);

        Schema::table('posts', function (Blueprint $table) {
            $table->string('error_message')->nullable()->change();
        });
    }
};
