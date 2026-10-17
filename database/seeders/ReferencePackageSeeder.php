<?php

namespace Database\Seeders;

use App\Models\Bundle;
use App\Models\PackageChange;
use App\Models\PackageFeature;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ReferencePackageSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $definitions = config('package_reference.features');
            foreach ($definitions as $key => $definition) {
                $feature = PackageFeature::firstOrNew(['key' => $key]);
                if ($feature->exists && $feature->type !== $definition['type']) {
                    throw new RuntimeException("The feature {$key} has an incompatible type.");
                }
                $before = $feature->exists ? $feature->toArray() : null;
                $feature->fill($definition + ['sort_order' => array_search($key, array_keys($definitions)) + 1, 'is_active' => true]);
                $this->saveWithHistory($feature, 'feature', $before);
            }
            // Keep legacy settings for history; remove overlapping controls from editors.
            foreach (PackageFeature::whereIn('key', array_keys(config('package_features')))->get() as $feature) {
                $before = $feature->toArray();
                $feature->is_active = false;
                $this->saveWithHistory($feature, 'feature', $before);
            }

            foreach (config('package_reference.packages') as $slug => $definition) {
                $bundle = Bundle::where('slug', $slug)->first()
                    ?? Bundle::whereIn('slug', $definition['aliases'])->first()
                    ?? new Bundle(['slug' => $slug]);
                $before = $bundle->exists ? $bundle->toArray() : null;
                // Existing slugs/IDs and yearly prices remain stable for existing links.
                $yearly = $bundle->exists ? $bundle->yearly_price : $definition['price'] * 12;
                $bundle->fill([
                    'name_en' => $definition['name_en'], 'name_ar' => $definition['name_ar'],
                    'currency' => 'SAR', 'is_active' => true,
                    'discount_price' => null, 'is_default' => $slug === 'free_trial',
                    'is_free' => $slug === 'free_trial',
                    'sort_order' => array_search($slug, array_keys(config('package_reference.packages'))) + 1,
                ]);
                if (! $bundle->exists || (float) $bundle->price !== (float) $definition['price']) {
                    $bundle->price = $definition['price'];
                }
                $values = array_combine(array_keys($definitions), $definition['values']);
                $bundle->entitlement_settings = array_replace($bundle->entitlement_settings ?? [], $values);
                if ($bundle->access_settings === null) {
                    $bundle->access_settings = \App\Services\PackageAccessService::initialSettings($bundle);
                }
                $meta = $bundle->meta ?? [];
                $meta['yearly_price'] = $yearly;
                $meta['trial_days'] = 14;
                $meta['allowance_period'] = $definition['period'];
                $meta['reference_allocation'] = '2026-10-17';
                $meta['display_features_source'] = 'catalog';
                foreach (['en', 'ar'] as $locale) {
                    $meta['description_'.$locale] = $definition['description_'.$locale];
                    $benefits = [];
                    foreach ($definitions as $key => $feature) {
                        $value = $values[$key];
                        $display = is_bool($value) ? ($value ? 'Yes' : 'No') : ($value === -1 ? 'Unlimited' : (string) $value);
                        if ($locale === 'ar') {
                            $display = trans($display, [], 'ar');
                        }
                        if ($feature['type'] === 'quota') {
                            $display .= $locale === 'ar'
                                ? ($definition['period'] === 'trial' ? ' خلال التجربة' : ' / شهر')
                                : ($definition['period'] === 'trial' ? ' total during trial' : ' / month');
                        }
                        $benefits[] = $feature['name_'.$locale].': '.$display;
                    }
                    $meta['display_features_'.$locale] = $benefits;
                }
                $bundle->meta = $meta;
                $this->saveWithHistory($bundle, 'bundle', $before);
            }
            // Retire the old extra tier without removing it or its subscriptions.
            foreach (Bundle::where('slug', 'unicorn')->get() as $bundle) {
                $before = $bundle->toArray();
                $bundle->is_active = false;
                $this->saveWithHistory($bundle, 'bundle', $before);
            }
        });
    }

    private function saveWithHistory($model, string $type, ?array $before): void
    {
        if (! $model->exists || $model->isDirty()) {
            $model->version = $model->exists ? $model->version + 1 : 1;
            $model->save();
            PackageChange::create(['entity_type' => $type, 'entity_id' => $model->id, 'actor_id' => null, 'before' => $before, 'after' => $model->fresh()->toArray()]);
        }
    }
}
