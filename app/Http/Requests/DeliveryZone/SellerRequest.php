<?php

namespace App\Http\Requests\DeliveryZone;

use App\Http\Requests\BaseRequest;

class SellerRequest extends BaseRequest
{

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
			'id'       				=> 'numeric',
			'address'       	    => 'array|required',
			'address.*'     	    => 'array|required',
			'address.*.*'   	    => 'numeric|required',
			'min_order_price'	    => 'numeric|min:0',
			'delivery_price' 	    => 'numeric|min:0',
			'business_delivery_fee' => 'numeric|min:0',
			'customer_delivery_fee' => 'numeric|min:0',
			'title'   			    => 'string|max:255',
        ];
    }

}
