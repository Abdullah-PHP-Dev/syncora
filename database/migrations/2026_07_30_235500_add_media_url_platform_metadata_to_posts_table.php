<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The Post model already declares `media_url` and `platform_metadata`
     * as fillable/cast (among several other fields the migrations never
     * added - a pre-existing model/schema mismatch, out of scope here
     * beyond the two columns WhatsAppPostService actually needs:
     * `media_url` for the broadcast's header image, `platform_metadata`
     * for the approved template name/language a WhatsApp post has to
     * remember between store() and the later publishPost() call.
     */
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->text('media_url')->nullable()->after('content');
            $table->json('platform_metadata')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn(['media_url', 'platform_metadata']);
        });
    }
};
