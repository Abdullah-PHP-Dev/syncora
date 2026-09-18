<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A queryable record of every inbound webhook hit, independent of
     * whether it ultimately produced a Message - built specifically so
     * "is TikTok actually reaching my webhook at all" and "did my
     * signature check pass" are one Eloquent query away instead of
     * grepping a multi-MB laravel.log. Deliberately platform-agnostic
     * (not a tiktok_webhook_logs table) so the same table/model can back
     * any other platform's webhook controller later without a schema
     * change - only TiktokWebhookController writes to it for now.
     */
    public function up(): void
    {
        Schema::create('webhook_logs', function (Blueprint $table) {
            $table->id();
            $table->string('platform');
            $table->string('event_type')->nullable();
            $table->boolean('signature_valid')->nullable();
            $table->boolean('processed')->default(false);
            $table->string('note')->nullable();
            $table->json('payload')->nullable();
            $table->string('ip')->nullable();
            $table->timestamps();

            $table->index(['platform', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_logs');
    }
};
