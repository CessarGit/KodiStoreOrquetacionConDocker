# Instrucciones para ejecutar el ecosistema KodiStore

Ecosistema de producción con Docker: **frontend Next.js 16** (KodiStore), **API Laravel 13** (Nginx + PHP-FPM 8.4, JWT y Stripe) y **MySQL 8.0**. Se levanta con un solo comando.

| Servicio   | Qué es                             | Imagen base          | Puerto interno | URL en tu máquina                     |
|------------|------------------------------------|----------------------|----------------|---------------------------------------|
| `frontend` | Tienda Next.js                     | `node:20-alpine`     | 3000           | http://localhost:3000                 |
| `backend`  | Nginx (puerta de entrada a la API) | `nginx:1.27-alpine`  | 80             | http://localhost:8000/api             |
| `laravel`  | PHP-FPM con la API                 | `php:8.4-fpm-alpine` | 9000           | sin puerto público (solo red interna) |
| `db`       | MySQL                              | `mysql:8.0`          | 3306           | sin puerto público (solo red interna) |

El cumplimiento de cada requisito de la rúbrica, con el comando para comprobarlo, está en el [paso 7](#7-comprobar-los-requisitos-de-la-rúbrica).

---

## Repositorio

Repositorio en GitHub: **https://github.com/CessarGit/KodiStoreOrquetacionConDocker.git**

| Archivo | Dónde está |
|------------|------------|
| Dockerfiles de cada servicio | Frontend: [frontend/Dockerfile](frontend/Dockerfile). API: [backend/Dockerfile](backend/Dockerfile), con una etapa para PHP-FPM (`production`) y otra para Nginx (`nginx`). MySQL usa la imagen oficial `mysql:8.0` y no necesita Dockerfile propio. |
| Orquestación | [docker-compose.yml](docker-compose.yml) |
| Variables de entorno documentadas | [.env.example](.env.example) |
| Código fuente del frontend Next.js | [frontend/KodiStore/](frontend/KodiStore/) |
| Código fuente de la API Laravel | [backend/e-commerce/](backend/e-commerce/) |
| Documentación | [README.md](README.md) e INSTRUCCIONES.md (este archivo) |


---

## 1. Requisitos

- **Git**, para clonar el repositorio.
- **Docker Engine 23 o superior** con **Docker Compose v2.17 o superior**.
  ```bash
  docker --version
  docker compose version
  ```

## 2. Clonar el repositorio y crear el archivo `.env`

Clona el repositorio y entra en su carpeta raíz. Todos los comandos de esta guía se ejecutan desde ahí:

```bash
git clone https://github.com/CessarGit/KodiStoreOrquetacionConDocker.git
cd KodiStoreOrquetacionConDocker
```

El archivo `.env` guarda los secretos. **No está en el repositorio** y nunca entra en las imágenes. Créalo a partir de `.env.example`:

```bash
cp .env.example .env

# Genera las claves y contraseñas automáticamente
sed -i "s|^APP_KEY=.*|APP_KEY=base64:$(openssl rand -base64 32)|" .env
sed -i "s|^JWT_SECRET=.*|JWT_SECRET=$(openssl rand -base64 48 | tr -d '\n')|" .env
sed -i "s|^MYSQL_ROOT_PASSWORD=.*|MYSQL_ROOT_PASSWORD=$(openssl rand -hex 16)|" .env
sed -i "s|^MYSQL_PASSWORD=.*|MYSQL_PASSWORD=$(openssl rand -hex 16)|" .env
```

Las versiones de las imágenes base se definen en `.env` y ya vienen con los valores correctos:

```env
NODE_VERSION=20-alpine
PHP_VERSION=8.4
MYSQL_VERSION=8.0
```

> Debido a que utilizo el proyecto desarrollado en los módulos anteriores, se trabaja con PHP 8.4

MySQL se configura con estas cuatro variables, todas obligatorias:

| Variable              | Uso                                          |
|-----------------------|----------------------------------------------|
| `MYSQL_ROOT_PASSWORD` | Contraseña de root (generada arriba)         |
| `MYSQL_DATABASE`      | Base de datos de la aplicación               |
| `MYSQL_USER`          | Usuario con el que se conecta Laravel        |
| `MYSQL_PASSWORD`      | Contraseña de ese usuario (generada arriba)  |

> **macOS:** `sed -i` necesita un argumento vacío: usa `sed -i ''` en lugar de `sed -i`.
> **Windows:** ejecuta los comandos en Git Bash o WSL, o edita `.env` a mano con valores propios.

**Pagos con Stripe (opcional):** para probar pagos, abre `.env` y pega tus claves **de prueba** (`pk_test_...` y `sk_test_...`):

```env
STRIPE_KEY=pk_test_...
STRIPE_SECRET=sk_test_...
```

Se debe tomar en cuenta que , `laravel` necesita salida a Internet para llamar a Stripe, y la red `backend_net` la bloquea. Para probar pagos, quita `internal: true` de `backend_net` en `docker-compose.yml` y recrea la red con `docker compose down && docker compose up -d`. MySQL sigue sin publicar su puerto.

Sin las claves y ese cambio, todo el ecosistema funciona salvo el procesamiento de pagos.

Si falta `APP_KEY`, `JWT_SECRET` o alguna variable de MySQL, `docker compose` se detiene antes de arrancar y dice cuál falta.

## 3. (Opcional) Cambiar los puertos

Si el puerto `8000` está ocupado, por ejemplo por otro proyecto, cambia **las tres líneas** en `.env` para usar otro, como `8080`:

```env
API_PORT=8080
NEXT_PUBLIC_API_BASE_URL=http://localhost:8080/api
APP_URL=http://localhost:8080
```

Esto solo cambia el puerto del host: dentro del contenedor, Nginx sigue en el `80`.

Para el frontend, cambia `FRONTEND_PORT` y `FRONTEND_URL` (este último se usa para CORS).

> `NEXT_PUBLIC_API_BASE_URL` se graba dentro del JavaScript del frontend al construir la imagen. Si la cambias después del primer build, reconstruye con `docker compose up -d --build frontend`.

## 4. Levantar el ecosistema

```bash
docker compose up -d --build
```

El primer build tarda unos minutos. Los siguientes son mucho más rápidos gracias a la caché de Docker.

Los servicios arrancan en orden y cada uno espera a que el anterior esté sano: `db` → `laravel` (crea las tablas con las migraciones) → `backend` → `frontend`.

Comprueba el estado:

```bash
docker compose ps
```

Espera a que los **4 servicios** muestren `Up ... (healthy)`. Suele tardar entre 30 y 60 segundos tras el build.

## 5. Usar la aplicación

La base de datos empieza **vacía**. Para ver la tienda con datos:

1. Abre http://localhost:3000 y **regístrate** con un usuario nuevo.
2. Entra a **Productos** y crea un producto desde el formulario.
3. El producto aparece en la lista.

La documentación interactiva de la API (Swagger) está en http://localhost:8080/api/documentation. #(El puerto varia si se los cambias)

También puedes probar la API desde la terminal:

```bash
API=http://localhost:8000/api   # (ajusta el puerto si lo cambiaste)

# Registro de usuario
curl -s -H "Accept: application/json" -H "Content-Type: application/json" \
  -X POST $API/auth/register \
  -d '{"name":"Prueba","email":"prueba@test.local","password":"Password123!","password_confirmation":"Password123!"}'

# Login: guarda el token JWT
TOKEN=$(curl -s -H "Accept: application/json" -H "Content-Type: application/json" \
  -X POST $API/auth/login \
  -d '{"email":"prueba@test.local","password":"Password123!"}' \
  | python3 -c "import sys,json;print(json.load(sys.stdin)['authorization']['token'])")

# Crear un producto (requiere token)
curl -s -H "Accept: application/json" -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" -X POST $API/products \
  -d '{"name":"Teclado Mecánico RGB","description":"Switch Red, USB-C","price":89.99,"stock":45,"sku":"KB-RGB-001"}'

# Listar productos (público)
curl -s -H "Accept: application/json" $API/products
```
