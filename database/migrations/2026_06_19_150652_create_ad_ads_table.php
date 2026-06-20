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
        Schema::create('ad_ads',function(Blueprint $table){


            $table->id();
            
            
            $table->string('ad_ad_id');
            
            
            $table->string('platform');
            
            
            
            $table->foreignId('ad_account_id')
            ->constrained()
            ->cascadeOnDelete();
            
            
            $table->foreignId('ad_campaign_id')
            ->constrained()
            ->cascadeOnDelete();
            
            
            $table->foreignId('ad_adgroup_id')
            ->constrained('ad_groups')
            ->cascadeOnDelete();
            
            
            
            // $table->foreignId('company_id')
            // ->nullable()
            // ->constrained()
            // ->cascadeOnDelete();
            
            
            
            $table->foreignId('creative_id')
            ->nullable()
            ->constrained('ad_creatives')
            ->cascadeOnDelete();
            
            
            
            $table->string('type')->nullable();
            
            
            $table->string('status')->nullable();
            
            
            $table->longText('text')->nullable();
            
            
            $table->string('ad_format')->nullable();
            
            
            $table->string('call_to_action')->nullable();
            
            
            
            $table->json('final_urls');
            
            $table->json('headlines');
            
            
            $table->timestamps();
            
            
            
            $table->index('ad_ad_id');
            
            
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ad_ads');
    }
};
