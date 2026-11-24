<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Crear usuarios y productos base
        $users = User::factory(10)->create();
        $products = Product::factory(25)->create();

        // 2. Crear órdenes con ítems y pagos coherentes
        $users->each(function ($user) use ($products) {
            // Crear entre 1 y 3 órdenes por usuario
            Order::factory(rand(1, 3))->create([
                'user_id' => $user->id,
            ])->each(function ($order) use ($products) {
                // Seleccionar entre 1 y 4 productos aleatorios para la orden
                $selectedProducts = $products->random(rand(1, 4));
                $totalAmount = 0;

                foreach ($selectedProducts as $product) {
                    $quantity = rand(1, 3);
                    $subtotal = round($quantity * $product->price, 2);
                    $totalAmount += $subtotal;

                    OrderItem::create([
                        'order_id' => $order->id,
                        'product_id' => $product->id,
                        'quantity' => $quantity,
                        'unit_price' => $product->price,
                        'subtotal' => $subtotal,
                    ]);
                }

                // Actualizar el monto total real de la orden
                $order->update(['total_amount' => $totalAmount]);

                // Crear registro de pago si la orden no está cancelada
                if ($order->status !== 'cancelled') {
                    Payment::factory()->create([
                        'order_id' => $order->id,
                        'amount' => $totalAmount,
                        'status' => $order->status === 'completed' ? 'succeeded' : 'pending',
                    ]);
                }
            });
        });
    }
}
