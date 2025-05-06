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
        try {
            $userId = Auth::id();
            $orders = Order::where('user_id', $userId)
                ->with(['items:id,order_id,product_id,quantity,unit_price,price,created_at', 'items.product:id,name,detail,price,type,brand,measure,image,status'])
                ->orderByDesc('id')
                ->paginate(20);
            return success_res(200, 'User Order Details', $orders);
        } catch (\Throwable $e) {
            return error_res(403, 'Something went wrong', $e->getMessage());
        }
    }
    /**
     * This function is used to edit Order
     * of present month ,if the order found ,
     * as per last_order_date in Options
     */

    public function editLastOrder()
    {
        try {
            $order = $this->checkOrderAlreadyPlaced('edit');
            if ($order instanceof \Illuminate\Http\JsonResponse) {
                return $order;
            }
            if (!$order) {
                return error_res(403, 'No previous order found to edit.');
            }
            $cart = Cart::where('user_id', Auth::id())->latest()->first();
            if ($cart) {
                $order_item_product_ids = $order->items()->pluck('product_id')->toArray();
                $cart->items()->onlyTrashed()->whereIn('product_id', $order_item_product_ids)->restore();
                $order->items()->delete();
                $order->delete();
                return success_res(200, 'Order is editable', $order);
            } else {
                return error_res(403, 'No Cart Items found', []);
            }
        } catch (\Exception $e) {
            return error_res(403, 'Failed to edit the order. Please try again.', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * This function is used to to display all
     * orders to admin and also added filter
     */
    public function allOrders(Request $request)
    {
        try {
            $query = Order::query()
                ->select([
                    "id",
                    "user_id",
                    "order_number",
                    "status",
                    "discount",
                    "grand_total",
                    "created_at"
                ])
                ->with(['user' => function ($query) {
                    $query->select("id", "emp_id", "name");
                }])
                ->with(['items' => function ($query) {
                    $query->select([
                        "id",
                        "order_id",
                        "product_id",
                        "quantity",
                        "unit_price",
                        "price",
                        "created_at"
                    ])
                        ->with(['product' => function ($query) {
                            $query->select([
                                "id",
                                "name",
                                "detail",
                                "price",
                                "type",
                                "brand",
                                "measure",
                                "image",
                                "status"
                            ]);
                        }]);
                }]);
            if ($request->filled('emp_id')) {
                $empIds = is_array($request->emp_id)
                    ? $request->emp_id
                    : array_filter(explode(',', $request->emp_id));
                if (!empty($empIds)) {
                    $query->whereHas('user', function ($q) use ($empIds) {
                        $q->whereIn('emp_id', $empIds);
                    });
                }
            }
            if ($request->filled('order_number')) {
                $orderNumber = $request->order_number;
                $query->where('order_number', 'like', "%{$orderNumber}%");
            }
            if ($request->filled('start_date')) {
                $query->whereDate('created_at', '>=', $request->start_date);
            }
            if ($request->filled('end_date')) {
                $query->whereDate('created_at', '<=', $request->end_date);
            }
            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }
            $query->latest('id');
            $perPage = min($request->per_page ?? 20, 100); // Limit max per page to 100
            $orders = $query->paginate($perPage);
            return success_res(
                status_code: 200,
                message: $orders->isEmpty() ? 'No orders found' : 'Orders retrieved successfully',
                data: $orders
            );
        } catch (\Exception $e) {
            return error_res(
                status_code: 403,
                message: 'Failed to fetch orders: ' . $e->getMessage(),
                data: []
            );
        }
    }

    public function allUsers(Request $request)
    {
        try {
            $query = User::select("id", "name", "email", "emp_id", "d_o_j", "location", "status");

            $has_empid_filter = $request->has('emp_id') && !empty($request->input('emp_id'));
            $has_name_filter = $request->has('name') && !empty($request->input('name'));

            if ($has_empid_filter || $has_name_filter) {
                $query->where(function($q) use ($request, $has_empid_filter, $has_name_filter) {
                    if ($has_empid_filter) {
                        $emp_ids = $request->input('emp_id');
                        $q->whereIn('emp_id', $emp_ids);
                    }
                    if ($has_name_filter) {
                        $names = $request->input('name');
                        foreach ($names as $name) {
                            $q->orWhere('name', 'like', '%' . $name . '%');
                        }
                    }
                });
            }

            $users = $query->paginate(20);
            return success_res(200, 'All Users Details', $users);
        } catch (\Exception $e) {
            return error_res(403, 'Something went wrong', $e->getMessage());
        }
    }

    public function show($id)
    {
        $order = Order::where('user_id', Auth::id())
            ->with('items.product')
            ->findOrFail($id);
        return success_res(200, 'Order Details', $order);
    }

    /**
     * This function is used to
     * tell that order is already been placed or not
     * of the current month
     */
    public function checkOrderAlreadyPlaced($purpose = 'edit')
    {
        $current_date = now();
        $employee_contribution = $company_discount = 0;
        $last_order_date = \App\Models\Option::getValueByKey('last_order_date');
        $cart = Cart::where('user_id', Auth::id())->latest()->first() ?? Cart::create(['user_id' => Auth::id()]);
        $original_cart_items = \App\Models\CartItem::where('cart_id', $cart->id)
            ->select('id', 'cart_id', 'product_id', 'quantity', 'unit_price', 'total')
            ->with('product:id,image,measure')
            ->get();
        $original_payable = round($original_cart_items->sum('total'), 2);
        if (!$last_order_date || $current_date->gt(\Carbon\Carbon::parse($last_order_date))) {
            $get_cart_summary = calculateAmountSummary($original_payable);
            $get_cart_summary = $get_cart_summary->getData(true);
            $employee_contribution =  $get_cart_summary['data']['employee_contribution'];
            $company_discount =  $get_cart_summary['data']['discount'];
            return error_res(403, 'Order editing is not allowed at this time, as last date was ' . $last_order_date, [
                'cart_data' => $original_cart_items,
                'payable_amount' => $original_payable,
                'employee_contribution' => $employee_contribution,
                'company_discount' => $company_discount
            ]);
        }

        $order = Order::where('user_id', Auth::id())
            ->whereYear('created_at', $current_date->year)
            ->whereMonth('created_at', $current_date->month)
            ->with('items.product')
            ->latest()
            ->first();

        if ($order) {
            if ($purpose === 'create') {
                return error_res(403, 'Order has already been placed for this month.');
            }
            return $order;
        } else {
            if ($purpose === 'edit') {
                return error_res(403, 'Order of current month not found.');
            }
            return error_res(200, 'You can place Order.');
        }
    }

    public function showOrderToAdmin($id)
    {
        $order = Order::select("id", "user_id", "order_number", "status", "discount", "grand_total", "created_at", "deleted_at")
            ->with('user:id,emp_id')
            ->with(['items' => function ($query) {
                $query->select(
                    "id",
                    "order_id",
                    "product_id",
                    "quantity",
                    "unit_price",
                    "price",
                    "created_at",
                )->with(['product' => function ($query) {
                    $query->select(
                        "id",
                        "name",
                        "detail",
                        "price",
                        "type",
                        "brand",
                        "measure",
                        "image",
                        "status",
                    );
                }]);
            }])
            ->findOrFail($id);

        return success_res(200, 'Order Details', $order);
    }

    /**
     * This function is used to place
     * the order for customer
     */
    public function placeOrder()
    {
        DB::beginTransaction();
        try {
            $check = $this->checkOrderAlreadyPlaced('create');
            if ($check instanceof \Illuminate\Http\JsonResponse && $check->getStatusCode() !== 200) {
                return $check;
            }
            $cart = Cart::with('items.product')->where('user_id', Auth::id())->first();

            if (!$cart || $cart->items->isEmpty()) {
                return error_res(403, 'Cart is empty');
            }
            $max_order_amount = (float) \App\Models\Option::getValueByKey('max_order_amount');
            $grand_total = round($cart->items->sum('total'), 2);
            if ($max_order_amount && $grand_total > $max_order_amount) {
                return error_res(403, "Your total order amount {$grand_total} exceeds the maximum allowed order amount of {$max_order_amount}");
            }
            $discount = ($grand_total >= 20000) ? round(10000, 3) : round($grand_total / 2, 3);
            $discount = min(round($discount, 3), round($grand_total, 3));
            $final_total = round($grand_total - $discount, 2);

            $order = Order::create([
                'user_id' => Auth::id(),
                'order_number' => 'ORD-' . strtoupper(uniqid()),
                'status' => 'pending',
                'is_editable' => true,
                'grand_total' => $grand_total,
                'discount' => $discount,
                'final_total' => $final_total
            ]);

            foreach ($cart->items as $cartItem) {
                if ($max_order_amount && $cartItem->total > $max_order_amount) {
                    return success_res(403, "The total amount for product {$cartItem->product->name} exceeds the maximum allowed order amount of {$max_order_amount}");
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
