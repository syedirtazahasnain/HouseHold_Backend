<?php

if (!function_exists('success_res')) {
    function success_res( $status_code = 200 , $message = 'Success' , $data = [])
    {
        return response()->json([
            'success' => true,
            'status_code' => $status_code,
            'message' => $message,
            'data' => $data
        ], $status_code);
    }
}

if (!function_exists('error_res')) {
    function error_res($status_code = 400 , $message = 'Error', $errors = [])
    {
        return response()->json([
            'success' => false,
            'status_code' => $status_code,
            'message' => $message,
            'errors' => $errors
        ], $status_code);
    }
}

/**
 * The function returns true , grand_total and discount in
 * same array indexes
 */
function calculateCartSummary($cart, $original_cart_items= null) {
    if($original_cart_items == null){
        $original_cart_items = \App\Models\CartItem::where('cart_id', $cart->id)
                ->select('id', 'cart_id', 'product_id', 'quantity', 'unit_price', 'total')
                ->with('product:id,image,measure')
                ->get();
    }
    $original_payable = round($original_cart_items->sum('total'), 2);
    $max_order_amount = (float) \App\Models\Option::getValueByKey('max_order_amount');
    $grand_total = round($cart->items->sum('total'), 2);

    // if ($max_order_amount && $grand_total > $max_order_amount) {
    //     return error_res(403, "Your total order amount {$grand_total} exceeds the maximum allowed order amount of {$max_order_amount}",[
    //         'cart_data' => $original_cart_items,
    //         'payable_amount' => $original_payable
    //     ]);
    // }
    $discount = ($grand_total >= 20000) ? round(10000, 3) : round($grand_total / 2, 3);
    $discount = min(round($discount, 3), round($grand_total, 3));
    return success_res(200,'Summary Data' ,[
        'grand_total' => $grand_total,
        'discount' => $discount,
        'employee_contribution' => $grand_total - $discount
    ]);
}


/**
 * The function returns true , grand_total and discount in
 * same array indexes
 */
function calculateAmountSummary($grand_total) {
    $discount = ($grand_total >= 20000) ? round(10000, 3) : round($grand_total / 2, 3);
    $discount = min(round($discount, 3), round($grand_total, 3));
    return success_res(200,'Summary Data' ,[
        'grand_total' => $grand_total,
        'discount' => $discount,
        'employee_contribution' => $grand_total - $discount
    ]);
}


