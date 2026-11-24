<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class UserController extends Controller
{
    #[OA\Get(
        path: '/api/users',
        summary: 'Listar usuarios (clientes)',
        description: 'Obtiene una lista paginada de todos los usuarios registrados en el sistema.',
        security: [['bearerAuth' => []]],
        tags: ['Usuarios'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Lista paginada de usuarios',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(
                                type: 'object',
                                properties: [
                                    new OA\Property(property: 'id', type: 'integer', example: 1),
                                    new OA\Property(property: 'name', type: 'string', example: 'Juan Pérez'),
                                    new OA\Property(property: 'email', type: 'string', example: 'juan.perez@example.com')
                                ]
                            )
                        ),
                        new OA\Property(
                            property: 'links',
                            type: 'object',
                            description: 'Enlaces de paginación de Laravel'
                        ),
                        new OA\Property(
                            property: 'meta',
                            type: 'object',
                            description: 'Metadatos de paginación (total, página actual, etc.)'
                        )
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'No autorizado (Falta Token JWT)')
        ]
    )]
    public function index(): AnonymousResourceCollection
    {
        $users = User::get(); // Puedes agregar paginación si lo deseas, por ejemplo: User::paginate(10);
        return UserResource::collection($users);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    #[OA\Get(
        path: '/api/users/{user}',
        summary: 'Ver el detalle de un usuario',
        description: 'Retorna la información detallada de un usuario específico.',
        security: [['bearerAuth' => []]],
        tags: ['Usuarios'],
        parameters: [
            new OA\Parameter(
                name: 'user',
                description: 'ID del usuario a consultar',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Detalle del usuario obtenido con éxito',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 1),
                                new OA\Property(property: 'name', type: 'string', example: 'Juan Pérez'),
                                new OA\Property(property: 'email', type: 'string', example: 'juan.perez@example.com')
                            ]
                        )
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'No autorizado'),
            new OA\Response(response: 404, description: 'Usuario no encontrado')
        ]
    )]
    public function show(User $user): UserResource
    {
        return new UserResource($user);
    }

    #[OA\Put(
        path: '/api/users/{user}',
        summary: 'Actualizar información de un usuario',
        description: 'Actualiza los datos de un usuario existente. Requiere validación a través de UpdateUserRequest.',
        security: [['bearerAuth' => []]],
        tags: ['Usuarios'],
        parameters: [
            new OA\Parameter(
                name: 'user',
                description: 'ID del usuario a actualizar',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Juan Pérez Actualizado'),
                    new OA\Property(property: 'email', type: 'string', example: 'juan.actualizado@example.com'),
                    new OA\Property(property: 'password', type: 'string', example: 'NuevaPassword123!', description: 'Opcional. Nueva contraseña.')
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Información actualizada exitosamente',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Información del cliente actualizada'),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            description: 'Objeto UserResource con los datos actualizados'
                        )
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'No autorizado'),
            new OA\Response(response: 404, description: 'Usuario no encontrado'),
            new OA\Response(response: 422, description: 'Error de validación de datos (ej. correo ya en uso)')
        ]
    )]
    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $user->update($request->validated());

        return response()->json([
            'message' => 'Información del cliente actualizada',
            'data' => new UserResource($user),
        ]);
    }

    #[OA\Delete(
        path: '/api/users/{user}',
        summary: 'Eliminar o deshabilitar un usuario',
        description: 'Elimina un usuario del sistema (o aplica Soft Delete si el modelo está configurado para ello).',
        security: [['bearerAuth' => []]],
        tags: ['Usuarios'],
        parameters: [
            new OA\Parameter(
                name: 'user',
                description: 'ID del usuario a eliminar',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Usuario eliminado exitosamente',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Cliente deshabilitado correctamente')
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'No autorizado'),
            new OA\Response(response: 403, description: 'Acceso denegado (ej. no puedes eliminar a otro administrador)'),
            new OA\Response(response: 404, description: 'Usuario no encontrado')
        ]
    )]
    public function destroy(User $user): JsonResponse
    {
        $user->delete();

        return response()->json([
            'message' => 'Cliente deshabilitado correctamente',
        ]);
    }
}
