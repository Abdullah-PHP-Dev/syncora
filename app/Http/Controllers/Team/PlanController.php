<?php

namespace App\Http\Controllers\Team;

use App\Http\Controllers\Controller;
use App\Models\Bundle;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PlanController extends Controller
{
    public function index() { return view('team.plans.index', ['plans' => Bundle::orderBy('sort_order')->paginate(20)]); }
    public function create() { return view('team.plans.form', ['plan' => new Bundle]); }
    public function edit(Bundle $plan) { return view('team.plans.form', compact('plan')); }
    public function store(Request $request) { return $this->save($request, new Bundle); }
    public function update(Request $request, Bundle $plan) { return $this->save($request, $plan); }

    private function save(Request $request, Bundle $plan)
    {
        $data = $request->validate([
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
        ]);
        if ($data['is_free'] && ((float) $data['price'] !== 0.0 || (float) $data['yearly_price'] !== 0.0)) {
            return back()->withErrors(['price' => __('Free plans must have zero prices.')])->withInput();
        }
        $plan->fill(collect($data)->only(['name_en', 'name_ar', 'slug', 'price', 'currency', 'sort_order', 'is_active', 'is_popular', 'is_free'])->all());
        $meta = $plan->meta ?? [];
        foreach (['yearly_price', 'description_en', 'description_ar', 'trial_days'] as $key) { $meta[$key] = $data[$key] ?? null; }
        foreach (['en', 'ar'] as $locale) {
            $meta['display_features_'.$locale] = array_values(array_filter(array_map('trim', preg_split('/\R/u', $data['features_'.$locale] ?? ''))));
        }
        $plan->meta = $meta;
        $plan->save();
        return redirect()->route('plans.index')->with('success', __('Plan saved.'));
    }
}
