<?php

namespace App\Http\Requests\Payment;

use App\Http\Requests\BaseRequest;
use App\Http\Requests\Order\StoreRequest as OrderStoreRequest;
use Illuminate\Validation\Rule;

class StripeRequest extends BaseRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
		$rules = [];

		if (request('cart_id')) {
			$rules = (new OrderStoreRequest)->rules();
		}

		return [
            'cart_id'  => [
                empty(request('parcel_id')) ? 'required' : 'nullable',
                Rule::exists('carts', 'id')
            ],
            'parcel_id'  => [
                empty(request('cart_id')) ? 'required' : 'nullable',
                Rule::exists('parcel_orders', 'id')
            ],
        ] + $rules;
    }

}
