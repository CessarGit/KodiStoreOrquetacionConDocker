<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrderRequest;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;
use App\Http\Resources\OrderResource;

class OrderController extends Controller
{
    #[OA\Get(
    path: '/api/orders',
    summary: 'Listar el historial de órdenes',
    description: 'Retorna todas las órdenes de compra pertenecientes al cliente autenticado, ordenadas de la más reciente a la más antigua, incluyendo sus ítems y productos asociados.',
    security: [['bearerAuth' => []]],
    tags: ['Órdenes'],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Lista de órdenes obtenida correctamente',
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(
                        property: 'data',
                        type: 'array',
                        items: new OA\Items(ref: '#/components/schemas/OrderResource')
                    )
                ]
            )
        ),
        new OA\Response(
            response: 401,
            description: 'No autorizado (Falta Token JWT o es inválido)'
        ),
        new OA\Response(
            response: 500,
            description: 'Error interno del servidor'
        )
    ]
)]
public function index(Request $request): JsonResponse
{
    $user = $request->user();

    $orders = $user->orders()
        ->with('items.product')
        ->orderBy('created_at', 'desc')
        ->get();

    return OrderResource::collection($orders)->response()->setStatusCode(200);
}


    #[OA\Post(
        path: '/api/orders',
        summary: 'Crear una nueva orden de compra',
        description: 'Crea una orden, valida el stock disponible y descuenta el inventario de los productos de forma segura (pesimistic locking).',
        security: [['bearerAuth' => []]],
        tags: ['Órdenes'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['shipping_address', 'items'],
                properties: [
                    new OA\Property(property: 'shipping_address', type: 'string', example: 'Colonia Escalón, Calle El Mirador #123, San Salvador'),
                    new OA\Property(
                        property: 'items',
                        type: 'array',
                        description: 'Arreglo de productos a comprar',
                        items: new OA\Items(
                            type: 'object',
                            required: ['product_id', 'quantity'],
                            properties: [
                                new OA\Property(property: 'product_id', type: 'integer', example: 1),
                                new OA\Property(property: 'quantity', type: 'integer', example: 2)
                            ]
                        )
                    )
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Orden de compra creada exitosamente'),
            new OA\Response(response: 401, description: 'No autorizado (Falta Token JWT)'),
            new OA\Response(response: 422, description: 'Error de validación, producto inactivo o stock insuficiente')
        ]
    )]
   public function store(StoreOrderRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        try {
            $order = DB::transaction(function () use ($user, $validated) {
                $totalAmount = 0;
                $itemsToCreate = [];

                foreach ($validated['items'] as $itemData) {
                    // Bloqueo pesimista para evitar inconsistencias de stock en peticiones concurrentes
                    $product = Product::where('id', $itemData['product_id'])
                        ->where('is_active', true)
                        ->lockForUpdate()
                        ->first();

                    if (!$product) {
                        throw new \Exception("El producto ID {$itemData['product_id']} no se encuentra activo o disponible.");
                    }

                    if ($product->stock < $itemData['quantity']) {
                        throw new \Exception("Stock insuficiente para: {$product->name}. Stock disponible: {$product->stock}");
                    }

                    $unitPrice = $product->price;
                    $subtotal = $unitPrice * $itemData['quantity'];
                    $totalAmount += $subtotal;

                    // Descontar inventario
                    $product->decrement('stock', $itemData['quantity']);

                    $itemsToCreate[] = [
                        'product_id' => $product->id,
                        'quantity' => $itemData['quantity'],
                        'unit_price' => $unitPrice,
                        'subtotal' => $subtotal,
                    ];
                }

                // Generar cabecera de la orden
                $order = Order::create([
                    'user_id' => $user->id,
                    'order_number' => 'ORD-' . strtoupper(Str::random(10)),
                    'status' => 'pending',
                    'total_amount' => $totalAmount,
                    'shipping_address' => $validated['shipping_address'],
                ]);

                // Crear los detalles/ítems de la orden
                $order->items()->createMany($itemsToCreate);

                return $order;
            });

            $order->load('items.product');

            return response()->json([
                'status' => 'success',
                'message' => 'Orden de compra creada exitosamente',
                'data' => $order
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 422);
        }
    }

    #[OA\Get(
        path: '/api/orders/{order}',
        summary: 'Ver el detalle de una orden',
        description: 'Retorna la información completa de una orden, incluyendo sus ítems y el historial de pagos asociados. Se valida mediante Policies que el usuario solo pueda ver sus propias órdenes.',
        security: [['bearerAuth' => []]],
        tags: ['Órdenes'],
        parameters: [
            new OA\Parameter(
                name: 'order',
                description: 'ID de la orden de compra',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            )
        ],
        responses: [
            new OA\Response(response: 200, description: 'Detalle de la orden obtenido con éxito'),
            new OA\Response(response: 401, description: 'No autorizado (Falta Token JWT)'),
            new OA\Response(response: 403, description: 'Acceso denegado (Esta orden pertenece a otro usuario)'),
            new OA\Response(response: 404, description: 'Orden no encontrada')
        ]
    )]
    public function show(Order $order): JsonResponse
    {
        // 🔒 El framework evalúa automáticamente al usuario autenticado contra la OrderPolicy
        Gate::authorize('view', $order);

        // Cargar ítems (con el producto asociado) y pagos vinculados a la orden
        $order->load(['items.product', 'payments']);

        return response()->json([
            'status' => 'success',
            'order' => $order
        ], 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Order $order)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Order $order)
    {
        //
    }
}
