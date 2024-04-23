<?php

namespace App\Http\Controllers\API\v1\Dashboard\User;

use App\Helpers\ResponseError;
use App\Http\Requests\FilterParamsRequest;
use App\Http\Resources\SubscriptionResource;
use App\Http\Resources\UserSubscriptionResource;
use App\Models\Subscription;
use App\Models\User;
use App\Models\UserSubscription;
use App\Services\SubscriptionService\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SubscriptionController extends UserBaseController
{
    private SubscriptionService $service;

    /**
     * @param SubscriptionService $service
     */
    public function __construct(SubscriptionService $service)
    {
        parent::__construct();
        $this->service = $service;
    }

    /**
     * Display a listing of the resource.
     *
     * @param FilterParamsRequest $request
     * @return AnonymousResourceCollection
     */
    public function index(FilterParamsRequest $request): AnonymousResourceCollection
    {
        $subscriptions = Subscription::where('type',Subscription::TYPE_DELIVERY)
            ->where('active', 1)
            ->paginate($request->paginate ?? 15);

        return SubscriptionResource::collection($subscriptions);
    }

    /**
     * Display a listing of the resource.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function mySubscription(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = auth('sanctum')->user();

        $subscriptions = UserSubscription::actualSubscription()
			->with(['subscription', 'transaction'])
            ->where('type',Subscription::TYPE_DELIVERY)
            ->where('user_id', $user->id)
			->orderBy($request->input('column', 'id'), $request->input('sort', 'desc'))
			->paginate($request->input('perPage', 10));

        return $this->successResponse(
            __('errors.' . ResponseError::SUCCESS, locale: $this->language),
            UserSubscriptionResource::collection($subscriptions)
        );
    }

    public function subscriptionAttach(int $id): JsonResponse
    {
        /** @var User $user */
        $user = auth('sanctum')->user();

        $subscription = Subscription::find($id);

        if (empty($subscription)) {
            return $this->onErrorResponse([
                'code'      => ResponseError::ERROR_404,
                'message'   => __('errors.' . ResponseError::ERROR_404, locale: $this->language)
            ]);
        }

        return $this->successResponse(
            __('errors.' . ResponseError::SUCCESS, locale: $this->language),
            $this->service->userSubscriptionAttach($subscription, $user->id)
        );
    }
}
