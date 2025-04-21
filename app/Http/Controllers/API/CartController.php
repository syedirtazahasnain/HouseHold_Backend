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
            ->with(['items' => function ($query) {
                $query->select(
                    'id',
                    'cart_id',
                    'product_id',
                    'quantity',
                    'unit_price',
                    'total'
                )->with(['product' => function ($query) {
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

        DB::beginTransaction();
        try {
            $callOrderController = new OrderController();
            $check = $callOrderController->checkOrderAlreadyPlaced('create');
            if ($check instanceof \Illuminate\Http\JsonResponse && $check->getStatusCode() !== 200) {
                return $check;
            }
            $max_order_amount = \App\Models\Option::getValueByKey('max_order_amount');
            $cart = Cart::firstOrCreate(['user_id' => Auth::id()]);
            $original_cart_items = CartItem::where('cart_id', $cart->id)
                ->select('id', 'cart_id', 'product_id', 'quantity', 'unit_price', 'total')
                ->with('product:id,image,measure')
                ->get();
            $original_payable = round($original_cart_items->sum('total'), 2);

            if ($max_order_amount && $original_payable > $max_order_amount) {
                DB::rollBack();
                return error_res(403, "Your current cart amount exceeds the maximum allowed order amount of {$max_order_amount}", [
                    'cart_data' => $original_cart_items,
                    'payable_amount' => $original_payable
                ]);
            }

            foreach ($request->products as $productData) {
                $product = Product::find($productData['product_id']);
                $quantity = $productData['quantity'];

                $cart_item_model = CartItem::updateOrCreate(
                    ['cart_id' => $cart->id, 'product_id' => $product->id],
                    [
                        'quantity' => $quantity,
                        'unit_price' => $product->price,
                        'total' => round($quantity * $product->price, 2)
                    ]
                );

                if ($max_order_amount && $cart_item_model->total > $max_order_amount) {
                    DB::rollBack();
                    $current_items = CartItem::where('cart_id', $cart->id)->get();
                    return error_res(403, "Product {$product->name} exceeds maximum order amount", [
                        'cart_data' => $current_items,
                        'payable_amount' => round($current_items->sum('total'), 2)
                    ]);
                }
            }

            $final_items = CartItem::where('cart_id', $cart->id)->get();
            $final_amount = round($final_items->sum('total'), 2);

            if ($max_order_amount && $final_amount > $max_order_amount) {
                DB::rollBack();
                $current_items = CartItem::where('cart_id', $cart->id)->get();
                return error_res(403, "Total exceeds maximum order amount", [
                    'cart_data' => $current_items,
                    'payable_amount' => round($current_items->sum('total'), 2)
                ]);
            }
            DB::commit();
            return success_res(200, 'Products added to cart', [
                'cart_data' => $final_items,
                'payable_amount' => $final_amount
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return error_res(403, "An error occurred while updating your cart");
        }
    }



    public function removeFromCart($id)
    {
        try {
            $cart_item = CartItem::findOrFail($id);
            $cart_item->delete();
            $cart = Cart::with('items.product')->where('user_id', Auth::id())->first();
            $payable_amount = $cart ? round($cart->items->sum('total'), 2) : 0;

            return success_res(200, 'Item removed from cart', [
                'cart_data' => $cart,
                'payable_amount' => $payable_amount
            ]);
        } catch (\Exception $e) {
            $cart = Cart::with('items.product')->where('user_id', Auth::id())->first();
            $payable_amount = $cart ? round($cart->items->sum('total'), 2) : 0;

            return error_res(403, 'Failed to remove item from cart', [
                'cart_data' => $cart,
                'payable_amount' => $payable_amount
            ]);
        }
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
