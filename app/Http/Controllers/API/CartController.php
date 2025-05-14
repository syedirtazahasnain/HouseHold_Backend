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
            ->latest()
            ->first();
        $payable_amount = $cart ? round($cart->items->sum('total'), 2) : 0;
        $get_cart_summary = calculateAmountSummary($payable_amount);
        $get_cart_summary = $get_cart_summary->getData(true);
        $employee_contribution =  $get_cart_summary['data']['employee_contribution'];
        $company_discount =  $get_cart_summary['data']['discount'];
        return success_res(
            200,'Products added to cart',
            [
                'cart_data' => $cart,
                'payable_amount' => round($payable_amount, 2),
                'employee_contribution' => $employee_contribution,
                'company_discount' => $company_discount,
            ]
        );
    }

    public function addToCart(Request $request)
    {
        $request->validate([
            'products' => 'required|array',
            'products.*.product_id' => 'required|exists:products,id',
            'products.*.quantity' => 'required|integer|min:1'
        ]);
        $employee_contribution = $company_discount = 0;

        DB::beginTransaction();
        try {
            $callOrderController = new OrderController();
            $check = $callOrderController->checkOrderAlreadyPlaced('create');
            if ($check instanceof \Illuminate\Http\JsonResponse && $check->getStatusCode() !== 200) {
                return $check;
            }
            $max_order_amount = \App\Models\Option::getValueByKey('max_order_amount');
            $cart = Cart::where('user_id', Auth::id())->latest()->first() ?? Cart::create(['user_id' => Auth::id()]);
            $original_cart_items = CartItem::where('cart_id', $cart->id)
                ->select('id', 'cart_id', 'product_id', 'quantity', 'unit_price', 'total')
                ->with('product:id,image,measure')
                ->orderBy('id', 'desc')
                ->get();
            $original_payable = round($original_cart_items->sum('total'), 2);
            $get_cart_summary = calculateAmountSummary($original_payable);
            $get_cart_summary = $get_cart_summary->getData(true);
            $employee_contribution =  $get_cart_summary['data']['employee_contribution'];
            $company_discount =  $get_cart_summary['data']['discount'];
            if ($max_order_amount && $original_payable > $max_order_amount) {
                DB::rollBack();
                $get_cart_summary = calculateAmountSummary($original_payable);
                $get_cart_summary = $get_cart_summary->getData(true);
                $employee_contribution =  $get_cart_summary['data']['employee_contribution'];
                $company_discount =  $get_cart_summary['data']['discount'];
                return error_res(403, "Your current cart amount exceeds the maximum allowed order amount of {$max_order_amount}", [
                    'cart_data' => $original_cart_items,
                    'payable_amount' => $original_payable,
                    'employee_contribution' => $employee_contribution,
                    'company_discount' => $company_discount,
                ]);
            }

            foreach ($request->products as $product_data) {
                $product = Product::find($product_data['product_id']);
                $quantity = $product_data['quantity'];

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
                    $current_items = CartItem::where('cart_id', $cart->id)->orderBy('id', 'desc')->get();
                    $get_total_payable = round($current_items->sum('total'), 2);
                    $get_cart_summary = calculateAmountSummary($get_total_payable);
                    $get_cart_summary = $get_cart_summary->getData(true);
                    $employee_contribution =  $get_cart_summary['data']['employee_contribution'];
                    $company_discount =  $get_cart_summary['data']['discount'];
                    return error_res(403, "Product {$product->name} exceeds maximum order amount", [
                        'cart_data' => $current_items,
                        'payable_amount' => round($current_items->sum('total'), 2),
                        'employee_contribution' => $employee_contribution,
                        'company_discount' => $company_discount,
                    ]);
                }
            }

            $final_items = CartItem::where('cart_id', $cart->id)->orderBy('id', 'desc')->get();
            $final_amount = round($final_items->sum('total'), 2);
            $get_cart_summary = calculateAmountSummary($final_amount);
            $get_cart_summary = $get_cart_summary->getData(true);
            $employee_contribution =  $get_cart_summary['data']['employee_contribution'];
            $company_discount =  $get_cart_summary['data']['discount'];

            if ($max_order_amount && $final_amount > $max_order_amount) {
                DB::rollBack();
                $current_items = CartItem::where('cart_id', $cart->id)->orderBy('id', 'desc')->get();
                $get_total = round($current_items->sum('total'), 2);
                $get_cart_summary = calculateAmountSummary($get_total);
                $get_cart_summary = $get_cart_summary->getData(true);
                $employee_contribution =  $get_cart_summary['data']['employee_contribution'];
                $company_discount =  $get_cart_summary['data']['discount'];
                return error_res(403, "Total exceeds maximum order amount", [
                    'cart_data' => $current_items,
                    'payable_amount' => round($current_items->sum('total'), 2),
                    'employee_contribution' => $employee_contribution,
                    'company_discount' => $company_discount
                ]);
            }
            DB::commit();
            return success_res(200, 'Products added to cart', [
                'cart_data' => $final_items,
                'payable_amount' => $final_amount,
                'employee_contribution' => $employee_contribution,
                'company_discount' => $company_discount,
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
            $cart = Cart::with('items.product')->where('user_id', Auth::id())->latest()->first();

            $payable_amount = $cart ? round($cart->items->sum('total'), 2) : 0;
            $get_cart_summary = calculateAmountSummary($payable_amount);
            $get_cart_summary = $get_cart_summary->getData(true);
            $employee_contribution =  $get_cart_summary['data']['employee_contribution'];
            $company_discount =  $get_cart_summary['data']['discount'];

            return success_res(200, 'Item removed from cart', [
                'cart_data' => $cart,
                'payable_amount' => $payable_amount,
                'employee_contribution' => $employee_contribution,
                'company_discount' => $company_discount
            ]);
        } catch (\Exception $e) {
            $cart = Cart::with('items.product')->where('user_id', Auth::id())->first();
            $payable_amount = $cart ? round($cart->items->sum('total'), 2) : 0;
            $get_cart_summary = calculateAmountSummary($payable_amount);
            $get_cart_summary = $get_cart_summary->getData(true);
            $employee_contribution =  $get_cart_summary['data']['employee_contribution'];
            $company_discount =  $get_cart_summary['data']['discount'];
            return error_res(403, 'Failed to remove item from cart', [
                'cart_data' => $cart,
                'payable_amount' => $payable_amount,
                'employee_contribution' => $employee_contribution,
                'company_discount' => $company_discount
            ]);
        }
    }


    public function clearCart()
    {
        $cart = Cart::where('user_id', Auth::id())->first();
        if ($cart) {
            $currentDate = now();
            $order = \App\Models\Order::where('user_id', Auth::id())
                ->whereYear('created_at', $currentDate->year)
                ->whereMonth('created_at', $currentDate->month)
                ->where('status', 'pending')
                ->with('items.product')
                ->latest()
                ->first();
            if ($order) {
                $order->items()->delete();
                $order->delete();
                $cart->items()->delete();
                $cart->delete();
                return success_res(200, 'Order and Cart cleared Successfully', []);
            } else {
                $cart->items()->delete();
                $cart->delete();
                return success_res(200, 'Cart cleared Successfully', []);
            }
        }
        return success_res(200, 'Cart cleared', []);
    }
}
