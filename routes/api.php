<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\CartController;
use App\Http\Controllers\API\GeneralController;
use App\Http\Controllers\API\OrderController;
use App\Http\Controllers\API\ProductController;
use App\Http\Controllers\API\SummaryController;


Route::post('register', [AuthController::class, 'register']);
Route::post('login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {

    Route::post('logout', [AuthController::class, 'logout']);

    // 🛒 User Routes (Only authenticated users can access)
    Route::get('/user-details', [GeneralController::class, 'userDetails']);
    Route::post('/password-update', [GeneralController::class, 'passwordUpdate']);
    Route::middleware('role_user:user')->group(function () {
        Route::get('/cart', [CartController::class, 'index']);
        Route::post('/cart/add', [CartController::class, 'addToCart']);
        Route::delete('/cart/remove/{id}', [CartController::class, 'removeFromCart']);
        Route::delete('/cart/clear', [CartController::class, 'clearCart']);

        Route::get('/orders', [OrderController::class, 'index']);
        Route::get('/orders/{id}', [OrderController::class, 'show']);
        Route::post('/orders/place', [OrderController::class, 'placeOrder']);
        Route::post('/orders/edit-last-order', [OrderController::class, 'editLastOrder']);
        Route::post('/orders/cancel/{id}', [OrderController::class, 'cancelOrder']);
        Route::get('/products', [ProductController::class, 'index']);
    });

    // 🛠 Admin Routes (Only admins & super admins)
    Route::prefix('admin')->middleware('role_admin')->group(function () {
        Route::get('/orders/all', [OrderController::class, 'allOrders']);
        Route::get('/users/all', [OrderController::class, 'allUsers']);
        Route::post('/users-update/{id}', [GeneralController::class, 'usersUpdate']);
        Route::get('/orders/{id}', [OrderController::class, 'showOrderToAdmin']);
        Route::post('/store-products', [ProductController::class, 'store']);
        Route::get('/products', [ProductController::class, 'index']);
        Route::post('/order-date', [GeneralController::class, 'orderDateUpdate']);
        Route::get('/products/{id}', [ProductController::class, 'show']);
        Route::post('/employees/import', [SummaryController::class, 'importEmployees'])->name('employees.import');
        Route::post('/product/import', [SummaryController::class, 'importProduct'])->name('product.import');
        Route::get('/dashboard', function () {
            return response()->json(['message' => 'Admin Dashboard']);
        });
    });

    // 👑 Super Admin Routes (Only super admins)
    Route::middleware('role_super_admin:super_admin')->group(function () {
        Route::get('/superadmin/dashboard', function () {
            return response()->json(['message' => 'Super Admin Dashboard']);
        });

        Route::delete('/orders/delete/{id}', [OrderController::class, 'deleteOrder']);
    });
});
