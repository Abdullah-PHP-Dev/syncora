<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per campaign-builder submission (FacebookCampaignBuilder). The
 * browser sends an idempotency key per submission: a double click or a
 * retry after a failure finds this row and continues from the step that
 * failed, re-using every Meta object that was already created (results),
 * instead of creating a second campaign/ad set/creative/ad.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ad_builder_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('social_account_id');
            $table->string('platform', 20)->default('facebook');
            $table->string('idempotency_key', 64);
            $table->string('payload_hash', 64);
            $table->string('status', 20)->default('running'); // running | failed | completed
            $table->json('results')->nullable();              // remote + local ids created so far
            $table->string('failed_step', 30)->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'idempotency_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_builder_runs');
    }
};
