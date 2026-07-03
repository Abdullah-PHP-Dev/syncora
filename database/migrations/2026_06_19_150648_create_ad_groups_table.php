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
        Schema::create('ad_adgroups', function(Blueprint $table){
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->bigInteger('ad_adgroup_id')->nullable();
            $table->string('platform')->nullable();
            $table->foreignId('ad_account_id')
            ->constrained('ad_accounts')
            ->cascadeOnDelete();
            $table->foreignId('ad_campaign_id')
            ->constrained('ad_campaigns')
            ->cascadeOnDelete();
            $table->string('name')->nullable();
            $table->string('promotion_type')->nullable();
            $table->string('promotion_target_type')->nullable();
            $table->string('placement_type')->nullable();
            $table->string('placements')->nullable();
            $table->json('location_ids')->nullable();
            $table->string('gender')->nullable();
            $table->string('operating_systems')->nullable();
            $table->string('audience_type')->nullable();
            $table->string('budget_mode')->nullable();
            $table->decimal('budget',12,2)->nullable();
            $table->string('schedule_type')->nullable();
            $table->timestamp('schedule_start_time')->nullable();
            $table->timestamp('schedule_end_time')->nullable();
            $table->string('optimization_goal')->nullable();
            $table->string('destination_type')->nullable();
            $table->string('bid_type')->nullable();
            $table->string('bid_price')->nullable();
            $table->decimal('conversion_bid_price',12,2)->nullable();
            $table->string('deep_bid_type')->nullable();
            $table->decimal('roas_bid',12,2)->nullable();
            $table->string('bid_display_mode')->nullable();
            $table->string('billing_event')->nullable();
            $table->string('pacing')->nullable();
            $table->string('status')->nullable();
            $table->longText('age_groups')->nullable();
            $table->string('primary_web_event_tag')->nullable();
            $table->string('ios')->nullable();
            $table->string('android')->nullable();
            $table->string('app_store_identifier')->nullable();
            $table->string('objective')->nullable();
            $table->json('publisher_platforms')->nullable();
            $table->json('languages')->nullable();
            $table->timestamps();
            
            $table->index('ad_adgroup_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ad_adgroups');
    }
};
