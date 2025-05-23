<?php

namespace App\Services;

use Carbon\Carbon;
use \App\Models\Order;
use \App\Models\OrderItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;


class OrderUpdateService
{

    public function updateOrderPricing(Order $order)
    {
        DB::beginTransaction();

        try {
            $order->load('items.product');
            $grand_total = 0;
            foreach ($order->items as $item) {
                $product = $item->product;
                if (!$product) {
                    continue;
                }
                $new_unit_price = $product->price;
                $new_price = $new_unit_price * $item->quantity;
                $item->update([
                    'unit_price' => $new_unit_price,
                    'price' => $new_price
                ]);

                $grand_total += $new_price;
            }

            $discount = ($grand_total >= 20000) ? 10000 : round($grand_total / 2, 2);
            $discount = min($discount, $grand_total);
            $order->update([
                'grand_total' => $grand_total,
                'discount' => $discount,
            ]);
            DB::commit();
            return $order;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function bulkUpdateOrders(Carbon $start_date, Carbon $end_date): Collection
    {
        DB::beginTransaction();
        try {
            $orders = Order::whereBetween('created_at', [$start_date, $end_date])
                ->with('items.product')
                ->get();

            foreach ($orders as $order) {
                $this->updateOrderPricing($order);
            }
            DB::commit();
            return $orders;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
