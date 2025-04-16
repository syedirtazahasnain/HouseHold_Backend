<?php

namespace App\Http\Controllers\API;

use App\Models\Cart;
use App\Models\Product;
use App\Models\CartItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

class CartController extends Controller
{
    public function index()
    {
        $cart = Cart::select('id', 'user_id')
                ->with(['items' => function($query) {
                    $query->select('id','cart_id','product_id','quantity','unit_price','total'
                        )->with(['product' => function($query) {
                        $query->select(
                            'id',
                            'name',
                            'price',
                            'measure',
                            'image',
                            'status'
                        );
                    }]);
                }])
                ->where('user_id', Auth::id())
                ->first();
        $payable_amount = $cart ? round($cart->items->sum('total'), 2) : 0;
        return success_res(200, 'Products added to cart', ['cart_data' => $cart, 'payable_amount' => round($payable_amount, 2)]);
    }

    public function addToCart(Request $request)
    {
        $request->validate([
            'products' => 'required|array',
            'products.*.product_id' => 'required|exists:products,id',
            'products.*.quantity' => 'required|integer|min:1'
        ]);

        $max_order_amount = \App\Models\Option::getValueByKey('max_order_amount');
        $cart = Cart::firstOrCreate(['user_id' => Auth::id()]);
        $payable_amount = 0;

        foreach ($request->products as $productData) {
            $product = Product::find($productData['product_id']);
            $quantity = $productData['quantity'];

            // Fetch or create the cart item
            $cart_item_model = CartItem::firstOrNew([
                'cart_id' => $cart->id,
                'product_id' => $product->id
            ]);

            if ($cart_item_model->exists) {
                $cart_item_model->quantity = $quantity;
            } else {
                $cart_item_model->quantity = $quantity;
                $cart_item_model->unit_price = $product->price;
            }

            $cart_item_model->total = round($cart_item_model->quantity * $product->price, 2);
            if ($max_order_amount && $cart_item_model->total > $max_order_amount) {
                return error_res(403, "The total amount for product {$product->name} exceeds the maximum allowed order amount of {$max_order_amount}");
            }
            $cart_item_model->save();
        }

        $cart_items = CartItem::where('cart_id', $cart->id)->select('id','cart_id','product_id','quantity','unit_price','total')->with('product:id,image,measure')->get();
        $payable_amount = round($cart_items->sum('total'), 2);
        if ($max_order_amount && $payable_amount > $max_order_amount) {
            return error_res(403, "Your total cart amount exceeds the maximum allowed order amount of {$max_order_amount}");
        }

        return success_res(200, 'Products added to cart', [
            'cart_data' => $cart_items,
            'payable_amount' => $payable_amount
        ]);
    }



    public function removeFromCart($id)
    {
        $cart_item = CartItem::findOrFail($id);
        $cart_item->delete();
        $cart = Cart::with('items.product')->where('user_id', Auth::id())->first();
        $payable_amount = $cart ? round($cart->items->sum('total'), 2) : 0;

        return success_res(200, 'Item removed from cart', [
            'cart_data' => $cart,
            'payable_amount' => $payable_amount
        ]);
    }

    public function clearCart()
    {
        $cart = Cart::where('user_id', Auth::id())->first();
        if ($cart) {
            $cart->items()->delete();
        }
        return success_res(200, 'Cart cleared', []);
    }
}
