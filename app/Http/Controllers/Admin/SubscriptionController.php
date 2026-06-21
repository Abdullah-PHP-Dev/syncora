<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Models\SellerBundle;
use Carbon\Carbon;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
	/**
	 * Show all packages
	 */
	public function select()
	{
		$packages = Package::where('is_active', true)
			->orderBy('sort_order')
			->get();

		return view('admin.subscriptions.index', compact('packages'));
	}

	/**
	 * Checkout page (confirm package)
	 */
	public function checkout(Request $request)
	{
		$package = Package::findOrFail($request->package_id);

		return view('admin.subscriptions.checkout', compact('package'));
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
}