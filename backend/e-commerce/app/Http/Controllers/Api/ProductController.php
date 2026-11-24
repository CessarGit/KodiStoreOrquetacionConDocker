<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class ProductController extends Controller
{
   #[OA\Get(
        path: '/api/products',
        summary: 'Listar catálogo de productos',
        tags: ['Productos'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Lista de productos obtenida correctamente',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'status', type: 'string', example: 'success'),
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(
                                type: 'object',
                                properties: [
                                    new OA\Property(property: 'id', type: 'integer', example: 1),
                                    new OA\Property(property: 'name', type: 'string', example: 'Laptop Gamer'),
                                    new OA\Property(property: 'price', type: 'number', format: 'float', example: 1250.50),
                                    new OA\Property(property: 'stock', type: 'integer', example: 10)
                                ]
                            )
                        )
                    ]
                )
            )
        ]
    )]
    public function index(): AnonymousResourceCollection
    {
        // Retorna todos los productos ordenados del más reciente al más antiguo
        $products = Product::latest()->get();
        return ProductResource::collection($products);

    }

    #[OA\Post(
        path: '/api/products',
        summary: 'Crear un nuevo producto',
        security: [['bearerAuth' => []]],
        tags: ['Productos'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'price', 'stock'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Teclado Mecánico RGB'),
                    new OA\Property(property: 'description', type: 'string', example: 'Switch Red, conexión USB-C'),
                    new OA\Property(property: 'price', type: 'number', format: 'float', example: 89.99),
                    new OA\Property(property: 'stock', type: 'integer', example: 45),
                    new OA\Property(property: 'sku', type: 'string', example: 'KB-RGB-001')
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Producto creado exitosamente'),
            new OA\Response(response: 401, description: 'No autorizado (Falta Token JWT)'),
            new OA\Response(response: 422, description: 'Error de validación de datos')
        ]
    )]

    public function store(StoreProductRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $validated['slug'] = Str::slug($validated['name']) . '-' . Str::random(5);

        $product = Product::create($validated);

        return response()->json([
            'message' => 'Producto creado exitosamente',
            'data' => new ProductResource($product),
        ], 201);
    }

    #[OA\Get(
        path: '/api/products/{product}',
        summary: 'Ver el detalle de un producto específico',
        tags: ['Productos'],
        parameters: [
            new OA\Parameter(
                name: 'product',
                description: 'ID del producto',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            )
        ],
        responses: [
            new OA\Response(response: 200, description: 'Detalle del producto'),
            new OA\Response(response: 404, description: 'Producto no encontrado')
        ]
    )]

    public function show(Product $product): ProductResource
    {
        return new ProductResource($product);
    }

    #[OA\Put(
        path: '/api/products/{product}',
        summary: 'Actualizar un producto existente',
        security: [['bearerAuth' => []]],
        tags: ['Productos'],
        parameters: [
            new OA\Parameter(
                name: 'product',
                description: 'ID del producto a actualizar',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Teclado Mecánico RGB (Actualizado)'),
                    new OA\Property(property: 'price', type: 'number', format: 'float', example: 79.99),
                    new OA\Property(property: 'stock', type: 'integer', example: 30)
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Producto actualizado exitosamente'),
            new OA\Response(response: 401, description: 'No autorizado'),
            new OA\Response(response: 404, description: 'Producto no encontrado')
        ]
    )]

    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        $validated = $request->validated();

        if (isset($validated['name'])) {
            $validated['slug'] = Str::slug($validated['name']) . '-' . Str::random(5);
        }

        $product->update($validated);

        return response()->json([
            'message' => 'Producto actualizado exitosamente',
            'data' => new ProductResource($product),
        ]);
    }

   #[OA\Delete(
        path: '/api/products/{product}',
        summary: 'Eliminar un producto',
        security: [['bearerAuth' => []]],
        tags: ['Productos'],
        parameters: [
            new OA\Parameter(
                name: 'product',
                description: 'ID del producto a eliminar',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            )
        ],
        responses: [
            new OA\Response(response: 200, description: 'Producto eliminado exitosamente'),
            new OA\Response(response: 401, description: 'No autorizado'),
            new OA\Response(response: 404, description: 'Producto no encontrado')
        ]
    )]

    public function destroy(Product $product): JsonResponse
    {
        $product->delete();

        return response()->json([
            'message' => 'Producto eliminado del catálogo',
        ]);
    }
}
