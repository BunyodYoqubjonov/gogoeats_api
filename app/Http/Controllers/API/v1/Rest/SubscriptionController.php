<?php

namespace App\Http\Controllers\API\v1\Rest;

use App\Http\Requests\FilterParamsRequest;
use App\Http\Resources\SubscriptionResource;
use App\Models\Subscription;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SubscriptionController extends RestBaseController
{
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
            ->paginate($request->input('perPage', 15));

        return SubscriptionResource::collection($subscriptions);
    }
}
