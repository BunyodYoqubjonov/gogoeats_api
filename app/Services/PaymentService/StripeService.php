<?php

namespace App\Services\PaymentService;

use App\Helpers\Utility;
use App\Models\Cart;
use App\Models\ParcelOrder;
use App\Models\Payment;
use App\Models\PaymentPayload;
use App\Models\PaymentProcess;
use App\Models\Payout;
use App\Models\Shop;
use App\Models\Subscription;
use App\Models\UserSubscription;
use App\Repositories\CartRepository\CartRepository;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Str;
use Stripe\Checkout\Session;
use Stripe\Exception\ApiErrorException;
use Stripe\PaymentIntent;
use Stripe\Stripe;
use Throwable;

class StripeService extends BaseService
{
    protected function getModelClass(): string
    {
        return Payout::class;
    }

	/**
	 * @param array $data
	 * @param array $types
	 * @return PaymentProcess|Model
	 * @throws ApiErrorException|Exception
	 */
    public function orderProcessTransaction(array $data, array $types = ['card']): Model|PaymentProcess
    {
        $payment = Payment::where('tag', Payment::TAG_STRIPE)->first();

        $paymentPayload = PaymentPayload::where('payment_id', $payment?->id)->first();
        $payload        = $paymentPayload?->payload;

        Stripe::setApiKey(data_get($payload, 'stripe_sk'));

        /** @var Cart $model */
        $model = data_get($data, 'parcel_id')
            ? ParcelOrder::find(data_get($data, 'parcel_id'))
            : Cart::find(data_get($data, 'cart_id'));

		$totalPrice = ceil((float)$model->rate_total_price * 100);

		if (data_get($data, 'cart_id')) {

			$data['address'] = @($data['location'] ?? $data['address'] ?? []) ?? [];
			$calculate  = (new CartRepository)->calculateByCartId($model->id, $data);

			if (!data_get($calculate, 'status')) {
				throw new Exception($calculate['message'] ?? 'Cart is empty');
			}

			$totalPrice = ceil(data_get($calculate, 'data.total_price') * 100);

		}

		$host = request()->getSchemeAndHttpHost();
        $key  = data_get($data, 'parcel_id') ? 'parcel_id' : 'cart_id';
        $url  = "$host/order-stripe-success?token={CHECKOUT_SESSION_ID}&$key=$model->id";

		$data['user_id'] = $model->owner_id ?? auth('sanctum')->id();

		if (@$data['type'] === 'mobile') {

			$session = PaymentIntent::create([
				'payment_method_types' => $types,
				'currency' => Str::lower($model->currency?->title ?? data_get($payload, 'currency')),
				'amount' => $totalPrice,
			]);

			return PaymentProcess::updateOrCreate([
				'user_id'    => auth('sanctum')->id(),
				'model_id'   => $model->id,
				'model_type' => get_class($model)
			], [
				'id' => $session->id,
				'data' => [
					'client_secret' => $session->client_secret,
					'price'         => $totalPrice,
					'type'          => 'mobile',
					'cart'			=> $data
				]
			]);

		}

		$session = Session::create([
			'payment_method_types' => $types,
			'currency' => Str::lower($model->currency?->title ?? data_get($payload, 'currency')),
			'line_items' => [
				[
					'price_data' => [
						'currency' => Str::lower($model->currency?->title ?? data_get($payload, 'currency')),
						'product_data' => [
							'name' => 'Payment'
						],
						'unit_amount' => $totalPrice,
					],
					'quantity' => 1,
				]
			],
			'mode'        => 'payment',
			'success_url' => $url,
			'cancel_url'  => $url,
		]);

        return PaymentProcess::updateOrCreate([
            'user_id'    => auth('sanctum')->id(),
            'model_id'   => $model->id,
            'model_type' => get_class($model)
        ], [
            'id' => $session->payment_intent ?? $session->id,
            'data' => [
                'url'  	=> $session->url,
                'price'	=> $totalPrice,
				'cart'	=> $data
			]
        ]);
    }

