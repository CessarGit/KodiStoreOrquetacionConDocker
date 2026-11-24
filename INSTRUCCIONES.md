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

## Repositorio y contenido de la entrega

Repositorio público en GitHub: **https://github.com/CessarGit/OrquestacionDockerKodiStore.git**

| Entregable | Dónde está |
|------------|------------|
| Dockerfiles de cada servicio | Frontend: [frontend/Dockerfile](frontend/Dockerfile). API: [backend/Dockerfile](backend/Dockerfile), con una etapa para PHP-FPM (`production`) y otra para Nginx (`nginx`). MySQL usa la imagen oficial `mysql:8.0` y no necesita Dockerfile propio. |
| Orquestación | [docker-compose.yml](docker-compose.yml) |
| Variables de entorno documentadas | [.env.example](.env.example) |
| Código fuente del frontend Next.js | [frontend/KodiStore/](frontend/KodiStore/) |
| Código fuente de la API Laravel | [backend/e-commerce/](backend/e-commerce/) |
| Documentación | [README.md](README.md) e INSTRUCCIONES.md (este archivo) |

La documentación cubre:

| Contenido requerido | Dónde leerlo |
|---------------------|--------------|
| Descripción general del proyecto | [README.md, sección 1](README.md#1-objetivo) |
| Diagrama y explicación de la arquitectura de contenedores | [README.md, sección 2](README.md#2-arquitectura) |
| Instrucciones para ejecutar el ecosistema (`docker compose up`) | Este archivo, pasos [1](#1-requisitos) a [6](#6-verificar-que-todo-funciona); el README lo enlaza al inicio |
| Decisiones técnicas de cada Dockerfile | [README.md, sección 5](README.md#5-decisiones-técnicas-por-servicio) |

---

## 1. Requisitos

- **Git**, para clonar el repositorio.
- **Docker Engine 23 o superior** con **Docker Compose v2.17 o superior** (en Windows/macOS, Docker Desktop actualizado).
  ```bash
  docker --version
  docker compose version
  ```
- **Conexión a Internet** durante el primer build: se descargan las imágenes base, las dependencias de pnpm y las de Composer.
- **Puertos libres** en el host: `3000` (frontend) y `8000` (API). Si alguno está ocupado, ver el [paso 3](#3-opcional-cambiar-los-puertos).
- En Linux/macOS: `openssl` (ya viene instalado) para generar las claves.

## 2. Clonar el repositorio y crear el archivo `.env`

Clona el repositorio y entra en su carpeta raíz. Todos los comandos de esta guía se ejecutan desde ahí:

```bash
git clone https://github.com/CessarGit/OrquestacionDockerKodiStore.git
cd OrquestacionDockerKodiStore
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

> No bajes `PHP_VERSION` de 8.4: la rúbrica pide PHP 8.2 o superior, pero las dependencias de `composer.lock` exigen PHP 8.4.1 o superior.

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

Además, `laravel` necesita salida a Internet para llamar a Stripe, y la red `backend_net` la bloquea. Para probar pagos, quita `internal: true` de `backend_net` en `docker-compose.yml` y recrea la red con `docker compose down && docker compose up -d`. MySQL sigue sin publicar su puerto.

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

La documentación interactiva de la API (Swagger) está en http://localhost:8000/api/documentation.

También puedes probar la API desde la terminal:

```bash
API=http://localhost:8000/api   # ajusta el puerto si lo cambiaste

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

## 6. Verificar que todo funciona

```bash
./scripts/smoke-test.sh
```

El script revisa que el frontend, Nginx y Laravel respondan y que Laravel se conecte a MySQL. También comprueba que MySQL no esté expuesto al host, que PHP-FPM y Next.js no corran como root y que exista el volumen de datos. Debe terminar con:

```
Resultado: TODO OK
```

**Persistencia de datos:** después de crear un producto, reinicia el ecosistema y comprueba que el producto sigue ahí:

```bash
docker compose down
docker compose up -d
```

## 7. Comprobar los requisitos de la rúbrica

Con el ecosistema levantado, ejecuta los comandos desde la carpeta raíz.

### 7.1 Frontend: Next.js

| Requisito | Cómo se cumple |
|-----------|----------------|
| Next.js 16 o superior con App Router | `next` 16.3.8; rutas en `frontend/KodiStore/src/app/` |
| Build de producción (`next build`) | Etapa `builder` de `frontend/Dockerfile`, con `output: "standalone"` |
| Imagen base `node:20-alpine` | `NODE_VERSION=20-alpine` en `.env` |
| Multi-stage build | Etapas `base` → `deps` → `builder` → `runner` |
| Solo los archivos necesarios para ejecutar | La etapa `runner` copia únicamente `.next/standalone`, `.next/static` y `public/` |
| Puerto 3000 | `EXPOSE 3000` y `3000:3000` en `docker-compose.yml` |

```bash
grep '"next"' frontend/KodiStore/package.json   # "next": "16.3.8"
docker compose exec frontend node -v            # v20.x
docker compose exec frontend ls -A /app         # .next node_modules package.json public server.js
curl -I http://localhost:3000                   # HTTP/1.1 200 OK
```

> **Sobre `next start`:** con `output: "standalone"`, `next build` genera `server.js`, el servidor de producción que sustituye a `next start`; el propio Next.js recomienda usarlo en este modo. Para ejecutar `next start` literalmente, cambia `target: runner` por `target: runner-start` en el servicio `frontend` de `docker-compose.yml` y reconstruye. Funciona, pero la imagen pesa más y Next.js muestra una advertencia.

### 7.2 Backend: API Laravel

| Requisito | Cómo se cumple |
|-----------|----------------|
| Laravel con PHP 8.2 o superior | Laravel 13 con PHP 8.4 |
| PHP-FPM con Nginx como proxy inverso | Servicios `laravel` (PHP-FPM) y `backend` (Nginx) |
| Imagen base `php:8.2-fpm-alpine` (recomendada) | `php:8.4-fpm-alpine`, misma variante; `composer.lock` exige PHP ≥ 8.4.1 |
| Extensiones requeridas | El build comprueba las siete y falla si falta alguna |
| Composer en etapa de build | Etapa `vendor` de `backend/Dockerfile`, con `--no-dev` |
| Solo `vendor/` en la imagen final | De la etapa de build solo se copia `vendor/`; Composer y los compiladores no llegan a la imagen |
| Puerto 8000 u 80 | Nginx expone el `80`, publicado en el `8000` del host |

```bash
docker compose exec laravel php artisan --version   # Laravel Framework 13.x
docker compose exec laravel php -v                  # PHP 8.4.x

# Deben aparecer las 7 extensiones
docker compose exec laravel sh -c 'php -m | grep -ixE "pdo_mysql|mbstring|openssl|tokenizer|xml|ctype|json"'

# vendor/ está, pero no Composer ni los compiladores
docker compose exec laravel sh -c 'test -f vendor/autoload.php && echo "vendor OK"'
docker compose exec laravel sh -c 'which composer gcc make || echo "sin herramientas de build"'

curl -I http://localhost:8000/api/products          # HTTP/1.1 200 OK (ajusta el puerto si lo cambiaste)
```

### 7.3 Base de datos: MySQL

| Requisito | Cómo se cumple |
|-----------|----------------|
| MySQL 8.0 o superior, imagen oficial `mysql:8.0` | `MYSQL_VERSION=8.0` en `.env` |
| Variables de entorno | Las cuatro `MYSQL_*` del [paso 2](#2-clonar-el-repositorio-y-crear-el-archivo-env) |
| Volumen persistente en `/var/lib/mysql` | Volumen `bootcamp_db_data` |
| Script de inicialización (opcional) | `database/init/` montado en `/docker-entrypoint-initdb.d/`; solo corre con el volumen vacío |
| Puerto 3306 interno, no expuesto al host | El servicio `db` no tiene `ports:` |

```bash
docker compose exec db mysqld --version                  # Ver 8.0.x
docker compose exec db ls /docker-entrypoint-initdb.d    # 01-init.sql
docker volume inspect bootcamp_db_data --format '{{.Name}}'

# Las cuatro variables están definidas (no muestra sus valores)
docker compose exec db sh -c 'for v in MYSQL_ROOT_PASSWORD MYSQL_DATABASE MYSQL_USER MYSQL_PASSWORD; do [ -n "$(printenv $v)" ] && echo "$v definida"; done'

# {} significa que no hay puertos publicados en el host
docker inspect -f '{{json .HostConfig.PortBindings}}' $(docker compose ps -q db)
```

## 8. Comandos del día a día

| Para…                                  | Comando                                              |
|----------------------------------------|------------------------------------------------------|
| Ver logs de un servicio                | `docker compose logs -f laravel` (o `frontend`, `backend`, `db`) |
| Aplicar cambios en el código           | `docker compose up -d --build`                       |
| Reconstruir solo el frontend           | `docker compose up -d --build frontend`              |
| Abrir la consola de Laravel            | `docker compose exec laravel php artisan tinker`     |
| Ver el estado de las migraciones       | `docker compose exec laravel php artisan migrate:status` |
| Entrar al cliente MySQL                | `docker compose exec db mysql -u root -p` (la clave es `MYSQL_ROOT_PASSWORD` de `.env`) |
| Detener (los datos se conservan)       | `docker compose down`                                |
| Detener y **borrar todos los datos**   | `docker compose down -v`                             |

## 9. Solución de problemas

| Síntoma | Causa y solución |
|---------|------------------|
| `Define APP_KEY en .env` (o `JWT_SECRET`, `MYSQL_...`) | Falta esa variable en `.env`. Repite el [paso 2](#2-clonar-el-repositorio-y-crear-el-archivo-env). |
| `port is already allocated` | El puerto está ocupado. Cámbialo según el [paso 3](#3-opcional-cambiar-los-puertos) y vuelve a levantar. |
| `laravel` queda `unhealthy` o se reinicia | Revisa `docker compose logs laravel`. Suele ser una migración fallida o contraseñas de MySQL cambiadas después del primer arranque (ver la fila siguiente). |
| Cambiaste las contraseñas de MySQL y Laravel ya no conecta | MySQL guarda las contraseñas del **primer** arranque en el volumen. Vuelve a las anteriores, o ejecuta `docker compose down -v` (**borra los datos**) y levanta de nuevo. |
| Error de CORS en el navegador | `FRONTEND_URL` en `.env` debe coincidir exactamente con la URL del frontend, por ejemplo `http://localhost:3000`. |
| El frontend llama a un puerto de API equivocado | Cambiaste `NEXT_PUBLIC_API_BASE_URL` sin reconstruir: `docker compose up -d --build frontend`. |
| El build falla descargando paquetes | Revisa la conexión a Internet y vuelve a ejecutar `docker compose up -d --build`. |
| `exec ... entrypoint: no such file or directory` (Windows) | Saltos de línea CRLF. Convierte `backend/docker/entrypoint.sh` a LF en tu editor y reconstruye. |

---

Para la arquitectura y las decisiones técnicas (multi-stage, seguridad, redes y optimización), consulta el [README.md](README.md).
