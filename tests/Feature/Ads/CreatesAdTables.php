<?php

namespace Tests\Feature\Ads;

use Illuminate\Support\Facades\Schema;

/** The ad tables the campaign builders write to (SQLite test schema). */
trait CreatesAdTables
{
    protected function createAdTables(): void
    {
        Schema::create('ad_campaigns', function ($t) {
            $t->id();
            foreach (['social_account_id', 'user_id'] as $c) {
                $t->unsignedBigInteger($c)->nullable();
            }
            foreach (['name', 'platform', 'status', 'ad_campaign_id', 'objective', 'special_ad_category', 'budget_mode', 'bidding_strategy', 'app_promotion_type'] as $c) {
                $t->string($c)->nullable();
            }
            $t->decimal('budget', 12, 2)->nullable();
            $t->decimal('daily_budget', 12, 2)->nullable();
            $t->timestamp('start_time')->nullable();
            $t->timestamp('end_time')->nullable();
            $t->timestamps();
        });
        Schema::create('ad_adgroups', function ($t) {
            $t->id();
            foreach (['user_id', 'social_account_id', 'ad_campaign_id'] as $c) {
                $t->unsignedBigInteger($c)->nullable();
            }
            foreach (['ad_adgroup_id', 'platform', 'name', 'optimization_goal', 'billing_event', 'destination_type', 'budget_mode', 'bid_strategy', 'bid_price', 'status', 'objective', 'promotion_type', 'placement_type', 'gender', 'schedule_type', 'bid_type', 'conversion_bid_price', 'pacing'] as $c) {
                $t->string($c)->nullable();
            }
            $t->decimal('budget', 12, 2)->nullable();
            $t->timestamp('schedule_start_time')->nullable();
            $t->timestamp('schedule_end_time')->nullable();
            foreach (['promotion_target_type', 'targeting_criteria', 'publisher_platforms', 'placements', 'location_ids', 'languages', 'age_groups'] as $c) {
                $t->text($c)->nullable();
            }
            $t->timestamps();
        });
        Schema::create('ad_creatives', function ($t) {
            $t->id();
            foreach (['user_id', 'social_account_id', 'ad_campaign_id', 'ad_adgroup_id'] as $c) {
                $t->unsignedBigInteger($c)->nullable();
            }
            foreach (['ad_creative_id', 'platform', 'name', 'type', 'page_id', 'headline', 'url', 'call_to_action', 'lead_generation_form_id', 'ad_format'] as $c) {
                $t->string($c)->nullable();
            }
            $t->text('message')->nullable();
            $t->timestamps();
        });
        Schema::create('ads', function ($t) {
            $t->id();
            foreach (['user_id', 'social_account_id', 'ad_campaign_id', 'ad_adgroup_id', 'ad_creative_id'] as $c) {
                $t->unsignedBigInteger($c)->nullable();
            }
            foreach (['ad_id', 'platform', 'name', 'status', 'call_to_action', 'ad_format'] as $c) {
                $t->string($c)->nullable();
            }
            $t->timestamps();
        });
        Schema::create('ad_media', function ($t) {
            $t->id();
            foreach (['ad_media_id', 'platform', 'name', 'file_name', 'type', 'status', 'signature', 'file_id', 'upload_by_type', 'ad_format'] as $c) {
                $t->string($c)->nullable();
            }
            foreach (['user_id', 'social_account_id'] as $c) {
                $t->unsignedBigInteger($c)->nullable();
            }
            $t->unsignedBigInteger('ad_campaign_id'); // NOT NULL, as on MySQL
            foreach (['url', 'download_link', 'thumbnail_url'] as $c) {
                $t->text($c)->nullable();
            }
            $t->timestamps();
        });
        Schema::create('ad_creative_media', function ($t) {
            $t->id();
            $t->unsignedBigInteger('ad_creative_id');
            $t->unsignedBigInteger('ad_media_id');
            $t->integer('sort_order')->default(0);
            $t->timestamps();
        });
        Schema::create('platform_pages', function ($t) {
            $t->id();
            foreach (['platform', 'page_id', 'name', 'username', 'picture'] as $c) {
                $t->string($c)->nullable();
            }
            $t->unsignedBigInteger('user_id');
            $t->timestamps();
        });
        Schema::create('media_assets', function ($t) {
            $t->id();
            $t->unsignedBigInteger('user_id');
            foreach (['type', 'path', 'url', 'thumbnail_url', 'original_name', 'extension', 'mime_type'] as $c) {
                $t->string($c)->nullable();
            }
            $t->unsignedBigInteger('size')->default(0);
            $t->timestamps();
            $t->softDeletes();
        });
    }
}
