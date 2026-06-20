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
        Schema::create('ad_groups', function(Blueprint $table){


            $table->id();
            
            
            $table->bigInteger('ad_adgroup_id');
            
            
            $table->string('platform')->nullable();
            
            
            
            $table->foreignId('ad_account_id')
            ->constrained('ad_accounts')
            ->cascadeOnDelete();
            
            
            
            $table->foreignId('ad_campaign_id')
            ->constrained('ad_campaigns')
            ->cascadeOnDelete();
            
            
            
            // $table->foreignId('company_id')
            // ->constrained('companies')
            // ->cascadeOnDelete();
            
            
            
            $table->string('name');
            
            
            
            $table->string('promotion_type');
            
            $table->string('promotion_target_type');
            
            $table->string('placement_type');
            
            
            $table->string('placements');
            
            
            $table->json('location_ids');
            
            
            $table->string('gender');
            
            $table->string('operating_systems');
            
            $table->string('audience_type');
            
            
            $table->string('budget_mode');
            
            
            $table->decimal('budget',12,2);
            
            
            
            $table->string('schedule_type');
            
            
            $table->timestamp('schedule_start_time');
            
            $table->timestamp('schedule_end_time');
            
            
            
            $table->string('optimization_goal');
            
            
            $table->string('bid_type');
            
            $table->string('bid_price');
            
            
            $table->decimal('conversion_bid_price',12,2);
            
            
            $table->string('deep_bid_type');
            
            
            $table->decimal('roas_bid',12,2);
            
            
            
            $table->string('bid_display_mode');
            
            
            $table->string('billing_event');
            
            $table->string('pacing');
            
            
            $table->string('status');
            
            
            
            $table->longText('age_groups');
            
            
            
            $table->string('primary_web_event_tag');
            
            $table->string('ios');
            
            $table->string('android');
            
            $table->string('app_store_identifier')->nullable();
            
            
            $table->string('objective');
            
            
            $table->timestamps();
            
            
            
            $table->index('ad_adgroup_id');
            
            
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ad_groups');
    }
};
