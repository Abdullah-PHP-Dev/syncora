<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Bundle;
use App\Models\Package;
use App\Models\SellerBundle;
use App\Services\SubscriptionService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\JsonResponse;

class SubscriptionController extends Controller
{

	/**
	 * @param SubscriptionService $subscriptionService
	 */
	public function __construct(private SubscriptionService $subscriptionService ) {

	}
	/**
	 * Show all packages
	 */
	public function select()
	{
		$packages = Bundle::where('is_active', true)->where('is_free', false)
			->orderBy('sort_order')
			->get();

		return view('admin.subscriptions.subs', compact('packages'));
	}

	/**
	 * Checkout page (confirm package)
	 */
	public function checkout(Request $request)
	{

		$package = Bundle::findOrFail($request->package_id);

		return view('admin.subscriptions.checkout', compact('package'));
	}

	public function showCheckout(Request $request)
	{
		$planId = $request->get('plan_id');
		$cycle = $request->get('cycle', 'monthly');

		$package = Bundle::findOrFail($planId);

		return view('admin.subscriptions.checkout', compact('package',
			'planId',
			'cycle'
		));
	}



	/**
	 * Activate subscription (after payment success)
	 */
	public function activate(Request $request)
	{
		$user = auth()->user();
		$seller = $user->seller ?? $user;

		$package = Package::findOrFail($request->package_id);

		SellerBundle::updateOrCreate(
			['seller_id' => $seller->id],
			[
				'package_id' => $package->id,
				'price' => $package->price,
				'currency' => $package->currency,
				'status' => 'active',
				'payment_status' => 'paid',
				'starts_at' => Carbon::now(),
				'expires_at' => Carbon::now()->addMonth(),
				'payment_gateway' => 'manual',
				'transaction_id' => null,
				'auto_renew' => false,
			]
		);

		return redirect('/admin/dashboard')
			->with('success', 'Subscription activated successfully!');
	}

	/**
	 * Cancel subscription
	 */
	public function cancel()
	{
		$user = auth()->user();
		$seller = $user->seller ?? $user;

		$bundle = SellerBundle::where('seller_id', $seller->id)->first();

		if ($bundle) {
			$bundle->update([
				                'status' => 'cancelled',
				                'payment_status' => 'refunded'
			                ]);
		}

		return back()->with('success', 'Subscription cancelled');
	}
	public function plans(Request $request): JsonResponse
	{

		$user                = Auth::user();
		$currentSubscription = $user->subscription ?? null;
		$currentBundleId     = $currentSubscription ? $currentSubscription->bundle_id : null;
		$packages            = Bundle::where('is_active', true)->where('is_free', false)->orderBy('sort_order')->get();
		$packages            = $packages->values()->map(function ($package, $index) use ($currentBundleId) {
			$monthlyPrice  = (float)$package->price;
			$yearlyPrice   = (float)data_get($package->meta, 'yearly_price', $monthlyPrice * 12);
			$monthlyPlanId = $package->monthly_plan_id ?? $package->id;
			$yearlyPlanId  = $package->yearly_plan_id ?? $package->id;
			$features      = is_array($package->features) ? $package->features : json_decode($package->features ?? '{}', true);
			$features      = is_array($features) ? $features : [];
			$isCurrent     = $currentBundleId == $package->id;
			$isRecommended = $index === 1;


			return [

				'id'          => $package->id,
				'name'        => $package->name_en,
				'description' => $package->description_en ?? 'Everything you need to grow your business.',
				'currency'    => $package->currency ?? 'SAR',
				'monthly'     => [
					'price'   => $monthlyPrice,
					'plan_id' => $monthlyPlanId,
				],

				'yearly' => [
					'price'   => $yearlyPrice,
					'plan_id' => $yearlyPlanId,
				],

				'features'       => $features,
				'is_current'     => $isCurrent,
				'is_recommended' => $isRecommended,
				'checkout_url'   => route('admin.subscription.checkout'),

			];
		});

		$current = null;

		if ($currentSubscription) {

			$current = [

				'bundle_id'   => $currentSubscription->bundle_id,
				'bundle_name' => optional($currentSubscription->bundle)->name ?? $currentSubscription->bundle_name ?? 'Current Plan',
				'start_date'  => $currentSubscription->start_date,
				'end_date'    => $currentSubscription->end_date,
				'status'      => $currentSubscription->status,
			];
		}

		return response()->json([

			                        'success' => true,
			                        'data'    => [
				                        'current_subscription' => $current,
				                        'packages'             => $packages,
			                        ],
		                        ]);
	}

	public function checkoutData(Request $request): JsonResponse
	{
		$planId = $request->get('plan_id');
		$cycle = in_array($request->get('cycle'), ['monthly', 'yearly'])
			? $request->get('cycle')
			: 'monthly';

		$package = Bundle::where('is_active', true)
			->where('is_free', false)
			->where(function ($query) use ($planId) {
				$query->where('id', $planId)
					->orWhere('monthly_plan_id', $planId)
					->orWhere('yearly_plan_id', $planId);
			})
			->first();

		if (!$package) {
			return response()->json([
				                        'success' => false,
				                        'message' => 'Subscription plan not found.'
			                        ], 404);
		}

		$monthlyPrice = (float) $package->price;
		$yearlyPrice = (float) data_get(
			$package->meta,
			'yearly_price',
			$monthlyPrice * 12
		);

		$monthlyPlanId = $package->monthly_plan_id ?? $package->id;
		$yearlyPlanId = $package->yearly_plan_id ?? $package->id;

		$features = is_array($package->features)
			? $package->features
			: json_decode($package->features ?? '{}', true);

		$features = is_array($features) ? $features : [];

		$currentPlanId = $cycle === 'yearly'
			? $yearlyPlanId
			: $monthlyPlanId;

		return response()->json([
			                        'success' => true,
			                        'data' => [
				                        'id' => $package->id,
				                        'name' => $package->name_en,
				                        'description' => $package->description_en
					                        ?? 'Everything you need to grow your business.',
				                        'currency' => $package->currency ?? 'SAR',
				                        'monthly' => [
					                        'price' => $monthlyPrice,
					                        'plan_id' => $monthlyPlanId,
				                        ],
				                        'yearly' => [
					                        'price' => $yearlyPrice,
					                        'plan_id' => $yearlyPlanId,
				                        ],
				                        'selected' => [
					                        'cycle' => $cycle,
					                        'plan_id' => $currentPlanId,
					                        'price' => $cycle === 'yearly'
						                        ? $yearlyPrice
						                        : $monthlyPrice,
				                        ],
				                        'features' => $features,
				                        'checkout_url' => route('admin.subscription.checkout.process'),
			                        ]
		                        ]);
	}



	public function checkoutProcess(Request $request)
	{
		$bundleId = $request->bundle_id;
		$planId = $request->plan_id;
		$cycle = $request->cycle;
		$paymentMethod = $request->payment_method;
		$couponCode = $request->coupon_code;
		$discount = 0;
		$action = 0;
		$bundle = Bundle::findOrFail($bundleId);

		$paymentResponse = $this->subscriptionService->checkout(
			auth()->user(),
			$bundle,
			$action,
			$paymentMethod,
			$cycle,
			$discount,
			$bundle->getMorphClass(),
			$bundle->id,
			'App\Services\SubscriptionService',
		);



		$checkoutUrl = $paymentResponse['redirect_url'];

		return response()->json([
			                        'success' => true,
			                        'checkout_url' => $checkoutUrl
		                        ]);
	}
}