# E-Commerce RESTful API 

Una API RESTful robusta y escalable construida con **Laravel** para gestionar el backend de una plataforma de comercio electrónico. Incluye autenticación segura con JWT, gestión de usuarios, procesamiento de órdenes y pagos integrados con **Stripe**, además de documentación interactiva generada con **Swagger**.

## Características Principales

*   **Autenticación y Seguridad:** Implementación de JSON Web Tokens (JWT) para registro, login, refresco de tokens y cierre de sesión.
*   **Gestión de Usuarios:** Endpoints CRUD protegidos para la administración de clientes.
*   **Gestión de Órdenes:** Creación de órdenes de compra ligadas a los usuarios autenticados.
*   **Procesamiento de Pagos:** Integración con la API de Stripe (PaymentIntents) para procesar cobros de forma segura.
*   **Autorización:** Uso de *Laravel Gates* y *Policies* para asegurar que los usuarios solo puedan acceder a sus propias órdenes y pagos.
*   **Documentación Interactiva:** Especificación OpenAPI (Swagger) integrada utilizando atributos nativos de PHP 8.

## Tecnologías y Herramientas

*   **Framework:** Laravel (PHP 8+)
*   **Autenticación:** `php-open-source-saver/jwt-auth` (o `tymon/jwt-auth`)
*   **Pagos:** Stripe PHP SDK
*   **Documentación API:** L5-Swagger (DarkaOnLine)
*   **Base de Datos:** MySQL / PostgreSQL / Oracle (Compatible vía Eloquent ORM)

## ⚙️ Requisitos Previos

Asegúrate de tener instalado lo siguiente en tu entorno local:
*   [PHP](https://www.php.net/) >= 8.1
*   [Composer](https://getcomposer.org/)
*   Servidor de Base de Datos (MySQL, MariaDB, etc.)
*   Cuenta de [Stripe](https://stripe.com/) (para obtener las claves de prueba)

## 💻 Instalación y Configuración Local

Sigue estos pasos para levantar el proyecto en tu entorno de desarrollo:

1. **Clonar el repositorio:**
   ```bash
   git clone https://github.com/CessarGit/APIecommerce.git

2. **Instalar dependencias:**

```bash
   composer install

3. **Configurar las variables de entorno:**
```bash
    cp .env.example .env
    php artisan key:generate

4. **Generar la clave secreta para JWT:**
```bash
    php artisan jwt:secret

5. **Configurar la base de datos y Stripe:**
```bash
    DB_CONNECTION=mysql
    DB_HOST=127.0.0.1
    DB_PORT=3306
    DB_DATABASE=nombre_de_tu_bd
    DB_USERNAME=tu_usuario
    DB_PASSWORD=tu_contraseña

    # Stripe Keys
    STRIPE_KEY=pk_test_tu_clave_publica_aqui
    STRIPE_SECRET=sk_test_tu_clave_secreta_aqui

6. **Ejecutar migraciones:**
```bash
    php artisan migrate --seed

7. **Generar la documentación de Swagger:**
```bash
    php artisan l5-swagger:generate

8. **Iniciar el servidor de desarrollo:**
```bash
    php artisan serve

**Documentación de la API (Swagger):**
```bash
    http://localhost:8000/api/documentation