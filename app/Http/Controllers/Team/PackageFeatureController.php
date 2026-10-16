<?php

namespace App\Http\Controllers\Team;

use App\Http\Controllers\Controller;
use App\Models\PackageChange;
use App\Models\PackageFeature;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PackageFeatureController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', Rule::in(['boolean', 'capacity', 'quota', 'benefit'])],
        ]);
        $features = PackageFeature::query()
            ->when(trim($filters['search'] ?? ''), fn ($q, $search) => $q->where(fn ($q) => $q
                ->where('name_en', 'like', '%'.$search.'%')->orWhere('name_ar', 'like', '%'.$search.'%')->orWhere('key', 'like', '%'.$search.'%')))
            ->when($filters['type'] ?? null, fn ($q, $type) => $q->where('type', $type))
            ->orderBy('sort_order')->orderBy('id')->paginate(20)->withQueryString();

        return view('team.features.index', compact('features'));
    }

    public function create()
    {
        return view('team.features.form', ['feature' => new PackageFeature]);
    }

    public function edit(PackageFeature $feature)
    {
        return view('team.features.form', [
            'feature' => $feature,
            'changes' => PackageChange::where('entity_type', 'feature')->where('entity_id', $feature->id)->with('actor')->latest('id')->limit(10)->get(),
        ]);
    }

    public function store(Request $request)
    {
        return $this->save($request, new PackageFeature);
    }

    public function update(Request $request, PackageFeature $feature)
    {
        return $this->save($request, $feature);
    }

    private function save(Request $request, PackageFeature $feature)
    {
        $data = $request->validate([
            'key' => ['required', 'regex:/^[a-z][a-z0-9_]*$/', 'max:100', Rule::unique('package_features')->ignore($feature->id)],
            'type' => ['required', Rule::in(['boolean', 'capacity', 'quota', 'benefit'])],
            'name_en' => ['required', 'string', 'max:255'], 'name_ar' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['required', 'boolean'],
            'version' => ['required', 'integer', 'min:0'],
        ]);
        DB::transaction(function () use ($feature, $data, $request) {
            if ($feature->exists) {
                $feature = PackageFeature::lockForUpdate()->findOrFail($feature->id);
                if ($feature->key !== $data['key'] || $feature->type !== $data['type']) {
                    throw ValidationException::withMessages(['key' => __('Feature codes and types cannot be changed after creation.')]);
                }
                if ((int) $data['version'] !== (int) $feature->version) {
                    throw ValidationException::withMessages(['version' => __('This record was changed by another administrator. Reload the page before saving.')]);
                }
            }
            $before = $feature->exists ? $feature->toArray() : null;
            $feature->fill(collect($data)->except('version')->all());
            if (! $feature->exists || $feature->isDirty()) {
                $feature->version = $feature->exists ? $feature->version + 1 : 1;
                $feature->save();
                PackageChange::create(['entity_type' => 'feature', 'entity_id' => $feature->id, 'actor_id' => $request->user()->id, 'before' => $before, 'after' => $feature->fresh()->toArray()]);
            }
        });

        return redirect()->route('package-features.index')->with('success', __('Feature saved.'));
    }
}
