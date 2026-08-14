<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * media_url was varchar(255) - Meta's CDN URLs (long signed query
     * strings) routinely exceed that, silently failing every backfill
     * insert with "Data too long for column". Widened to text to match
     * thumbnail_url/alt_text on the same table.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE post_media MODIFY media_url TEXT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE post_media MODIFY media_url VARCHAR(255) NULL');
    }
};
