<?php

namespace Database\Factories;

use App\Models\Payment;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'stripe_payment_intent_id' => 'pi_' . Str::random(24),
            'stripe_charge_id' => 'ch_' . Str::random(24),
            'amount' => fake()->randomFloat(2, 20, 1000),
            'currency' => 'usd',
            'status' => fake()->randomElement(['succeeded', 'pending', 'failed']),
            'payment_method' => 'card',
            'payload' => [
                'seller_message' => 'Payment complete.',
                'outcome' => ['network_status' => 'approved_by_network'],
            ],
        ];
    }
}
