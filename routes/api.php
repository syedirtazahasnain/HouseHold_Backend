<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\CartController;
use App\Http\Controllers\API\OrderController;
use App\Http\Controllers\API\ProductController;


Route::post('register', [AuthController::class, 'register']);
Route::post('login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {

    Route::post('logout', [AuthController::class, 'logout']);

    // 🛒 User Routes (Only authenticated users can access)
    Route::middleware('role_user:user')->group(function () {
        Route::get('/cart', [CartController::class, 'index']);
        Route::post('/cart/add', [CartController::class, 'addToCart']);
        Route::delete('/cart/remove/{id}', [CartController::class, 'removeFromCart']);
        Route::delete('/cart/clear', [CartController::class, 'clearCart']);

        Route::get('/orders', [OrderController::class, 'index']);
        Route::post('/orders/place', [OrderController::class, 'placeOrder']);
        Route::post('/orders/cancel/{id}', [OrderController::class, 'cancelOrder']);
        Route::get('/products', [ProductController::class, 'index']);
    });

    // 🛠 Admin Routes (Only admins & super admins)
    Route::middleware('admin')->group(function () {
        Route::get('/admin/dashboard', function () {
            return response()->json(['message' => 'Admin Dashboard']);
        });

        Route::get('/orders/all', [OrderController::class, 'allOrders']);
    });

    // 👑 Super Admin Routes (Only super admins)
    Route::middleware('super_admin')->group(function () {
        Route::get('/superadmin/dashboard', function () {
            return response()->json(['message' => 'Super Admin Dashboard']);
        });

        Route::delete('/orders/delete/{id}', [OrderController::class, 'deleteOrder']);
    });
});
