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
        Schema::create('ad_media',function(Blueprint $table){
            $table->id();
            $table->string('ad_media_id');
            $table->string('platform');
            // $table->foreignId('company_id')
            // ->constrained()
            // ->cascadeOnDelete();
            $table->foreignId('ad_account_id')
            ->constrained()
            ->cascadeOnDelete();
            $table->foreignId('ad_campaign_id')
            ->constrained()
            ->cascadeOnDelete();
            $table->string('name')->nullable();
            $table->string('file_name')->nullable();
            $table->text('download_link')->nullable();
            $table->string('type')->nullable();
            $table->string('status')->nullable();
            $table->string('image_category')->nullable();
            $table->text('url')->nullable();
            $table->string('upload_by_type')->nullable();
            $table->string('ad_format');
            $table->string('signature');
            $table->bigInteger('file_id');
            $table->timestamps();
            $table->index('ad_media_id');
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ad_media');
    }
};
