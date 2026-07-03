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
        Schema::create('ad_creatives',function(Blueprint $table){
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->bigInteger('ad_adgroup_id')->nullable();
            $table->string('platform')->nullable();
            $table->foreignId('ad_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ad_campaign_id')->constrained()->cascadeOnDelete();
            $table->string('name')->nullable();
            $table->bigInteger('top_snap_media_id')->nullable();
            $table->string('brand_name')->nullable();
            $table->string('ad_creative_id')->nullable();
            $table->string('profile_id')->nullable();
            $table->string('headline')->nullable();
            $table->string('ad_format')->nullable();
            $table->string('page_id')->nullable();
            $table->text('message')->nullable();
            $table->text('url')->nullable();
            $table->string('type')->nullable();
            $table->string('top_snap_crop_position')->nullable();
            $table->string('call_to_action')->nullable();
            $table->json('chat_properties')->nullable();
            $table->json('web_view_properties')->nullable();
            $table->json('app_install_properties')->nullable();
            $table->json('deep_link_properties')->nullable();
            $table->json('ad_to_lens_properties')->nullable();
            $table->json('ad_to_call_properties')->nullable();
            $table->json('ad_to_message_properties')->nullable();
            $table->json('lead_generation_form_id')->nullable();
            $table->json('composite_properties')->nullable();
            $table->json('preview_properties')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ad_creatives');
    }
};
