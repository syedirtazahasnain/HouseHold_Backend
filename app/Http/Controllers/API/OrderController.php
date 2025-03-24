<?php

namespace App\Http\Controllers\API;

use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

class OrderController extends Controller
{

    public function index()
    {
        $orders = Order::where('user_id', Auth::id())->with('items.product')->get();
        return success_res(200, 'User Order Details',$orders );
    }

    public function placeOrder()
    {
        $cart = Cart::with('items.product')->where('user_id', Auth::id())->first();

        if (!$cart || $cart->items->isEmpty()) {
            return error_res(403,'Cart is empty' );
        }

        $order = Order::create([
            'user_id' => Auth::id(),
            'order_number' => 'ORD-' . strtoupper(uniqid()),
            'status' => 'pending',
            'grand_total' => round($cart->items->sum('total'),2)
        ]);

        foreach ($cart->items as $cartItem) {
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
        return success_res(200,'Order placed successfully',$order);
    }

    public function cancelOrder($id)
    {
        $order = Order::where('user_id', Auth::id())->where('id', $id)->firstOrFail();
        $order->update(['status' => 'cancelled']);
        return success_res(200,'Order cancelled successfully');
    }
}
