<?php

namespace Database\Seeders;

use App\Models\Cart;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use App\Models\User;
use App\Models\Order;
use App\Models\Product;
use App\Models\CartItem;
use App\Models\OrderItem;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        // User::factory()->create([
        //     'name' => 'Test User',
        //     'email' => 'test@example.com',
        // ]);

        User::create([
            'name' => 'Super Admin',
            'email' => 'superadmin@test.com',
            'is_admin' => 1,
            'email_verified_at' => now(),
            'password' => bcrypt('test@123'),
        ]);

        User::create([
            'name' => 'Admin User',
            'email' => 'admin@test.com',
            'is_admin' => 2,
            'email_verified_at' => now(),
            'password' => bcrypt('test@123'),
        ]);

        User::factory(998)->create();
        Product::factory(500)->create();
        Cart::factory(200)->create();
        // Create 400 Cart Items (2 per cart)
        CartItem::factory(400)->create();
        // Create 300 Orders
        Order::factory(300)->create();
        // Create 500 Order Items
        OrderItem::factory(500)->create();
    }
}
