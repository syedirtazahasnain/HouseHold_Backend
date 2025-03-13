<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\CartItem;
use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Cart;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CartItem>
 */
class CartItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    protected $model = CartItem::class;

    public function definition()
    {
        $product = Product::inRandomOrder()->first();
        $quantity = rand(1, 5);

        return [
            'cart_id' => Cart::inRandomOrder()->first()->id,
            'product_id' => $product->id,
            'quantity' => $quantity,
            'unit_price' => $product->price,
            'total' => $quantity * $product->price,
        ];
    }
}
