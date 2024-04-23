<?php

namespace App\Http\Requests\Subscription;

use App\Http\Requests\BaseRequest;

class UpdateRequest extends BaseRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
           'type'                  => 'string',
           'price'                 => 'required|numeric',
           'tax'	               => 'required|numeric|max:100|min:0',
           'month'                 => 'required|integer|min:1|max:12',
           'active'                => 'required|boolean',
           'with_report'           => 'boolean',
           'title'                 => 'required|string',
           'product_limit'         => 'integer|min:1',
           'order_limit'           => 'integer|min:1',
           'business_delivery_fee' => 'numeric|min:1',
           'customer_delivery_fee' => 'numeric|min:1',
        ];
    }
}
