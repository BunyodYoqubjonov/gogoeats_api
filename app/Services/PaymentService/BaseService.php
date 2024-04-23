<?php

namespace App\Services\PaymentService;

use App\Helpers\NotificationHelper;
use App\Helpers\ResponseError;
use App\Models\Cart;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentProcess;
use App\Models\Payout;
use App\Models\PushNotification;
use App\Models\Settings;
use App\Models\Shop;
use App\Models\Subscription;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserSubscription;
use App\Models\WalletHistory;
use App\Services\CoreService;
use App\Services\OrderService\OrderService;
use App\Services\SubscriptionService\SubscriptionService;
use App\Traits\Notification;
use Illuminate\Support\Str;
use Log;
use Throwable;

class BaseService extends CoreService
{
	use Notification;

	protected function getModelClass(): string
	{
		return Payout::class;
	}

	public function afterHook($token, $status) {

		$paymentProcess = PaymentProcess::with(['model'])
			->where('id', $token)
			->first();

		if(empty($paymentProcess)) {
			return;
		}

		/** @var PaymentProcess $paymentProcess */
		if ($paymentProcess->model_type === Subscription::class) {
			$subscription = $paymentProcess->model;

			$shop = Shop::find(data_get($paymentProcess->data, 'shop_id'));

			$shopSubscription = (new SubscriptionService)->subscriptionAttach(
				$subscription,
				(int)$shop?->id,
				$status === 'paid' ? 1 : 0
			);

			$shopSubscription->createTransaction([
				'price'              => $shopSubscription->price,
				'user_id'            => $shop?->user_id,
				'payment_sys_id'     => Payment::where('tag', 'stripe')->first()?->id,
				'payment_trx_id'     => $token,
				'note'               => $shopSubscription->id,
				'perform_time'       => now(),
				'status_description' => "Transaction for model #$shopSubscription->id",
				'status'             => $status,
			]);

			return;
		}

        if ($paymentProcess->model_type === UserSubscription::class) {

			/** @var UserSubscription $subscription */
			$subscription = $paymentProcess->model;

			if ($status === Transaction::STATUS_PAID) {
				$subscription->update(['active' => 1]);
			}

			$subscription->createTransaction([
				'price'              => $subscription->price,
				'user_id'            => $subscription->user_id,
				'payment_sys_id'     => Payment::where('tag', 'stripe')->first()?->id,
				'payment_trx_id'     => $token,
				'note'               => $subscription->id,
				'perform_time'       => now(),
				'status_description' => "Transaction for model #$subscription->id",
				'status'             => $status,
			]);

            return;
        }

		if ($paymentProcess->model_type === Cart::class && $status === Transaction::STATUS_PAID) {

			try {
				$result = (new OrderService)->create($paymentProcess->data['cart']);

				$tokens = $this->tokens($result);

				/** @var Order $order */
				$order = $result['data'];

				$payment = Payment::where('tag', 'stripe')->first()?->id;

				$order?->createTransaction([
					'price'              => $order->total_price,
					'user_id'            => $order->user_id,
					'payment_sys_id'     => $payment ?? Payment::first()?->id,
					'payment_trx_id'     => $token,
					'note'               => $order->id,
					'perform_time'       => now(),
					'status_description' => "Transaction for model #$order->id",
					'status'             => $status,
				]);

				$this->sendNotification(
					data_get($tokens, 'tokens'),
					__('errors.' . ResponseError::NEW_ORDER, ['id' => data_get($result, 'data.id')], $this->language),
					data_get($result, 'data.id'),
					data_get($result, 'data')?->setAttribute('type', PushNotification::NEW_ORDER)?->only(['id', 'status', 'type']),
					data_get($tokens, 'ids', [])
				);

				if ((int)data_get(Settings::where('key', 'order_auto_approved')->first(), 'value') === 1) {
					(new NotificationHelper)->autoAcceptNotification(
						data_get($result, 'data'),
						$this->language,
						Order::STATUS_ACCEPTED
					);
				}

			} catch (Throwable $e) {
//				$this->error($e);
			}

			return;
		}

		$userId = data_get($paymentProcess->data, 'user_id');
		$type   = data_get($paymentProcess->data, 'type');

		if ($userId && $type === 'wallet') {

			$trxId       = data_get($paymentProcess->data, 'trx_id');
			$transaction = Transaction::find($trxId);

			$transaction->update([
				'payment_trx_id' => $token,
				'status'         => $status,
			]);

			if ($status === WalletHistory::PAID) {

				$user = User::find($userId);

				$user?->wallet?->increment('price', data_get($paymentProcess->data, 'price'));

				$user->wallet->histories()->create([
					'uuid'              => Str::uuid(),
					'transaction_id'    => $transaction->id,
					'type'              => 'topup',
					'price'             => $transaction->price,
					'note'              => "Payment top up via Wallet" ,
					'status'            => WalletHistory::PAID,
					'created_by'        => $transaction->user_id,
				]);

			}

            return;
		}

        if ($paymentProcess->model_type !== Cart::class) {

			$paymentProcess->fresh(['model.transaction']);

			$paymentProcess->model?->createTransaction([
				'price'              => $paymentProcess->model->total_price,
				'user_id'            => $paymentProcess->model->user_id,
				'payment_sys_id'     => $payment?->id ?? Payment::first()?->id,
				'payment_trx_id'     => $token,
				'note'               => $paymentProcess->model->id,
				'perform_time'       => now(),
				'status_description' => "Transaction for model #{$paymentProcess->model->id}",
				'status'             => $status,
			]);

		}

	}

	public function tokens($result): array
	{
		$adminFirebaseTokens = User::with([
			'roles' => fn($q) => $q->where('name', 'admin')
		])
			->whereHas('roles', fn($q) => $q->where('name', 'admin') )
			->whereNotNull('firebase_token')
			->pluck('firebase_token', 'id')
			->toArray();

		$sellersFirebaseTokens = User::with([
			'shop' => fn($q) => $q->where('id', data_get($result, 'data.shop_id'))
		])
			->whereHas('shop', fn($q) => $q->where('id', data_get($result, 'data.shop_id')))
			->whereNotNull('firebase_token')
			->pluck('firebase_token', 'id')
			->toArray();

		$aTokens = [];
		$sTokens = [];

		foreach ($adminFirebaseTokens as $adminToken) {
			$aTokens = array_merge($aTokens, is_array($adminToken) ? array_values($adminToken) : [$adminToken]);
		}

		foreach ($sellersFirebaseTokens as $sellerToken) {
			$sTokens = array_merge($sTokens, is_array($sellerToken) ? array_values($sellerToken) : [$sellerToken]);
		}

		return [
			'tokens' => array_values(array_unique(array_merge($aTokens, $sTokens))),
			'ids'    => array_merge(array_keys($adminFirebaseTokens), array_keys($sellersFirebaseTokens))
		];
	}
}
