<?php

namespace App\Services;

use App\Models\Bundle;
use App\Models\Subscription;
use App\Models\SubscriptionCycle;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SubscriptionService
{
	private const DEFAULT_TRIAL_DAYS = 15;
	private const DEFAULT_DURATION_DAYS = 30;
	private const DEFAULT_SUBSCRIPTION = 'subscription';

	public function assignFreeTrial(User $user): Subscription
	{
		$bundle = Bundle::query()
			->where('is_free', true)
			->where('is_active', true)
			->first();

		if (! $bundle) {
			throw new RuntimeException('Free bundle not found.');
		}

		[$start, $end] = $this->resolveDates(
			data_get($bundle->meta, 'trial_days', self::DEFAULT_TRIAL_DAYS)
		);

		return DB::transaction(function () use ($user, $bundle, $start, $end) {
			$subscription = Subscription::updateOrCreate(
				['user_id' => $user->id],
				[
					'bundle_id'    => $bundle->id,
					'bundle_name'  => $bundle->name_en,
					'start_date'   => $start,
					'end_date'     => $end,
					'status'       => 'trial',
					'is_active'    => true,
				]
			);

			$this->createCycle($subscription, $bundle->id, 'trial', $start, $end);

			return $subscription;
		});
	}

	public function renew(Subscription $subscription, string $cycle): Subscription
	{
		$bundle = Bundle::findOrFail($subscription->bundle_id);

		$months = $this->resolveCycleToMonths($cycle);

		[$start, $end] = $this->resolveDates($months);

		return DB::transaction(function () use ($subscription, $bundle, $start, $end) {

			$subscription->update([
				                      'start_date' => $start,
				                      'end_date'   => $end,
				                      'status'     => 'active',
				                      'is_active'  => true,
			                      ]);

			$this->createCycle(
				$subscription,
				$bundle->id,
				'renewal',
				$start,
				$end
			);

			return $subscription->fresh();
		});
	}

	public function upgrade(
		Subscription $subscription,
		Bundle $newBundle,
		string $cycle
	): Subscription
	{
		$months = $this->resolveCycleToMonths($cycle);

		[$start, $end] = $this->resolveDates($months);

		return DB::transaction(function () use ($subscription, $newBundle, $start, $end) {

			$subscription->update([
				                      'bundle_id'   => $newBundle->id,
				                      'bundle_name' => $newBundle->name_en,
				                      'start_date'  => $start,
				                      'end_date'    => $end,
				                      'status'      => 'active',
				                      'is_active'   => true,
			                      ]);

			$this->createCycle(
				$subscription,
				$newBundle->id,
				'upgrade',
				$start,
				$end
			);

			return $subscription->fresh();
		});
	}

	private function createCycle(
		Subscription $subscription,
		int $bundleId,
		string $type,
		Carbon $start,
		Carbon $end
	): void {
		SubscriptionCycle::create([
			                          'subscription_id' => $subscription->id,
			                          'user_id'         => $subscription->user_id,
			                          'bundle_id'       => $bundleId,
			                          'start_date'      => $start,
			                          'end_date'        => $end,
			                          'type'            => $type,
			                          'status'          => 'active',
		                          ]);
	}

	private function resolveDates(int $months): array
	{
		$start = now();

		return [$start, $start->copy()->addMonths($months)];
	}

	public function checkout(
		User $user,
		Bundle $bundle,
		string $action,
		string $paymentMethod,
		string $cycle,
		float $discount,
		string $subjectType,
		string $subjectId,
		string $callback
	) {

		$user = $user;
		$amount = $bundle['price'] * $this->resolveCycleToMonths($cycle);
		$plan = $bundle;
		$cycle = $cycle;
		$this->validatePaymentAttempts($user);


		$transaction = WalletTransaction::create([
			                                         'seller_id' => $user->id,
			                                         'amount' => $amount,
			                                         'direction' => 'debit',
			                                         'category' => self::DEFAULT_SUBSCRIPTION,
			                                         'status' => 'pending',
			                                         'subject_type' => $subjectType,
			                                         'subject_id' => $subjectId,
			                                         'callback' => $callback,
			                                         'meta' => [
				                                         'plan' => $plan->slug,
				                                         'bundle_id' => $plan->bundle_id,
				                                         'cycle' => $cycle,
				                                         'starts_at' => now()->toISOString(),
			                                         ],
		                                         ]);


		$payment = app(PaymentManager::class)
			->driver($paymentMethod)
			->pay([
				      'user' => $user,
				      'amount' => $amount,
				      'bundle' => $bundle,
				      'cycle' => $cycle,
				      'type' => self::DEFAULT_SUBSCRIPTION,
				      'transaction' => $transaction,
			      ]);

		/**
		 * CASE 1: PAYMENT FAILED
		 */
		if ($payment['status'] === 'failed') {
			return [
				'success' => false,
				'message' => $payment['message'] ?? 'Payment failed'
			];
		}

		/**
		 * CASE 2: CARD PAYMENT (REDIRECT FLOW)
		 */
		if ($payment['status'] === 'pending') {


			return [
				'success' => true,
				'payment_type' => 'redirect',
				'redirect_url' => $payment['redirect_url'],
				'transaction_id' => $payment['transaction_id'] ?? null,
				'message' => 'Redirecting to payment gateway'
			];
		}

		/**
		 * CASE 3: WALLET / INSTANT PAYMENT
		 */
		if ($payment['status'] === 'paid') {

			$subscription = $user->subscription;


			$action = 'upgrade';
			$result = match ($action) {
				'renew' => $this->renew($subscription),
				'upgrade' => $this->upgrade($subscription, $bundle, $cycle),
				default => throw new \Exception('Invalid action')
			};

			return [
				'success' => true,
				'payment_type' => 'instant',
				'data' => $result,
				'transaction_id' => $payment['transaction_id'],
				'id' => $payment['id']
			];
		}

		return [
			'success' => false,
			'message' => 'Unknown payment state'
		];
	}


	private function resolveCycleToMonths(string $cycle): int
	{
		return match ($cycle) {
			'monthly' => 1,
			'yearly'  => 12,
			default   => throw new \Exception("Invalid cycle: {$cycle}"),
		};
	}


	private function validatePaymentAttempts(User $user): void
	{
		$failedAttempts = WalletTransaction::where('seller_id', $user->id)
			->whereIn('status', ['pending', 'rejected'])
			->whereDate('created_at', today())
			/* ->whereIn('failure_reason', [
				 'insufficient_balance',
				 'payment_declined',
				 'abandoned'
			 ])*/
			->count();

		if ($failedAttempts >= 5) {
			throw new \RuntimeException(
				'Too many failed payment attempts. Please try again tomorrow.'
			);
		}
	}


	public function processSuccessfulPayment(WalletTransaction $transaction)
	{


		/*return DB::transaction(function () use ($transaction) {*/

		$user = $transaction->seller;
		$subscription = $user->subscription;


		$bundle = Bundle::where('slug',$transaction->meta['plan'])->first();
		$action = $transaction->meta['action'] ?? 'renew';
		$cycle  = $transaction->meta['cycle'] ?? 'monthly';

		// resolve subscription duration
		[$start, $end] = $this->resolveDates(
			data_get($bundle->meta, 'duration_days', 30)
		);

		switch ($action) {

			case 'renew':

				$subscription->update([
					                      'start_date' => $start,
					                      'end_date'   => $end,
					                      'status'     => 'active',
					                      'is_active'  => true,
				                      ]);

				$this->createCycle(
					$subscription,
					$bundle->id,
					'renewal',
					$start,
					$end
				);

				break;

			case 'upgrade':

				$subscription->update([
					                      'bundle_id'   => $bundle->id,
					                      'bundle_name' => $bundle->name_en,
					                      'start_date'  => $start,
					                      'end_date'    => $end,
					                      'status'      => 'active',
					                      'is_active'   => true,
				                      ]);

				$this->createCycle(
					$subscription,
					$bundle->id,
					'upgrade',
					$start,
					$end
				);

				break;

			default:
				throw new \RuntimeException('Invalid subscription action');
		}

		$transaction->fresh();
		return redirect()->route('subscription.success',$transaction->id );

		/* });*/
	}

	public function success($transaction) {
		return redirect()->route('subscription.success', $transaction->id);
	}

	public function fail($transaction) {
		return redirect()->route('subscription.failed');
	}
}
