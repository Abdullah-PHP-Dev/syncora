<?php

namespace Database\Seeders;

use App\Models\Bundle;
use Illuminate\Database\Seeder;

class BundleSeeder extends Seeder
{
	public function run(): void
	{
		$bundles = [
			[
				'name_en' => 'Free Trial',
				'name_ar' => 'تجربة مجانية',
				'slug' => 'free_trial',
				'price' => 0.00,
				'discount_price' => null,
				'currency' => 'SAR',

				'is_default' => true,
				'is_free' => true,
				'is_popular' => false,
				'is_active' => true,

				'version' => 1,
				'sort_order' => 1,
				'allow_integration' => false,

				'features' => [
					'shopify' => false,
					'woocommerce' => false,
					'api_access' => false,
					'warehouse_sync' => false,
				],

				'allowed_categories' => [
					'basic',
				],

				'allowed_product' => [
					'max_products' => 50,
					'auto_sync' => false,
				],

				'limits' => [
					'hour' => 20,
					'daily' => 100,
					'monthly' => 1000,
				],

				'meta' => [
					'trial_days' => 15,
					'support' => 'basic',
				],
			],

			[
				'name_en' => 'Starter',
				'name_ar' => 'الخطة المبدئية',
				'slug' => 'starter',
				'price' => 49.00,
				'discount_price' => 40.00,
				'currency' => 'SAR',

				'is_default' => false,
				'is_free' => false,
				'is_popular' => false,
				'is_active' => true,

				'version' => 1,
				'sort_order' => 2,
				'allow_integration' => true,

				'features' => [
					'shopify' => true,
					'woocommerce' => false,
					'api_access' => true,
					'warehouse_sync' => true,
				],

				'allowed_categories' => [
					'basic',
					'fashion',
				],

				'allowed_product' => [
					'max_products' => 500,
					'auto_sync' => true,
				],

				'limits' => [
					'hour' => 200,
					'daily' => 2000,
					'monthly' => 20000,
				],

				'meta' => [
					'support' => 'standard',
				],
			],

			[
				'name_en' => 'Pro',
				'name_ar' => 'الخطة الاحترافية',
				'slug' => 'pro',
				'price' => 99.00,
				'discount_price' => 250.00,
				'currency' => 'SAR',

				'is_default' => false,
				'is_free' => false,
				'is_popular' => false,
				'is_active' => true,

				'version' => 1,
				'sort_order' => 3,
				'allow_integration' => true,

				'features' => [
					'shopify' => true,
					'woocommerce' => true,
					'api_access' => true,
					'warehouse_sync' => true,
				],

				'allowed_categories' => [
					'all',
				],

				'allowed_product' => [
					'max_products' => 5000,
					'auto_sync' => true,
				],

				'limits' => [
					'hour' => 1000,
					'daily' => 10000,
					'monthly' => 100000,
				],

				'meta' => [
					'support' => 'priority',
					'ai_features' => true,
				],
			],

			[
				'name_en' => 'Empire',
				'name_ar' => '',
				'slug' => 'empire',
				'price' => 299.00,
				'discount_price' => null,
				'currency' => 'SAR',

				'is_default' => false,
				'is_free' => false,
				'is_popular' => true,
				'is_active' => true,

				'version' => 1,
				'sort_order' => 4,
				'allow_integration' => false,

				'features' => null,
				'allowed_categories' => null,
				'allowed_product' => null,
				'limits' => null,
				'meta' => null,
			],

			[
				'name_en' => 'Unicorn',
				'name_ar' => '',
				'slug' => 'unicorn',
				'price' => 599.00,
				'discount_price' => null,
				'currency' => 'SAR',

				'is_default' => false,
				'is_free' => false,
				'is_popular' => false,
				'is_active' => true,

				'version' => 1,
				'sort_order' => 5,
				'allow_integration' => false,

				'features' => null,
				'allowed_categories' => null,
				'allowed_product' => null,
				'limits' => null,
				'meta' => null,
			],
		];

		foreach ($bundles as $bundle) {
			Bundle::updateOrCreate(
				['slug' => $bundle['slug']],
				$bundle
			);
		}
	}
}