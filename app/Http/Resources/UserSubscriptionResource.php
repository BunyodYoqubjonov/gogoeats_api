<?php

namespace App\Http\Resources;

use App\Models\UserSubscription;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserSubscriptionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     * @return array
     */
    public function toArray($request): array
	{
        /** @var UserSubscription|JsonResource $this */
        return [
            'id'                => $this->when($this->id, $this->id),
            'user_id'           => $this->when($this->user_id, $this->user_id),
            'subscription_id'   => $this->when($this->subscription_id, $this->subscription_id),
            'expired_at'        => $this->when($this->expired_at, $this->expired_at),
            'price'             => $this->when($this->price, $this->price),
            'type'              => $this->when($this->type, $this->type),
            'active'            => $this->when($this->active, $this->active),
            'created_at'        => $this->when($this->created_at, $this->created_at?->format('Y-m-d H:i:s') . 'Z'),
            'updated_at'        => $this->when($this->updated_at, $this->updated_at?->format('Y-m-d H:i:s') . 'Z'),
            'deleted_at'        => $this->when($this->deleted_at, $this->deleted_at?->format('Y-m-d H:i:s') . 'Z'),
            'subscription'      => SubscriptionResource::make($this->whenLoaded('subscription')),
            'transaction'       => TransactionResource::make($this->whenLoaded('transaction')),
            'user'              => UserResource::make($this->whenLoaded('user')),
        ];
    }
}
