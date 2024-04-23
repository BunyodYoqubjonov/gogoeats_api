<?php

namespace App\Services\SubscriptionService;

use App\Helpers\ResponseError;
use App\Models\Currency;
use App\Models\Payment;
use App\Models\PaymentPayload;
use App\Models\ShopSubscription;
use App\Models\Subscription;
use App\Models\UserSubscription;
use App\Services\CoreService;
use DB;
use Illuminate\Database\Eloquent\Model;
use Stripe\Price;
use Stripe\Stripe;
use Throwable;

class SubscriptionService extends CoreService
{
    protected function getModelClass(): string
    {
        return Subscription::class;
    }

    /**
     * @param array $data
     * @return array
     */
    public function create(array $data): array
    {
        try {

//			$payment = Payment::where('tag', Payment::TAG_STRIPE)->first();
//
//			$paymentPayload = PaymentPayload::where('payment_id', $payment?->id)->first();
//
//			Stripe::setApiKey(data_get($paymentPayload?->payload, 'stripe_sk'));
//
//			if (isset($data['month']) && isset($data['title']) && isset($data['price'])) {
//
//				$price = Price::create([
//					'currency' 		=> Currency::where('default', 1)->first(['id', 'title'])?->title,
//					'unit_amount' 	=> $data['price'] * 100,
//					'recurring' 	=> ['interval' => 'month', 'interval_count' => $data['month'], 'trial_period_days' => 60],
//					'product_data' 	=> ['name' => $data['title']],
//				]);
//
//				$data['data'] = $price->toArray();
//			}

			$subscription = $this->model()->create($data);

			return ['status' => true, 'code' => ResponseError::NO_ERROR, 'data' => $subscription];

        } catch (Throwable $e) {
            $this->error($e);
            return [
                'status'  => false,
                'code'    => ResponseError::ERROR_501,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * @param Subscription $subscription
     * @param array $data
     * @return array
     */
    public function update(Subscription $subscription, array $data): array
    {
        try {

//			$payment = Payment::where('tag', Payment::TAG_STRIPE)->first();
//
//			$paymentPayload = PaymentPayload::where('payment_id', $payment?->id)->first();
//
//			Stripe::setApiKey(data_get($paymentPayload?->payload, 'stripe_sk'));
//
//			if (isset($data['month']) && isset($data['title']) && isset($data['price'])) {
//
//				if (!isset($subscription->data['id'])) {
//					$price = Price::create([
//						'currency' 		=> Currency::where('default', 1)->first(['id', 'title'])?->title,
//						'unit_amount' 	=> $data['price'] * 100,
//						'recurring' 	=> ['interval' => 'month', 'interval_count' => $data['month'], 'trial_period_days' => 60],
//						'product_data' 	=> ['name' => $data['title']],
//					]);
//				} else {
//					$price = Price::update($subscription->data['id'], [
//						'currency' 		=> Currency::where('default', 1)->first(['id', 'title'])?->title,
//						'unit_amount' 	=> $data['price'] * 100,
//						'recurring' 	=> ['interval' => 'month', 'interval_count' => $data['month'], 'trial_period_days' => 60],
//						'product_data' 	=> ['name' => $data['title']],
//					]);
//				}
//
//				$data['data'] = $price->toArray();
//			}

            $subscription->update($data);

            return ['status' => true, 'code' => ResponseError::NO_ERROR, 'data' => $subscription];

        } catch (Throwable $e) {
            $this->error($e);
            return [
                'status'  => false,
                'code'    => ResponseError::ERROR_502,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * @param Subscription $subscription
     * @param int $shopId
     * @param int $active
     * @return Model|ShopSubscription
     */
    public function subscriptionAttach(Subscription $subscription, int $shopId, int $active = 0): Model|ShopSubscription
    {
		try {
			DB::table('shop_subscriptions')
				->where('shop_id', $shopId)
				->whereDate('expired_at', '<=', now())
				->delete();
		} catch (Throwable) {}

        return ShopSubscription::create([
            'shop_id'           => $shopId,
            'subscription_id'   => $subscription->id,
            'expired_at'        => now()->addMonths($subscription->month),
            'price'             => $subscription->price,
            'type'              => data_get($subscription, 'type', 'order'),
            'active'            => $active,
        ]);
    }

	/**
	 * @param Subscription $subscription
	 * @param int $userId
	 * @param int $active
	 * @return Model|UserSubscription
	 */
    public function userSubscriptionAttach(Subscription $subscription, int $userId, int $active = 0): Model|UserSubscription
    {
        try {
           	UserSubscription::where('user_id', $userId)
                ->whereDate('expired_at', '<=', now())
                ->delete();
        } catch (Throwable) {}

        return UserSubscription::updateOrCreate([
            'user_id'           => $userId,
        ],[
            'user_id'           => $userId,
            'subscription_id'   => $subscription->id,
            'expired_at'        => now()->addMonths($subscription->month),
            'price'             => $subscription->price,
            'type'              => data_get($subscription, 'type', 'order'),
            'active'            => $active,
        ]);
    }

}
