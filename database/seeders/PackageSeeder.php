<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Package;

class PackageSeeder extends Seeder
{
	public function run(): void
	{
		$packages = [

			// =========================
			// BASIC
			// =========================
			[
				'bundle_key' => 'basic',
				'name_en' => 'Basic',
				'name_ar' => 'الأساسية',
				'price' => 99,
				'currency' => 'SAR',
				'billing_cycle' => 'monthly',

				'features' => [
					'limits' => [
						'users' => 2,
						'products' => 100,
						'storage_gb' => 5,
					],

					'features' => [
						'social_integrations' => true,
						'post_scheduler' => true,
						'ads_manager' => false,
						'analytics_dashboard' => false,
					],

					'integrations' => [
						'facebook_posts' => true,
						'instagram_posts' => true,
						'facebook_ads' => false,
						'google_ads' => false,
						'tiktok_ads' => false,
						'snapchat_ads' => false,
					],
				],

				'is_active' => true,
				'sort_order' => 1,
			],

			// =========================
			// PREMIUM
			// =========================
			[
				'bundle_key' => 'premium',
				'name_en' => 'Premium',
				'name_ar' => 'المميزة',
				'price' => 199,
				'currency' => 'SAR',
				'billing_cycle' => 'monthly',

				'features' => [
					'limits' => [
						'users' => 10,
						'products' => 1000,
						'storage_gb' => 50,
					],

					'features' => [
						'social_integrations' => true,
						'post_scheduler' => true,
						'ads_manager' => true,
						'analytics_dashboard' => true,
					],

					'integrations' => [
						'facebook_posts' => true,
						'instagram_posts' => true,
						'facebook_ads' => true,
						'google_ads' => true,
						'tiktok_ads' => false,
						'snapchat_ads' => true,
					],
				],

				'is_active' => true,
				'sort_order' => 2,
			],

			// =========================
			// VIP
			// =========================
			[
				'bundle_key' => 'vip',
				'name_en' => 'VIP',
				'name_ar' => 'كبار العملاء',
				'price' => 499,
				'currency' => 'SAR',
				'billing_cycle' => 'monthly',

				'features' => [
					'limits' => [
						'users' => -1,        // unlimited
						'products' => -1,     // unlimited
						'storage_gb' => 500,
					],

					'features' => [
						'social_integrations' => true,
						'post_scheduler' => true,
						'ads_manager' => true,
						'analytics_dashboard' => true,
					],

					'integrations' => [
						'facebook_posts' => true,
						'instagram_posts' => true,
						'facebook_ads' => true,
						'google_ads' => true,
						'tiktok_ads' => true,
						'snapchat_ads' => true,
					],
				],

				'is_active' => true,
				'sort_order' => 3,
			],
		];

		foreach ($packages as $package) {

			Package::updateOrCreate(
				['bundle_key' => $package['bundle_key']],
				$package
			);
		}
	}
}