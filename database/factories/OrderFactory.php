<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Order>
 */
class OrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    protected $model = Order::class;

    public function definition(): array
    {
        return [
            'user_id' => User::where('is_admin', 3)->inRandomOrder()->first()->id,
            'order_number' => strtoupper(uniqid('ORD-')),
            'status' => $this->faker->randomElement(['pending', 'completed', 'cancelled']),
            'grand_total' => $this->faker->randomFloat(2, 50, 2000),
        ];
    }
}
