<?php

namespace App\Http\Controllers\API\v1\Dashboard\Payment;

use App\Helpers\ResponseError;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payment\StripeRequest;
use App\Http\Requests\Shop\SubscriptionRequest;
use App\Models\Currency;
use App\Models\Payment;
use App\Models\PaymentPayload;
use App\Models\PaymentProcess;
use App\Models\Subscription;
use App\Models\Transaction;
use App\Services\PaymentService\StripeService;
use App\Traits\ApiResponse;
use App\Traits\OnResponse;
use Http;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Redirect;
use Throwable;

class StripeController extends Controller
{
	use OnResponse, ApiResponse;

	public function __construct(private StripeService $service)
	{
		parent::__construct();
	}

	/**
	 * process transaction.
	 *
	 * @param StripeRequest $request
	 * @return JsonResponse
	 */
	public function orderProcessTransaction(StripeRequest $request): JsonResponse
	{
		try {
			$result = $this->service->orderProcessTransaction($request->all());

			return $this->successResponse('success', $result);
		} catch (Throwable $e) {
			$this->error($e);
			return $this->onErrorResponse([
				'message' => $e->getMessage(),
			]);
		}
	}

	/**
	 * process transaction.
	 *
	 * @param SubscriptionRequest $request
	 * @return JsonResponse
	 */
	public function subscriptionProcessTransaction(SubscriptionRequest $request): JsonResponse
	{
		$currency = Currency::currenciesList()->where('active', 1)->where('default', 1)->first()?->title;

		if (empty($currency)) {
			return $this->onErrorResponse([
				'code'    => ResponseError::ERROR_404,
				'message' => __('errors.' . ResponseError::CURRENCY_NOT_FOUND)
			]);
		}

		try {
			$result = $this->service->subscriptionProcessTransaction($request->all(), null, $currency);

			return $this->successResponse('success', $result);
		} catch (Throwable $e) {
			$this->error($e);
			return $this->onErrorResponse([
				'code'    => ResponseError::ERROR_501,
				'message' => $e->getMessage() . $e->getFile() . $e->getLine()
			]);
		}
	}

	/**
	 * @param Request $request
	 * @return RedirectResponse
	 */
	public function orderResultTransaction(Request $request): RedirectResponse
	{
		$cartId  = (int)$request->input('cart_id');
		$parcelId = (int)$request->input('parcel_id');

		$to = config('app.front_url') . ($cartId ? '/' : "parcels/$parcelId");

		return Redirect::to($to);
	}

  	/**
  	 * @param Request $request
  	 * @return RedirectResponse
  	*/
    public function subscriptionResultTransaction(Request $request): RedirectResponse
    {
        $subscription = Subscription::find((int)$request->input('subscription_id'));

		$to = config('app.front_url');

        if ($subscription->type == Subscription::TYPE_SHOP) {
			$to = config('app.admin_url') . "seller/subscriptions/$subscription->id";
		}

        return Redirect::to($to);
	}

    /**
     * @param Request $request
     * @return RedirectResponse
     */
    public function userSubscriptionResultTransaction(Request $request): RedirectResponse
    {
        $to = config('app.front_url');

        return Redirect::to($to);
    }

	/**
	 * @param Request $request
	 * @return void
	 */
	/**
	 * @param Request $request
	 * @return void
	 */
	public function paymentWebHook(Request $request): void
	{
		$token = $request->input('data.object.id');

		$payment = Payment::where('tag', 'stripe')->first();

		$paymentPayload = PaymentPayload::where('payment_id', $payment?->id)->first();
		$payload        = $paymentPayload?->payload;

		$paymentProcess = PaymentProcess::where('id', $token)->first();

		if (@$paymentProcess?->data['type'] === 'mobile') {

			$status = match ($request->input('data.object.status')) {
				'succeeded', 'paid'			 => Transaction::STATUS_PAID,
				'payment_failed', 'canceled' => Transaction::STATUS_CANCELED,
				default				  		 => 'progress',
			};

			$this->service->afterHook($token, $status);

			return;
		}

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . data_get($payload, 'stripe_sk')
        ])
            ->get("https://api.stripe.com/v1/checkout/sessions?limit=1&payment_intent=$token")
            ->json();

        $token = data_get($response, 'data.0.id');

        $status = match (data_get($response, 'data.0.payment_status')) {
            'succeeded', 'paid'			 => Transaction::STATUS_PAID,
            'payment_failed', 'canceled' => Transaction::STATUS_CANCELED,
            default				  		 => 'progress',
        };

		$this->service->afterHook($token, $status);
	}

}
