<?php

namespace App\Http\Requests\DeliveryZone;

use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

class AdminRequest extends BaseRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
			'id' => 'numeric',
			'shop_id' => [
				'required',
				Rule::exists('shops', 'id')->whereNull('deleted_at')
			],
			'data' 	 				        => 'required|array',
			'data.*' 				        => 'required|array',
			'data.*.address'       	        => 'array|required',
			'data.*.address.*'          	=> 'array|required',
			'data.*.address.*.*'   	        => 'numeric|required',
			'data.*.min_order_price'        => 'numeric|min:0',
			'data.*.delivery_price'         => 'numeric|min:0',
			'data.*.business_delivery_fee'  => 'numeric|min:0',
			'data.*.customer_delivery_fee'  => 'numeric|min:0',
			'data.*.title'   		        => 'string|max:255',
        ];
    }

}
