<?php

namespace App\Http\Controllers\Team;

use App\Http\Controllers\Controller;
use App\Models\Bundle;
use App\Models\Coupon;
use App\Models\CouponChange;
use App\Models\CouponRedemption;
use App\Models\User;
use App\Services\CouponService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CouponController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', Rule::in(['active', 'inactive', 'archived'])]]);
        $query = Coupon::query()->withCount(['redemptions as completed_count' => fn ($q) => $q->where('status', 'completed'), 'redemptions as reserved_count' => fn ($q) => $q->where('status', 'reserved')]);
        if ($search = trim($filters['search'] ?? '')) {
            $query->where(fn ($q) => $q->where('code', 'like', '%'.$search.'%')->orWhere('name', 'like', '%'.$search.'%'));
        }
        if (($filters['status'] ?? '') === 'archived') {
            $query->onlyTrashed();
        } elseif (isset($filters['status'])) {
            $query->where('is_active', $filters['status'] === 'active');
        }

        return view('team.coupons.index', ['coupons' => $query->latest('id')->paginate(15)->withQueryString(), 'stats' => ['total' => Coupon::count(), 'completed' => CouponRedemption::where('status', 'completed')->count(), 'discount' => CouponRedemption::where('status', 'completed')->selectRaw('currency, SUM(discount) as amount')->groupBy('currency')->get()]]);
    }

    public function create()
    {
        return $this->form(new Coupon(['type' => 'percentage', 'currency' => 'SAR', 'minimum_amount' => 0, 'per_user_limit' => 1, 'cycles' => ['monthly', 'yearly'], 'is_active' => true, 'allow_yearly_discount' => true]));
    }

    public function edit(Coupon $coupon)
    {
        return $this->form($coupon);
    }

    private function form(Coupon $coupon)
    {
        return view('team.coupons.form', [
            'coupon' => $coupon, 'bundles' => Bundle::orderBy('sort_order')->get(),
            'sellers' => User::role('seller')->whereDoesntHave('roles', fn ($q) => $q->whereIn('name', ['admin', 'customer_support']))->orderBy('name')->get(['id', 'name', 'email']),
            'redemptions' => $coupon->exists ? $coupon->redemptions()->with('user')->latest('id')->paginate(15, ['*'], 'redemptions') : collect(),
            'changes' => $coupon->exists ? $coupon->changes()->with('actor')->latest('id')->limit(10)->get() : collect(),
        ]);
    }

    public function store(Request $request)
    {
        return $this->save($request, new Coupon);
    }

    public function update(Request $request, Coupon $coupon)
    {
        return $this->save($request, $coupon);
    }

    private function save(Request $request, Coupon $coupon)
    {
        abort_if($coupon->trashed(), 422, __('coupons.restore_first'));
        if (is_string($request->input('code'))) {
            $request->merge(['code' => CouponService::normalize($request->input('code'))]);
        }
        $data = $request->validate([
            'version' => ['required', 'integer', 'min:0'],
            'name' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string', 'max:2000'],
            'code' => ['required', 'string', 'regex:/^[A-Z0-9][A-Z0-9_-]{2,49}$/D', Rule::unique('coupons')->ignore($coupon->id)],
            'type' => ['required', Rule::in(['fixed', 'percentage'])],
            'value' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:'.($request->input('type') === 'percentage' ? '100' : '9999999')],
            'currency' => ['required', Rule::in(['SAR', 'USD', 'AED', 'EUR', 'GBP'])],
            'minimum_amount' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999'],
            'maximum_discount' => ['nullable', 'numeric', 'decimal:0,2', 'min:0.01', 'max:9999999'],
            'discount_budget' => ['nullable', 'numeric', 'decimal:0,2', 'min:0.01', 'max:999999999'],
            'usage_limit' => ['nullable', 'integer', 'min:1', 'max:1000000000'], 'per_user_limit' => ['nullable', 'integer', 'min:1', 'max:1000000000'],
            'bundle_ids' => ['nullable', 'array', 'max:500'], 'bundle_ids.*' => ['integer', 'distinct', 'exists:bundles,id'],
            'seller_ids' => ['nullable', 'array', 'max:500'], 'seller_ids.*' => ['integer', 'distinct', 'exists:users,id'],
            'cycles' => ['required', 'array', 'min:1', 'max:2'], 'cycles.*' => ['required', 'distinct', Rule::in(['monthly', 'yearly'])],
            'payment_methods' => ['nullable', 'array', 'max:5'], 'payment_methods.*' => ['required', 'distinct', Rule::in(['card', 'wallet', 'cashback', 'tamara', 'tabby'])],
            'is_active' => ['required', 'boolean'], 'first_purchase_only' => ['required', 'boolean'], 'allow_yearly_discount' => ['required', 'boolean'],
            'starts_at' => ['nullable', 'date'], 'ends_at' => ['nullable', 'date', ...($request->filled('starts_at') ? ['after:starts_at'] : [])],
        ], ['code.regex' => __('coupons.code_format')]);
        foreach (['description', 'maximum_discount', 'discount_budget', 'usage_limit', 'per_user_limit', 'starts_at', 'ends_at'] as $key) {
            $data[$key] ??= null;
        }
        // A nullable start date still permits an explicit expiry.
        foreach (['bundle_ids', 'seller_ids', 'payment_methods'] as $key) {
            $data[$key] = array_values($data[$key] ?? []);
        }
        foreach (['bundle_ids', 'seller_ids'] as $key) {
            $data[$key] = array_map('intval', $data[$key]);
        }
        if ($data['seller_ids'] && User::role('seller')->whereDoesntHave('roles', fn ($q) => $q->whereIn('name', ['admin', 'customer_support']))->whereIn('id', $data['seller_ids'])->count() !== count($data['seller_ids'])) {
            throw ValidationException::withMessages(['seller_ids' => __('coupons.sellers_only')]);
        }
        DB::transaction(function () use ($coupon, $data, $request) {
            if ($coupon->exists) {
                $coupon = Coupon::whereKey($coupon->id)->lockForUpdate()->firstOrFail();
                if ($coupon->version !== (int) $data['version']) {
                    throw ValidationException::withMessages(['version' => __('coupons.stale')]);
                }
                if ($coupon->code !== $data['code'] && $coupon->redemptions()->exists()) {
                    throw ValidationException::withMessages(['code' => __('coupons.immutable_code')]);
                }
                if ($coupon->currency !== $data['currency'] && $coupon->redemptions()->exists()) {
                    throw ValidationException::withMessages(['currency' => __('coupons.immutable_currency')]);
                }
                $claimed = $coupon->redemptions()->claimed();
                if ($data['usage_limit'] !== null && (clone $claimed)->count() > $data['usage_limit']) {
                    throw ValidationException::withMessages(['usage_limit' => __('coupons.below_usage')]);
                }
                if ($data['discount_budget'] !== null && CouponService::minor((clone $claimed)->sum('discount')) > CouponService::minor($data['discount_budget'])) {
                    throw ValidationException::withMessages(['discount_budget' => __('coupons.below_usage')]);
                }
                if ($data['per_user_limit'] !== null && (clone $claimed)->selectRaw('user_id, COUNT(*) as uses')->groupBy('user_id')->havingRaw('COUNT(*) > ?', [$data['per_user_limit']])->exists()) {
                    throw ValidationException::withMessages(['per_user_limit' => __('coupons.below_usage')]);
                }
            }
            $before = $coupon->exists ? $coupon->toArray() : null;
            $coupon->fill(collect($data)->except('version')->all());
            $coupon->version = $coupon->exists ? $coupon->version + 1 : 1;
            $coupon->save();
            CouponChange::create(['coupon_id' => $coupon->id, 'actor_id' => $request->user()->id, 'before' => $before, 'after' => $coupon->fresh()->toArray()]);
        }, 3);

        // Retrieve by the normalized unique code for both creates and updates.
        return redirect()->route('coupons.edit', Coupon::where('code', $data['code'])->firstOrFail())->with('success', __('coupons.saved'));
    }

    public function destroy(Request $request, Coupon $coupon)
    {
        $this->archive($request, $coupon, true);

        return redirect()->route('coupons.index')->with('success', __('coupons.archived'));
    }

    public function restore(Request $request, Coupon $coupon)
    {
        $this->archive($request, $coupon, false);

        return redirect()->route('coupons.edit', $coupon)->with('success', __('coupons.restored'));
    }

    private function archive(Request $request, Coupon $coupon, bool $archive): void
    {
        $data = $request->validate(['version' => ['required', 'integer']]);
        DB::transaction(function () use ($coupon, $request, $archive, $data) {
            $coupon = Coupon::withTrashed()->whereKey($coupon->id)->lockForUpdate()->firstOrFail();
            if ($coupon->version !== (int) $data['version']) {
                throw ValidationException::withMessages(['version' => __('coupons.stale')]);
            }
            $before = $coupon->toArray();
            $coupon->is_active = false;
            $coupon->version++;
            $coupon->save();
            $archive ? $coupon->delete() : $coupon->restore();
            CouponChange::create(['coupon_id' => $coupon->id, 'actor_id' => $request->user()->id, 'before' => $before, 'after' => $coupon->fresh()->toArray()]);
        });
    }
}
