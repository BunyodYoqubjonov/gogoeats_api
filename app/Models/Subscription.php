<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * App\Models\Subscription
 *
 * @property int $id
 * @property string $type
 * @property float $price
 * @property int $tax
 * @property double $business_delivery_fee
 * @property double $customer_delivery_fee
 * @property int $month
 * @property array $data
 * @property int $active
 * @property string $title
 * @property int $product_limit
 * @property int $order_limit
 * @property boolean $with_report
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @method static Builder|self newModelQuery()
 * @method static Builder|self newQuery()
 * @method static Builder|self onlyTrashed()
 * @method static Builder|self query()
 * @method static Builder|self filter(array $filter)
 * @method static Builder|self whereActive($value)
 * @method static Builder|self whereCreatedAt($value)
 * @method static Builder|self whereDeletedAt($value)
 * @method static Builder|self whereId($value)
 * @method static Builder|self whereMonth($value)
 * @method static Builder|self wherePrice($value)
 * @method static Builder|self whereType($value)
 * @method static Builder|self whereUpdatedAt($value)
 * @method static Builder|self withTrashed()
 * @method static Builder|self withoutTrashed()
 * @mixin Eloquent
 */
class Subscription extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = ['id'];
	protected $casts = [
		'data' => 'array'
	];

    const TTL = 2592000; // 30 days
    const TYPE_DELIVERY = 'delivery';
    const TYPE_ORDER = 'orders';
    const TYPE_SHOP = 'shop';

    public function scopeFilter($query, $filter)
    {
        $query->when(data_get($filter, 'type'), fn($q, $type) => $q->whereIn('type', $type));
    }
}
