<?php

namespace App\Http\Controllers\Team;

use App\Http\Controllers\Controller;
use App\Models\Bundle;
use App\Models\PackageChange;
use App\Models\PackageFeature;
use App\Services\PackageAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PlanController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['active', 'disabled'])],
        ]);
        $query = Bundle::query();
        if ($search = trim($filters['search'] ?? '')) {
            $query->where(function ($query) use ($search) {
                $query->where('name_en', 'like', '%'.$search.'%')
                    ->orWhere('name_ar', 'like', '%'.$search.'%')
                    ->orWhere('slug', 'like', '%'.$search.'%');
            });
        }
        if ($status = $filters['status'] ?? null) {
            $query->where('is_active', $status === 'active');
        }

        return view('team.plans.index', [
            'comparisonPlans' => Bundle::where('is_active', true)->orderBy('sort_order')->orderBy('id')->get(),
            'comparisonFeatures' => PackageFeature::where('is_active', true)->orderBy('sort_order')->orderBy('id')->get(),
            'plans' => $query->orderBy('sort_order')->orderBy('id')->paginate(12)->withQueryString(),
            'stats' => [
                'total' => Bundle::count(),
                'active' => Bundle::where('is_active', true)->count(),
                'recommended' => Bundle::where('is_popular', true)->count(),
            ],
        ]);
    }

    public function create()
    {
        return view('team.plans.form', ['plan' => new Bundle, 'features' => PackageFeature::where('is_active', true)->orderBy('sort_order')->orderBy('id')->get()]);
    }

    public function edit(Bundle $plan)
    {
        return view('team.plans.form', [
            'plan' => $plan,
            'features' => PackageFeature::where('is_active', true)->orderBy('sort_order')->orderBy('id')->get(),
            'changes' => PackageChange::where('entity_type', 'bundle')->where('entity_id', $plan->id)->with('actor')->latest('id')->limit(10)->get(),
        ]);
    }

    public function store(Request $request)
    {
        return $this->save($request, new Bundle);
    }

    public function update(Request $request, Bundle $plan)
    {
        return $this->save($request, $plan);
    }

    private function save(Request $request, Bundle $plan)
    {
        $features = PackageFeature::where('is_active', true)->get();
        $rules = [
            'version' => ['required', 'integer', 'min:0'],
            'entitlements' => $features->isNotEmpty()
                ? ['required', 'array:'.$features->pluck('key')->implode(',')]
                : ['sometimes', 'array', 'max:0'],
            'name_en' => ['required', 'string', 'max:255'], 'name_ar' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'alpha_dash', 'max:100', Rule::unique('bundles')->ignore($plan->id)],
            'price' => ['required', 'numeric', 'min:0', 'max:9999999'],
            'yearly_price' => ['required', 'numeric', 'min:0', 'max:9999999'],
            'currency' => ['required', Rule::in(['SAR', 'USD', 'AED', 'EUR', 'GBP'])],
            'description_en' => ['nullable', 'string', 'max:2000'], 'description_ar' => ['nullable', 'string', 'max:2000'],
            'features_en' => ['nullable', 'string', 'max:5000'], 'features_ar' => ['nullable', 'string', 'max:5000'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['required', 'boolean'], 'is_popular' => ['required', 'boolean'], 'is_free' => ['required', 'boolean'],
            'trial_days' => ['required', 'integer', 'min:1', 'max:365'],
            'allowance_period' => ['sometimes', Rule::in(['monthly', 'billing_cycle', 'trial'])],
            'access' => ['sometimes', 'array:'.implode(',', array_keys(config('package_access.modules')))],
        ];
        foreach (config('package_access.modules') as $module => $definition) {
            $rules['access.'.$module] = ['required_with:access', 'array:mode,tier'];
            $rules['access.'.$module.'.mode'] = ['required_with:access', Rule::in(['disabled', 'preview', 'included', 'addon_required'])];
            $rules['access.'.$module.'.tier'] = ['required_with:access', Rule::in(['none', 'standard', 'basic', 'limited', 'expanded', 'full', 'advanced', 'preview', 'addon', 'included', 'multi_user'])];
        }
        foreach ($features as $feature) {
            $rules['entitlements.'.$feature->key] = match ($feature->type) {
                'boolean' => ['required', 'boolean'],
                'capacity', 'quota' => ['required', 'integer', 'min:-1', 'max:1000000000'],
                'benefit' => ['nullable', 'string', 'max:500'],
            };
        }
        $data = $request->validate($rules);
        if ($data['is_free'] && ((float) $data['price'] !== 0.0 || (float) $data['yearly_price'] !== 0.0)) {
            return back()->withErrors(['price' => __('Free plans must have zero prices.')])->withInput();
        }
        $isUpdate = $plan->exists;
        $savedPlan = DB::transaction(function () use ($plan, $data, $features, $request) {
            // Lock the catalog in a consistent order before changing recommendations.
            $catalog = Bundle::orderBy('id')->lockForUpdate()->get();
            if ($plan->exists) {
                $plan = $catalog->firstWhere('id', $plan->id);
                abort_unless($plan, 404);
                if ((int) $data['version'] !== (int) $plan->version) {
                    throw ValidationException::withMessages(['version' => __('This record was changed by another administrator. Reload the page before saving.')]);
                }
            }
            if ($data['is_popular']) {
                foreach ($catalog as $otherPlan) {
                    if ($otherPlan->id === $plan->id || ! $otherPlan->is_popular) {
                        continue;
                    }
                    $previous = $otherPlan->toArray();
                    $otherPlan->is_popular = false;
                    $otherPlan->version++;
                    $otherPlan->save();
                    PackageChange::create(['entity_type' => 'bundle', 'entity_id' => $otherPlan->id, 'actor_id' => $request->user()->id, 'before' => $previous, 'after' => $otherPlan->fresh()->toArray()]);
                }
            }
            $before = $plan->exists ? $plan->toArray() : null;
            $plan->fill(collect($data)->only(['name_en', 'name_ar', 'slug', 'price', 'currency', 'sort_order', 'is_active', 'is_popular', 'is_free'])->all());
            $meta = $plan->meta ?? [];
            $meta['allowance_period'] = $data['allowance_period'] ?? $meta['allowance_period'] ?? 'billing_cycle';
            foreach (['yearly_price', 'description_en', 'description_ar', 'trial_days'] as $key) {
                $meta[$key] = $data[$key] ?? null;
            }
            foreach (['en', 'ar'] as $locale) {
                $meta['display_features_'.$locale] = array_values(array_filter(array_map('trim', preg_split('/\\R/u', $data['features_'.$locale] ?? '')), fn ($value) => $value !== ''));
                if ($meta['display_features_'.$locale] !== data_get($plan->meta, 'display_features_'.$locale, [])) {
                    $meta['display_features_source'] = 'manual';
                }
            }
            $plan->meta = $meta;
            $settings = $plan->entitlement_settings ?? [];
            foreach ($features as $feature) {
                $value = $data['entitlements'][$feature->key] ?? '';
                $settings[$feature->key] = match ($feature->type) {
                    'boolean' => (bool) $value,
                    'capacity', 'quota' => (int) $value,
                    'benefit' => (string) $value,
                };
            }
            $plan->entitlement_settings = $settings;
            $plan->access_settings = $data['access'] ?? $plan->access_settings ?? PackageAccessService::initialSettings($plan);
            if (! $plan->exists || $plan->isDirty()) {
                $plan->version = $plan->exists ? $plan->version + 1 : 1;
                $plan->save();
                PackageChange::create(['entity_type' => 'bundle', 'entity_id' => $plan->id, 'actor_id' => $request->user()->id, 'before' => $before, 'after' => $plan->fresh()->toArray()]);
            }

            return $plan;
        }, 3);

        return ($isUpdate ? redirect()->route('plans.edit', $savedPlan) : redirect()->route('plans.index'))
            ->with('success', __('Plan saved.'));
    }
}
