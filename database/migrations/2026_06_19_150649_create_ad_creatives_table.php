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
            
            
            $table->bigInteger('ad_adgroup_id');
            
            
            $table->string('platform')->nullable();
            
            
            $table->foreignId('ad_account_id')
            ->constrained()
            ->cascadeOnDelete();
            
            
            $table->foreignId('ad_campaign_id')
            ->constrained()
            ->cascadeOnDelete();
            
            
            
            $table->foreignId('company_id')
            ->constrained()
            ->cascadeOnDelete();
            
            
            
            $table->string('name');
            
            
            $table->bigInteger('top_snap_media_id');
            
            $table->string('brand_name');
            
            
            $table->string('creative_id');
            
            
            $table->string('profile_id');
            
            
            $table->string('headline');
            
            
            $table->string('ad_format');
            
            
            $table->string('page_id');
            
            
            $table->text('message');
            
            
            $table->json('ad_media_files');
            
            
            $table->text('url');
            
            $table->string('type');
            
            
            $table->string('top_snap_crop_position');
            
            $table->string('call_to_action');
            
            
            
            $table->json('chat_properties');
            
            $table->json('web_view_properties');
            
            $table->json('app_install_properties');
            
            $table->json('deep_link_properties');
            
            $table->json('ad_to_lens_properties');
            
            $table->json('ad_to_call_properties');
            
            $table->json('ad_to_message_properties');
            
            $table->json('lead_generation_form_id');
            
            $table->json('composite_properties');
            
            $table->json('preview_properties');
            
            
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
