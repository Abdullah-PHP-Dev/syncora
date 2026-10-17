<?php

namespace App\Services;

use App\Models\Bundle;
use App\Models\PackageFeature;
use Illuminate\Support\Collection;

class PackagePresentationService
{
    public function catalog(): array
    {
        $plans = Bundle::where('is_active', true)->orderBy('sort_order')->orderBy('id')->get();
        $features = PackageFeature::where('is_active', true)->orderBy('sort_order')->orderBy('id')->get()
            ->filter(fn ($feature) => $plans->contains(fn ($plan) => array_key_exists($feature->key, $plan->entitlement_settings ?? [])));

        $maxSaving = $plans->map(fn ($plan) => $this->yearlySaving($plan))->max() ?? 0;

        return compact('plans', 'features', 'maxSaving');
    }

    public function yearlySaving(Bundle $plan): int
    {
        return ! $plan->is_free && $plan->price > 0
            ? max(0, (int) floor((1 - $plan->yearly_price / ($plan->price * 12)) * 100))
            : 0;
    }

    public function value(Bundle $plan, PackageFeature $feature): string
    {
        $value = $plan->entitlement_settings[$feature->key] ?? null;
        if ($value === null || $value === '') {
            return __('Not specified');
        }
        if ($feature->type === 'boolean') {
            return $value ? __('Yes') : __('No');
        }
        if (in_array($feature->type, ['capacity', 'quota'], true)) {
            $text = (int) $value === -1 ? __('Unlimited') : number_format((int) $value);
            if ($feature->type === 'quota') {
                $period = data_get($plan->meta, 'allowance_period', 'billing_cycle');
                $text .= ' · '.__([
                    'monthly' => 'Per month', 'trial' => 'Total during trial',
                    'billing_cycle' => 'Per billing cycle',
                ][$period] ?? 'Per billing cycle');
            }

            return $text;
        }

        return __((string) $value);
    }

    public function highlights(Bundle $plan, Collection $features): array
    {
        $highlights = [];
        foreach ($features as $feature) {
            $value = $plan->entitlement_settings[$feature->key] ?? null;
            if ($value === null || $value === '' || $value === false || $value === 0 || $value === 'No') {
                continue;
            }
            $highlights[] = $feature->name.': '.$this->value($plan, $feature);
        }

        return $highlights ?: (empty($plan->entitlement_settings) ? $plan->display_features : []);
    }

    public function additionalBenefits(Bundle $plan): array
    {
        $source = data_get($plan->meta, 'display_features_source', data_get($plan->meta, 'reference_allocation') ? 'catalog' : 'manual');

        return $source === 'manual' ? $plan->display_features : [];
    }
}