	/**
	 * @param array $data
	 * @param Shop|null $shop
	 * @param $currency
	 * @param array $types
	 * @return Model|array|PaymentProcess
	 * @throws ApiErrorException
	 */
    public function subscriptionProcessTransaction(array $data, ?Shop $shop, $currency, array $types = ['card']): Model|array|PaymentProcess
    {
        $payment = Payment::where('tag', Payment::TAG_STRIPE)->first();

        $paymentPayload = PaymentPayload::where('payment_id', $payment?->id)->first();

        Stripe::setApiKey(data_get($paymentPayload?->payload, 'stripe_sk'));

        $host           = request()->getSchemeAndHttpHost();
        $subscription   = Subscription::find(data_get($data, 'subscription_id'));
		$currency	    = Str::lower(data_get($paymentPayload?->payload, 'currency', $currency));
		$url = "$host/subscription-stripe-success?token={CHECKOUT_SESSION_ID}&subscription_id=$subscription->id";

		$user = auth('sanctum')->user();
		$userSubscription = null;
		$firstly = Utility::checkFirstlySubs();

		$tax = ($subscription->price / 100 * $subscription->tax);
		$totalPrice = ceil($firstly < 60 ? 99 : ($subscription->price + $tax) * 100);

		if (empty($shop)) {
			try {
				UserSubscription::where('user_id', $user->id)
					->whereDate('expired_at', '<=', now())
					->delete();
			} catch (Throwable) {}

			$userSubscription = UserSubscription::create([
				'user_id'         => auth('sanctum')->id(),
				'subscription_id' => $subscription->id,
				'expired_at'      => date('Y-m-d', strtotime("+$subscription->month months")),
				'price'           => $totalPrice / 100,
				'type'            => data_get($subscription, 'type', 'order'),
				'active'          => 0,
			]);

//			$email = $user->email ?? Str::random() . '@gmail.com';
//			$fullName = "$user->firstname $user->lastname";
//			$email= "jamshidmirzamaxmudov3@gmail.com";
//			if ($user->stripe_token) {
//				$customer = Customer::update($user->stripe_token, [
//					'name'   => !empty($fullName) ? $fullName : "$user->phone" ?? $email,
//					'email'  => $email,
//				]);
//			} else {
//				$customer = Customer::create([
//					'name'   => !empty($fullName) ? $fullName : "$user->phone" ?? $email,
//					'email'  => $email,
//				]);
//			}
//
//			$stripeSubscription = \Stripe\Subscription::create([
//				'customer' 		   => $customer->id,
//				'payment_behavior' => 'default_incomplete',
//				'trial_settings'   => ['end_behavior' => ['missing_payment_method' => 'cancel']],
//				'items' => [
//					[
//						'price' => $subscription->data['id'],
//					],
//				],
//			])->toArray();
//
//			$userSubscription->update(['data' => $stripeSubscription]);
//
//			$session = PaymentIntent::create([
//				'payment_method_types' => $types,
//				'currency' => $currency,
//				'amount' => $totalPrice,
//			]);

//			return PaymentProcess::updateOrCreate([
//				'user_id'    => auth('sanctum')->id(),
//				'model_id'   => $subscription->id,
//				'model_type' => !empty($userSubscription) ? get_class($userSubscription) : get_class($subscription)
//			], [
//				'id' => $session->id,
//				'data' => [
//					'client_secret'   => $session->client_secret,
//					'price'           => $totalPrice,
//					'type'            => 'mobile',
//					'shop_id'         => $shop->id,
//					'subscription_id' => $subscription->id,
//					'subscription'    => $stripeSubscription,
//				]
//			]);

		}

		if (@$data['type'] === 'mobile') {

			$session = PaymentIntent::create([
				'payment_method_types' => $types,
				'currency' => $currency,
				'amount' => $totalPrice,
			]);

			return PaymentProcess::updateOrCreate([
				'user_id'    => auth('sanctum')->id(),
				'model_id'   => !empty($userSubscription) ? $userSubscription->id : $subscription->id,
				'model_type' => !empty($userSubscription) ? get_class($userSubscription) : get_class($subscription)
			], [
				'id' => $session->id,
				'data' => [
					'client_secret'   => $session->client_secret,
					'price'           => $totalPrice / 100,
					'type'            => 'mobile',
					'shop_id'         => $shop?->id,
					'subscription_id' => $subscription->id,
				]
			]);

		}

        $session = Session::create([
            'payment_method_types' => $types,
            'line_items' => [
                [
                    'price_data' => [
                        'currency' => Str::lower(data_get($paymentPayload?->payload, 'currency', $currency)),
                        'product_data' => [
                            'name' => 'Payment'
                        ],
                        'unit_amount' => $totalPrice,
                    ],
                    'quantity' => 1,
                ]
            ],
            'mode' => 'payment',
            'success_url' => $url,
            'cancel_url'  => $url,
        ]);

        return PaymentProcess::updateOrCreate([
            'user_id'    => auth('sanctum')->id(),
			'model_id'   => !empty($userSubscription) ? $userSubscription->id : $subscription->id,
			'model_type' => !empty($userSubscription) ? get_class($userSubscription) : get_class($subscription)
        ], [
            'id' => $session->payment_intent ?? $session->id,
            'data' => [
                'price'           => $totalPrice,
                'shop_id'         => $shop?->id,
				'url'      		  => $session->url,
				'subscription_id' => $subscription->id
			]
        ]);
    }

	public function cancelSubscription($id) {

		$userSubscription = UserSubscription::find($id);

		try {
			\Stripe\Subscription::cancel($userSubscription->data['id'], []);
		} catch (ApiErrorException $e) {
			dd($e->getMessage());
		}
	}
}
