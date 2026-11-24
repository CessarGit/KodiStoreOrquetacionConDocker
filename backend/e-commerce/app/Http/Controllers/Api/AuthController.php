<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;


class AuthController extends Controller
{
    #[OA\Post(
        path: '/api/auth/register',
        summary: 'Registrar un nuevo cliente',
        description: 'Crea una nueva cuenta de cliente en el e-commerce y devuelve un token JWT para iniciar sesión automáticamente.',
        tags: ['Autenticación'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'email', 'password'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'María Gómez'),
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'maria.gomez@example.com'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: 'PasswordFuerte123!'),
                    new OA\Property(property: 'phone', type: 'string', example: '+503 7000-0000', description: 'Número de teléfono (Opcional)')
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Cliente registrado exitosamente',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'string', example: 'success'),
                        new OA\Property(property: 'message', type: 'string', example: 'Cliente registrado con éxito en el e-commerce'),
                        new OA\Property(
                            property: 'user',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 1),
                                new OA\Property(property: 'name', type: 'string', example: 'María Gómez'),
                                new OA\Property(property: 'email', type: 'string', example: 'maria.gomez@example.com'),
                                new OA\Property(property: 'phone', type: 'string', example: '+503 7000-0000', nullable: true),
                            ]
                        ),
                        new OA\Property(
                            property: 'authorization',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'token', type: 'string', description: 'Token JWT generado', example: 'eyJ0eXAiOiJKV1QiLCJhbGc...'),
                                new OA\Property(property: 'type', type: 'string', example: 'bearer')
                            ]
                        )
                    ]
                )
            ),
            new OA\Response(response: 422, description: 'Error de validación de datos (ej. el correo ya existe o la contraseña es muy corta)')
        ]
    )]
    public function register(RegisterRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'phone' => $validated['phone'] ?? null,
        ]);

        // Iniciamos sesión y emitimos el JWT inmediatamente
        $token = Auth::login($user);

        return response()->json([
            'status' => 'success',
            'message' => 'Cliente registrado con éxito en el e-commerce',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
            ],
            'authorization' => [
                'token' => $token,
                'type' => 'bearer',
            ]
        ], 201);
    }

    #[OA\Post(
        path: '/api/auth/login',
        summary: 'Iniciar sesión',
        description: 'Autentica al usuario con su correo y contraseña, validando las credenciales y devolviendo un token JWT.',
        tags: ['Autenticación'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'password'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'maria.gomez@example.com'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: 'PasswordFuerte123!')
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Inicio de sesión exitoso',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'string', example: 'success'),
                        new OA\Property(property: 'message', type: 'string', example: 'Inicio de sesión exitoso'),
                        new OA\Property(
                            property: 'user',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 1),
                                new OA\Property(property: 'name', type: 'string', example: 'María Gómez'),
                                new OA\Property(property: 'email', type: 'string', example: 'maria.gomez@example.com')
                            ]
                        ),
                        new OA\Property(
                            property: 'authorization',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'token', type: 'string', description: 'Token JWT generado', example: 'eyJ0eXAiOiJKV1QiLCJhbGc...'),
                                new OA\Property(property: 'type', type: 'string', example: 'bearer')
                            ]
                        )
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                description: 'Credenciales inválidas',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'string', example: 'error'),
                        new OA\Property(property: 'message', type: 'string', example: 'Acceso denegado: Credenciales inválidas')
                    ]
                )
            ),
            new OA\Response(response: 422, description: 'Error de validación (correo o contraseña omitidos)')
        ]
    )]
    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->only('email', 'password');

        // Intentamos autenticar con el guard configurado para la API (JWT)
        $token = Auth::attempt($credentials);

        if (!$token) {
            return response()->json([
                'status' => 'error',
                'message' => 'Acceso denegado: Credenciales inválidas'
            ], 401);
        }

        $user = Auth::user();

        return response()->json([
            'status' => 'success',
            'message' => 'Inicio de sesión exitoso',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'authorization' => [
                'token' => $token,
                'type' => 'bearer',
            ]
        ], 200);
    }

    #[OA\Get(
        path: '/api/auth/profile',
        summary: 'Ver el perfil del usuario autenticado',
        description: 'Retorna los datos del usuario correspondiente al token JWT suministrado en la petición.',
        security: [['bearerAuth' => []]],
        tags: ['Autenticación'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Datos del perfil',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'string', example: 'success'),
                        new OA\Property(property: 'user', type: 'object', description: 'Objeto con la información del usuario autenticado')
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'No autorizado (Token inválido, expirado o ausente)')
        ]
    )]
    public function profile(): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'user' => Auth::user(),
        ]);
    }

    #[OA\Post(
        path: '/api/auth/logout',
        summary: 'Cerrar sesión',
        description: 'Invalida el token JWT actual de forma permanente (añadiéndolo a la lista negra), por lo que ya no podrá usarse para futuras peticiones.',
        security: [['bearerAuth' => []]],
        tags: ['Autenticación'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Sesión cerrada exitosamente',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'string', example: 'success'),
                        new OA\Property(property: 'message', type: 'string', example: 'Sesión cerrada y token invalidado exitosamente')
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'No autorizado (El token ya es inválido o no se envió)')
        ]
    )]
    public function logout(): JsonResponse
    {
        Auth::logout();

        return response()->json([
            'status' => 'success',
            'message' => 'Sesión cerrada y token invalidado exitosamente'
        ], 200);
    }

    #[OA\Post(
        path: '/api/auth/refresh',
        summary: 'Refrescar el token JWT',
        description: 'Genera y devuelve un nuevo token JWT válido. Invalida el token anterior. Ideal para mantener la sesión activa sin pedir usuario y contraseña nuevamente.',
        security: [['bearerAuth' => []]],
        tags: ['Autenticación'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Token refrescado exitosamente',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'string', example: 'success'),
                        new OA\Property(property: 'user', type: 'object', description: 'Información del usuario'),
                        new OA\Property(
                            property: 'authorization',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'token', type: 'string', description: 'Nuevo token JWT generado', example: 'eyJ0eXAiOiJKV1QiLCJhbGc...'),
                                new OA\Property(property: 'type', type: 'string', example: 'bearer')
                            ]
                        )
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'No autorizado (El token provisto ya expiró de forma irreversible o es inválido)')
        ]
    )]
    public function refresh(): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'user' => Auth::user(),
            'authorization' => [
                'token' => Auth::refresh(),
                'type' => 'bearer',
            ]
        ], 200);
    }
}
