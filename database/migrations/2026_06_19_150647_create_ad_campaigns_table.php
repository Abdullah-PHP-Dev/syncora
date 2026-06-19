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
        Schema::create('ad_campaigns', function(Blueprint $table){

            $table->id();
        
        
            $table->foreignId('ad_company_id')
                ->constrained('companies')
                ->cascadeOnDelete();
        
        
            $table->foreignId('ad_account_id')
                ->constrained('ad_accounts')
                ->cascadeOnDelete();
        
        
            $table->string('name')->nullable();
        
            $table->string('status')->nullable();
        
            $table->string('campaign_id')->nullable();
        
            $table->string('objective')->nullable();
        
            $table->string('app_promotion_type')->nullable();
        
        
            $table->decimal('bidding_amount',12,2)->nullable();
        
            $table->string('budget_mode')->nullable();
        
        
            $table->decimal('budget',12,2)->nullable();
        
            $table->decimal('daily_budget',12,2)->nullable();
        
        
        
            $table->string('budget_resource_id')->nullable();
        
        
            $table->timestamp('start_time')->nullable();
        
            $table->timestamp('end_time')->nullable();
        
        
            $table->string('bidding_strategy')->nullable();
        
            $table->string('bidding_resource_id')->nullable();
        
        
            $table->string('advertising_channel_type')->nullable();
        
            $table->string('advertising_channel_sub_type')->nullable();
        
        
        
            $table->string('hotel_center_id')->nullable();
        
            $table->string('app_store')->nullable();
        
            $table->string('app_id')->nullable();
        
            $table->string('merchant_id')->nullable();
        
        
            $table->decimal('cpc_bid_ceiling_micros',12,2)->nullable();
        
        
            $table->string('funding_instrument_id')->nullable();
        
        
        
            $table->string('account_to_use');
        
            $table->string('platform');
        
        
            $table->timestamps();
        
        
        
            $table->index('campaign_id');
        
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ad_campaigns');
    }
};
