<?php

namespace App\Http\Controllers\API;

use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class OrderController extends Controller
{

    public function index()
    {
        $orders = Order::where('user_id', Auth::id())
            ->with(['items' => function($query){
                $query->select(
                        "id","order_id","product_id","quantity","unit_price","price","created_at",
                )->with(['product' => function($query){
                    $query->select(
                        "id","name","detail","price","type","brand","measure","image","status",
                    );
                }]);
            }])
            ->orderBy('id','desc')
            ->paginate(20);

        return success_res(200, 'User Order Details', $orders);
    }

    public function allOrders()
    {
        $orders = Order::select("id","user_id","order_number","status","discount","grand_total","created_at")
                                    ->with(['items' => function($query){
                                 $query->select(
                                    "id","order_id","product_id","quantity","unit_price","price","created_at",
                                     )->with(['product' => function($query){
                                $query->select(
                                    "id","name","detail","price","type","brand","measure","image","status",
                                );
                            }]);
                        }])->orderBy('id','desc')->paginate(20);
        return success_res(200, 'All Order Details', $orders);
    }

    public function allUsers()
    {
        $users = User::select("id","name","email","emp_id","d_o_j","location","status")->paginate(20);
        return success_res(200, 'All Users Details', $users);
    }

    public function show($id)
    {
        $order = Order::where('user_id', Auth::id())
            ->with('items.product')
            ->findOrFail($id);
        return success_res(200, 'Order Details', $order);
    }

    public function showOrderToAdmin($id)
    {
        $order = Order::select("id","user_id","order_number","status","discount","grand_total","created_at","deleted_at")
                ->with(['items' => function($query){
            $query->select(
                    "id","order_id","product_id","quantity","unit_price","price","created_at",
            )->with(['product' => function($query){
                $query->select(
                    "id","name","detail","price","type","brand","measure","image","status",
                );
            }]);
        }])
            ->findOrFail($id);

        return success_res(200, 'Order Details', $order);
    }

    public function placeOrder()
    {
        DB::beginTransaction();
        try {
            $cart = Cart::with('items.product')->where('user_id', Auth::id())->first();

            if (!$cart || $cart->items->isEmpty()) {
                return error_res(403, 'Cart is empty');
            }

            $max_order_amount = (float) \App\Models\Option::getValueByKey('max_order_amount');
            $grand_total = round($cart->items->sum('total'), 2);
            if ($max_order_amount && $grand_total > $max_order_amount) {
                return error_res(403, "Your total order amount {$grand_total} exceeds the maximum allowed order amount of {$max_order_amount}");
            }
            $discount = ($grand_total >= 20000)? round(10000, 3): round($grand_total / 2, 3);
            $discount = min(round($discount, 3), round($grand_total, 3));
            $final_total = round($grand_total - $discount, 2);

            $order = Order::create([
                'user_id' => Auth::id(),
                'order_number' => 'ORD-' . strtoupper(uniqid()),
                'status' => 'pending',
                'grand_total' => $grand_total,
                'discount' => $discount,
                'final_total' => $final_total
            ]);

            foreach ($cart->items as $cartItem) {
                if ($max_order_amount && $cartItem->total > $max_order_amount) {
                    return success_res(403,"The total amount for product {$cartItem->product->name} exceeds the maximum allowed order amount of {$max_order_amount}");
                }
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $cartItem->product_id,
                    'quantity' => $cartItem->quantity,
                    'unit_price' => $cartItem->unit_price,
                    'price' => $cartItem->total,
                ]);
            }

            // Clear the cart after placing an order
            $cart->items()->delete();
            DB::commit();
            return success_res(200, 'Order placed successfully', $order);
        } catch (\Exception $e) {
            DB::rollBack();
            return error_res(403, $e->getMessage());
        }
    }

    public function cancelOrder($id)
    {
        $order = Order::where('user_id', Auth::id())->where('id', $id)->firstOrFail();
        $order->update(['status' => 'cancelled']);
        return success_res(200, 'Order cancelled successfully');
    }
}
