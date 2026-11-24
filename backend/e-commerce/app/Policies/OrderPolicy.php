<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class OrderPolicy
{
    /**
     * Determinar si el usuario autenticado puede ver el detalle de la orden.
     */
    public function view(User $user, Order $order): Response
    {
        // 🔒 Validamos que el ID del usuario coincida con el user_id asignado a la orden de compra
        return $user->id === $order->user_id
            ? Response::allow()
            : Response::deny('Acceso denegado: No eres el propietario de esta orden de compra.');
    }
}
