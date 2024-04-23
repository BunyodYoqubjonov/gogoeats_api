<?php

namespace App\Repositories\OrderRepository\DeliveryMan;

use App\Models\Order;
use App\Models\User;
use App\Repositories\CoreRepository;
use Illuminate\Support\Facades\DB;

class OrderReportRepository extends CoreRepository
{
    /**
     * @return string
     */
    protected function getModelClass(): string
    {
        return Order::class;
    }

    /**
     * @param array $filter
     * @return array
     */
    public function report(array $filter = []): array
    {
        $type       = data_get($filter, 'type', 'day');
        $dateFrom   = date('Y-m-d 00:00:01', strtotime(request('date_from')));
        $dateTo     = date('Y-m-d 23:59:59', strtotime(request('date_to', now())));
        $now        = now()?->format('Y-m-d 00:00:01');
        $user       = User::withAvg('assignReviews', 'rating')
            ->with(['wallet'])
            ->find(data_get($filter, 'deliveryman'));

		/** @var Order $lastOrder */
		$lastOrder = Order::where('deliveryman', data_get($filter, 'deliveryman'))
            ->where('delivered_at', '>=', $dateFrom)
            ->where('delivered_at', '<=', $dateTo)
            ->latest('id')
            ->first();

        $orders = DB::table('orders')
            ->where('deliveryman', data_get($filter, 'deliveryman'))
            ->where('delivered_at', '>=', $dateFrom)
            ->where('delivered_at', '<=', $dateTo)
            ->select([
                DB::raw("sum(if(status = 'delivered', delivery_fee, 0)) + sum(if(status = 'delivered', business_delivery_fee, 0)) + sum(if(business_delivery_fee <= 0, small_order_fee, 0)) as delivery_fee"),
                DB::raw('count(id) as total_count'),
                DB::raw("sum(if(delivered_at >= '$now', 1, 0)) as total_today_count"),
                DB::raw("sum(if(status = 'new', 1, 0)) as total_new_count"),
                DB::raw("sum(if(status = 'ready', 1, 0)) as total_ready_count"),
                DB::raw("sum(if(status = 'on_a_way', 1, 0)) as total_on_a_way_count"),
                DB::raw("sum(if(status = 'accepted', 1, 0)) as total_accepted_count"),
                DB::raw("sum(if(status = 'canceled', 1, 0)) as total_canceled_count"),
                DB::raw("sum(if(status = 'delivered', 1, 0)) as total_delivered_count"),
            ])
            ->first();

        $type = match ($type) {
            'year' => '%Y',
            'week' => '%w',
            'month' => '%Y-%m',
            default => '%Y-%m-%d',
        };

        $chart = DB::table('orders')
            ->where('deliveryman', data_get($filter, 'deliveryman'))
            ->where('delivered_at', '>=', $dateFrom)
            ->where('delivered_at', '<=', $dateTo)
            ->where('status', Order::STATUS_DELIVERED)
            ->select([
                DB::raw("(DATE_FORMAT(delivered_at, '$type')) as time"),
                DB::raw('sum(delivery_fee) + sum(business_delivery_fee) + sum(if(business_delivery_fee <= 0, small_order_fee, 0)) as total_price'),
            ])
            ->groupBy('time')
            ->orderBy('time')
            ->get();

		$deliveryFee = $lastOrder?->delivery_fee + $lastOrder?->business_delivery_fee;

		if ($lastOrder?->business_delivery_fee <= 0) {
			$deliveryFee += $lastOrder?->small_order_fee;
		}

        return [
            'last_order_total_price' => (double)($lastOrder?->total_price ?? 0),
            'last_order_income'      => (double)$deliveryFee ?? 0,
            'total_price'            => (double)data_get($orders, 'delivery_fee', 0),
            'avg_rating'             => (double)$user->assign_reviews_avg_rating ?? 0,
            'wallet_price'           => (double)$user->wallet?->price ?? 0,
            'wallet_currency'        => $user->wallet?->currency ?? 0,
            'total_count'            => (int)data_get($orders, 'total_count', 0),
            'total_today_count'      => (int)data_get($orders, 'total_today_count', 0),
            'total_new_count'        => (int)data_get($orders, 'total_new_count', 0),
            'total_ready_count'      => (int)data_get($orders, 'total_ready_count', 0),
            'total_on_a_way_count'   => (int)data_get($orders, 'total_on_a_way_count', 0),
            'total_accepted_count'   => (int)data_get($orders, 'total_accepted_count', 0),
            'total_canceled_count'   => (int)data_get($orders, 'total_canceled_count', 0),
            'total_delivered_count'  => (int)data_get($orders, 'total_delivered_count', 0),
            'chart'                  => $chart
        ];
    }

}
