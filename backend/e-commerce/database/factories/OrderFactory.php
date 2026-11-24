<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'order_number' => 'ORD-' . strtoupper(Str::random(10)),
            'status' => fake()->randomElement(['pending', 'processing', 'completed', 'cancelled']),
            'total_amount' => 0.00, // Se calcula dinámicamente en el seeder según los ítems
            'shipping_address' => fake()->address(),
        ];
    }
}
