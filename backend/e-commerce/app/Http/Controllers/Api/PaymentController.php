<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePaymentRequest;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Stripe\Exception\ApiErrorException;
use Stripe\PaymentIntent;
use Stripe\Stripe;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class PaymentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    #[OA\Post(
        path: '/api/payments',
        summary: 'Procesar el pago de una orden con Stripe',
        description: 'Crea y confirma un PaymentIntent en Stripe usando el monto total de la orden. Si el pago es exitoso, actualiza el estado de la orden a "paid".',
        security: [['bearerAuth' => []]],
        tags: ['Pagos'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['order_id', 'payment_method_id'],
                properties: [
                    new OA\Property(property: 'order_id', type: 'integer', example: 1, description: 'ID de la orden de compra que se va a pagar'),
                    new OA\Property(property: 'payment_method_id', type: 'string', example: 'pm_card_visa', description: 'ID del método de pago generado desde el frontend con Stripe.js (ej. pm_1Hh...')
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Pago procesado exitosamente',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'string', example: 'success'),
                        new OA\Property(property: 'message', type: 'string', example: 'Pago procesado exitosamente con Stripe'),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'payment_id', type: 'integer', example: 5),
                                new OA\Property(property: 'stripe_payment_intent', type: 'string', example: 'pi_3MtwBwLkdIwHu7ix28a3tq...'),
                                new OA\Property(property: 'status', type: 'string', example: 'succeeded'),
                                new OA\Property(property: 'total_paid', type: 'number', format: 'float', example: 1250.50)
                            ]
                        )
                    ]
                )
            ),
            new OA\Response(response: 400, description: 'Error: La orden ya ha sido pagada previamente'),
            new OA\Response(response: 401, description: 'No autorizado (Falta Token JWT)'),
            new OA\Response(response: 403, description: 'Acceso denegado (La orden pertenece a otro usuario)'),
            new OA\Response(response: 404, description: 'Orden no encontrada'),
            new OA\Response(response: 422, description: 'Error al procesar el pago con la API de Stripe o error de validación')
        ]
    )]

    public function store(StorePaymentRequest $request): JsonResponse
    {
        $order = Order::findOrFail($request->order_id);

        // 🔒 Verificar que la orden pertenece al usuario autenticado vía JWT
        Gate::authorize('view', $order);

        if ($order->status === 'paid') {
            return response()->json([
                'status' => 'error',
                'message' => 'Esta orden ya ha sido pagada previamente.'
            ], 400);
        }

        // Configurar la API Key secreta de Stripe
        Stripe::setApiKey(config('services.stripe.secret'));

        try {
            // Crear y confirmar el PaymentIntent en Stripe (el monto se envía en centavos)
            $paymentIntent = PaymentIntent::create([
                'amount' => (int) round($order->total_amount * 100),
                'currency' => 'usd',
                'payment_method' => $request->payment_method_id,
                'confirm' => true,
                'automatic_payment_methods' => [
                    'enabled' => true,
                    'allow_redirects' => 'never',
                ],
                'metadata' => [
                    'order_id' => $order->id,
                    'user_id' => $request->user()->id,
                ],
            ]);

            // Registrar la transacción en la base de datos
            $payment = Payment::create([
                'order_id' => $order->id,
                'stripe_payment_id' => $paymentIntent->id,
                'amount' => $order->total_amount,
                'currency' => 'usd',
                'status' => $paymentIntent->status === 'succeeded' ? 'completed' : 'pending',
                'payment_method' => 'stripe',
            ]);

            // Actualizar el estado de la orden de compra
            if ($paymentIntent->status === 'succeeded') {
                $order->update(['status' => 'paid']);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Pago procesado exitosamente con Stripe',
                'data' => [
                    'payment_id' => $payment->id,
                    'stripe_payment_intent' => $paymentIntent->id,
                    'status' => $paymentIntent->status,
                    'total_paid' => $order->total_amount,
                ],
            ], 201);

        } catch (ApiErrorException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al procesar el pago con Stripe: ' . $e->getMessage(),
            ], 422);
        }
    }

   #[OA\Get(
        path: '/api/payments/{payment}',
        summary: 'Ver el detalle de un pago',
        description: 'Retorna la información de un pago registrado en el sistema. Se valida que el usuario solo pueda ver los pagos asociados a sus propias órdenes.',
        security: [['bearerAuth' => []]],
        tags: ['Pagos'],
        parameters: [
            new OA\Parameter(
                name: 'payment',
                description: 'ID del pago a consultar',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            )
        ],
        responses: [
            new OA\Response(response: 200, description: 'Detalle del pago obtenido con éxito'),
            new OA\Response(response: 401, description: 'No autorizado (Falta Token JWT)'),
            new OA\Response(response: 403, description: 'Acceso denegado (El pago pertenece a otro usuario)'),
            new OA\Response(response: 404, description: 'Pago no encontrado')
        ]
    )]
    public function show(Payment $payment): JsonResponse
    {
        Gate::authorize('view', $payment->order);

        return response()->json([
            'status' => 'success',
            'data' => $payment,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Payment $payment)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Payment $payment)
    {
        //
    }
}
