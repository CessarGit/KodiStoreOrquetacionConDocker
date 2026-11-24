<?php

namespace App\Http\Controllers;
use OpenApi\Attributes as OA;

#[OA\Info(
    version: "1.0.0",
    title: "E-commerce REST API",
    description: "API para la gestión de clientes, productos, órdenes y pagos."
)]
#[OA\Server(
    url: "http://127.0.0.1:8001/api",
    description: "Servidor local de desarrollo"
)]
#[OA\Get(
    path: "/ping",
    summary: "Verificar estado del servidor",
    tags: ["Health Check"],
    responses: [
        new OA\Response(
            response: 200,
            description: "Servidor activo"
        )
    ]
)]
#[OA\SecurityScheme(
    securityScheme: "bearerAuth", 
    type: "http",
    name: "Authorization",
    in: "header",
    scheme: "bearer",
    bearerFormat: "JWT"
)]
abstract class Controller
{
    //
}
