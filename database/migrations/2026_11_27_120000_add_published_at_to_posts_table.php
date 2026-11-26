<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When a post actually went live on its platform. Post already declared
 * published_at (fillable, cast, a scope) but the column never existed, so
 * the listing guessed from schedule_at/created_at. X only lets a post be
 * edited for 30 minutes after it went out, which needs the real time.
 *
 * Existing rows stay NULL: their real publish time isn't known, and the
 * listing keeps its old estimate for them.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('posts', 'published_at')) {
            return;
        }

        Schema::table('posts', function (Blueprint $table) {
            $table->dateTime('published_at')->nullable()->after('schedule_at');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('posts', 'published_at')) {
            Schema::table('posts', function (Blueprint $table) {
                $table->dropColumn('published_at');
            });
        }
    }
};
