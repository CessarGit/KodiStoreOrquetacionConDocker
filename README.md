# Ecosistema de producción con Docker multi-stage

**Bootcamp Full Stack Junior · Módulo V: DevOps, Integración y Validación Profesional**
Rol: DevOps Engineer

> **Para mas informacion tecnica de como utilizar este ecosistema, en GitHub, busca el archivo:** en INSTRUCCIONES.d

---

## 1. Objetivo

Preparar para producción un ecosistema de tres servicios (frontend, API y base de datos) con **imágenes ligeras, seguras y reproducibles**. Se reemplazan los Dockerfiles básicos del equipo (builds lentos, imágenes pesadas, entornos inconsistentes) por **builds multi-stage**, un orden de capas pensado para la **caché de Docker** y un único `docker-compose.yml` que levanta todo con un solo comando.

## 2. Arquitectura

```
                         Host (tu máquina / servidor)
   ┌───────────────────────────────────────────────────────────────────────┐
   │   :3000                          :8000                                │
   │     │                              │                                  │
   │ ┌───▼──────────┐   frontend_net  ┌─▼──────────────┐                   │
   │ │  frontend    │ ───────────────►│   backend      │                   │
   │ │  Next.js 16  │  http://backend │   Nginx        │                   │
   │ │  node:alpine │                 │   (proxy)      │                   │
   │ └──────────────┘                 └─┬──────────────┘                   │
   │                                    │ FastCGI  laravel:9000            │
   │                     backend_net    │ (internal: sin salida a Internet)│
   │                                  ┌─▼──────────────┐     ┌───────────┐ │
   │                                  │   laravel      │────►│    db     │ │
   │                                  │   PHP 8.4-FPM  │3306 │  MySQL    │ │
   │                                  └────────────────┘     └─────┬─────┘ │
   │                                                               │       │
   │                                              volumen db_data ─┘       │
   └───────────────────────────────────────────────────────────────────────┘
```

| Servicio   | Imagen                     | Puerto interno | Publicado en el host | Red(es)                   |
|------------|----------------------------|----------------|----------------------|---------------------------|
| `frontend` | `bootcamp/frontend`        | 3000           | **3000**             | frontend_net              |
| `backend`  | `bootcamp/api-nginx`       | 80             | **8000**             | frontend_net, backend_net |
| `laravel`  | `bootcamp/api-php`         | 9000 (FastCGI) | no                   | backend_net               |
| `db`       | `mysql:8.0` (oficial)      | 3306           | **no** (producción)  | backend_net               |

La API Laravel son **dos contenedores** (Nginx + PHP-FPM) que salen del **mismo Dockerfile** con dos `--target`. Así se respeta el principio "un proceso por contenedor": cada uno escala, se reinicia y se monitorea por separado.

## 3. Estructura del repositorio

```
ecosistema-docker-prod/
├── docker-compose.yml          # Orquestación de los 4 contenedores
├── .env.example                # Variables documentadas (copiar a .env)
├── INSTRUCCIONES.md            # Guía de ejecución y comprobación de requisitos
├── frontend/
│   ├── KodiStore/              # ← proyecto Next.js 16 (pnpm) = contexto de build
│   ├── Dockerfile              # multi-stage: deps → builder → test → runner
│   ├── Dockerfile.dockerignore # .dockerignore propio de este Dockerfile (BuildKit)
│   └── next.config.ts.example  # output: "standalone"
├── backend/
│   ├── e-commerce/             # ← API Laravel 13 (JWT + Stripe) = contexto de build
│   ├── Dockerfile              # multi-stage: base → vendor → test → production / nginx
│   ├── Dockerfile.dockerignore
│   └── docker/                 # contexto adicional `docker-config`
│       ├── entrypoint.sh       # cachés de Laravel + migraciones al arrancar
│       ├── nginx/default.conf  # proxy inverso + cabeceras de seguridad
│       └── php/                # app.ini, opcache.ini, www.conf (pool FPM)
├── database/init/01-init.sql   # se monta en /docker-entrypoint-initdb.d/
├── scripts/
│   ├── compare-images.sh       # evidencia: tamaño antes vs después
│   └── smoke-test.sh           # evidencia: el ecosistema arranca bien
└── docs/baseline/              # Dockerfiles "básicos" de partida (para comparar)
```

## 4. Puesta en marcha

### 4.1 Adaptaciones de los proyectos

Los proyectos viven en `frontend/KodiStore` y `backend/e-commerce`, y los Dockerfiles un nivel arriba. Por eso `docker-compose.yml` usa `context: <proyecto>` + `dockerfile: ../Dockerfile`, y el backend recibe `backend/docker` como contexto adicional (`docker-config`).

| Tema | Ajuste |
|------|--------|
| Gestor de paquetes | El frontend usa **pnpm** (`pnpm-lock.yaml`) vía Corepack |
| Versión de Node | `NODE_VERSION=20-alpine`, la recomendada por la actividad (`24-alpine` también funciona) |
| Versión de PHP | `PHP_VERSION=8.4`: las dependencias de `composer.lock` exigen PHP ≥ 8.4.1 |
| Salida de Next.js | `output: "standalone"` en `next.config.ts` |
| URL de la API | El frontend usa `NEXT_PUBLIC_API_BASE_URL`; en el servidor, `src/lib/constants.ts` prefiere `API_INTERNAL_URL` |
| Secretos extra | `JWT_SECRET`, `JWT_ALGO`, `STRIPE_*` y `HASH_DRIVER` se pasan al contenedor `laravel` |
| CORS | `config/cors.php` lee `FRONTEND_URL` (admite varios orígenes separados por coma) |
| `/products` | `export const dynamic = 'force-dynamic'` para no congelar la lista durante el build |

### 4.2 Variables de entorno

```bash
cp .env.example .env
# Genera la APP_KEY de Laravel y pégala en .env
echo "base64:$(openssl rand -base64 32)"
# Genera el JWT_SECRET y pégalo en .env
openssl rand -base64 48
```
Cambia también `MYSQL_ROOT_PASSWORD` y `MYSQL_PASSWORD`, y copia tus claves de prueba de Stripe (`STRIPE_KEY`, `STRIPE_SECRET`). Si falta un secreto, `docker compose` se detiene con un mensaje claro, sin arrancar a medias.

### 4.3 Levantar todo con un solo comando

```bash
docker compose up -d --build
docker compose ps          # espera a que los 4 servicios estén "healthy"
```

- Frontend: http://localhost:3000
- API: http://localhost:8000/api/... (puerto `API_PORT` de `.env`)

Orden de arranque garantizado por *healthchecks*: `db` → `laravel` (migra la base) → `backend` (Nginx) → `frontend`.

### 4.4 Comandos útiles

```bash
docker compose logs -f laravel                 # logs de la API
docker compose exec laravel php artisan tinker # consola de Laravel
docker compose exec db mysql -u root -p        # cliente MySQL (dentro de la red)
docker compose down                            # detiene (los datos se conservan)
docker compose down -v                         # detiene y BORRA el volumen de datos
```

## 5. Decisiones técnicas por servicio

### 5.1 Frontend — Next.js (`frontend/Dockerfile`)

| Etapa          | Qué hace                                                                 | Por qué                                                       |
|----------------|--------------------------------------------------------------------------|---------------------------------------------------------------|
| `base`         | `node:20-alpine`, Corepack (pnpm), telemetría desactivada                | Alpine pesa una fracción de la imagen Debian                  |
| `deps`         | Copia **solo** los manifiestos de pnpm y ejecuta `pnpm install --frozen-lockfile` | Esta capa se reutiliza mientras no cambien las dependencias   |
| `builder`      | Copia el código y ejecuta `next build` con `output: "standalone"`        | Genera un servidor mínimo con solo los módulos que se usan    |
| `test`         | `pnpm run --if-present test` — opcional                                  | Punto de enganche para pruebas; KodiStore aún no tiene        |
| `runner-start` | Alternativa con `next start` + `pnpm install --prod`                     | Para proyectos que no pueden usar standalone                  |
| **`runner`**   | Copia solo `.next/standalone`, `.next/static` y `public/`                | Imagen final mínima, sin código fuente ni devDependencies     |

Sobre **`next start` vs standalone**: `node server.js` (standalone) es el servidor de producción que genera el propio `next build` (hace lo mismo que `next start`, pero solo incluye los archivos trazados como necesarios). Para usar `next start` tal cual, pon `target: runner-start` en el compose: funciona, aunque la imagen pesa más y Next.js avisa de que con `standalone` recomienda `server.js`.

**Variables del frontend:**
- `NEXT_PUBLIC_API_BASE_URL` se usa en el **navegador** y se incrusta en el JavaScript durante el build (por eso es un `ARG`). Si la cambias, reconstruye la imagen.
- `API_INTERNAL_URL=http://backend/api` se usa en el **servidor** (Server Components, Route Handlers) y viaja por la red interna de Docker:
  ```ts
  const API = process.env.API_INTERNAL_URL ?? process.env.NEXT_PUBLIC_API_BASE_URL;
  const res = await fetch(`${API}/products`, { cache: "no-store" });
  ```

### 5.2 Backend — Laravel (`backend/Dockerfile`)

| Etapa            | Qué hace                                                                                  | Por qué                                                            |
|------------------|-------------------------------------------------------------------------------------------|--------------------------------------------------------------------|
| `base`           | `php:8.4-fpm-alpine` + `pdo_mysql`, `opcache`, `bcmath`; `php.ini-production`              | Los compiladores (`$PHPIZE_DEPS`) se instalan y borran en el mismo `RUN` |
| `vendor`         | Composer (copiado desde `composer:2`) ejecuta `composer install --no-dev` primero con solo `composer.json/lock` | La capa más lenta queda en caché; Composer no llega a producción   |
| `test`           | Instala dev-deps y ejecuta `php artisan test` — opcional                                  | Pruebas unitarias/integración en el build (semana 16)              |
| **`production`** | Código + **solo `vendor/`** desde la etapa `vendor`; usuario `www-data`                   | Sin Composer, sin compiladores, sin dependencias de desarrollo, sin `.env`              |
| **`nginx`**      | `nginx:alpine` + `default.conf` + solo `public/`                                          | Proxy inverso liviano en el puerto 80                              |

**Extensiones requeridas**: `pdo_mysql` se compila; `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype` y `json` ya vienen en la imagen oficial. El build las **verifica** todas con `php -m`, junto con las demás que usa la app, y falla si falta alguna.

**¿Por qué las cachés de Laravel se generan al arrancar y no en el build?** `config:cache` guarda los valores de las variables de entorno. Si se ejecutara en el build, la imagen quedaría atada a un ambiente. Con el `entrypoint.sh`, **la misma imagen** sirve para staging y producción (principio *build once, run anywhere*).

### 5.3 Base de datos — MySQL

- Imagen oficial `mysql:8.0`, configurada con `MYSQL_ROOT_PASSWORD`, `MYSQL_DATABASE`, `MYSQL_USER` y `MYSQL_PASSWORD`.
- **Named volume** `db_data` → `/var/lib/mysql`: los datos sobreviven a `docker compose down`, a `--build` y a la recreación de contenedores.
- `./database/init` → `/docker-entrypoint-initdb.d/` (solo lectura): se ejecuta **solo la primera vez**, con el volumen vacío.
- **Sin `ports:`**: el 3306 solo es accesible dentro de `backend_net`. Nadie desde fuera del host puede conectarse a la base.
- El healthcheck usa TCP (`-h 127.0.0.1`) a propósito: durante la inicialización MySQL arranca sin red, así que el servicio solo se marca *healthy* cuando está listo de verdad.

## 6. Optimización: tamaño y velocidad de build

### 6.1 Técnicas aplicadas

1. **Multi-stage build**: todo lo que solo sirve para compilar (pnpm, Composer, gcc, devDependencies, código TypeScript) se queda en etapas intermedias que se descartan.
2. **Orden de capas**: primero lo que cambia poco (sistema, extensiones, manifiestos de dependencias) y al final lo que cambia mucho (código). Si editas un componente, Docker reutiliza la capa de `pnpm install` o `composer install`.
3. **Cache mounts de BuildKit** (`RUN --mount=type=cache`): el caché de pnpm, Composer y `.next/cache` acelera los rebuilds **sin quedar dentro de la imagen**.
4. **`.dockerignore`**: `node_modules`, `vendor`, `.git`, `.env` y logs no se envían al daemon. El contexto es más chico y la caché no se invalida por archivos irrelevantes.
5. **Imágenes Alpine** y limpieza de paquetes de compilación en el mismo `RUN`.

### 6.2 Resultados

Medición del **contenido de la aplicación** en una app Next.js 16.4 de prueba (sin contar la imagen base):

| Variante                                      | Tamaño   |
|-----------------------------------------------|----------|
| Básica (código + `node_modules` completo)     | 655 MB   |
| `runner-start` (deps de producción + `.next`) | 469 MB   |
| **`runner` (standalone)**                     | **68 MB**|

A eso se suma la imagen base: `node:20` (Debian) pesa cerca de 1 GB y `node:20-alpine` solo una fracción de eso. Para medir **tus** imágenes reales:

```bash
./scripts/compare-images.sh
```
El script construye las versiones básica (`docs/baseline/`) y optimizada, y muestra tamaños, capas (`docker history`), el usuario de ejecución y el tiempo de un rebuild con caché.

## 7. Seguridad (reducción de la superficie de ataque)

| Medida                                         | Dónde                         |
|------------------------------------------------|-------------------------------|
| Next.js (`node`) y PHP-FPM (`www-data`) sin root; en Nginx solo el proceso maestro es root, los workers no | Dockerfiles (`USER`) |
| Código propiedad de root | `COPY` sin `--chown`; en Next.js solo `.next/cache` es escribible; en Laravel, `storage/`, `bootstrap/cache` y el directorio raíz `/var/www/html` (la imagen oficial de PHP lo asigna a `www-data`) |
| Sin compiladores, Composer ni caché de pnpm en producción | multi-stage              |
| Secretos fuera de las imágenes (`.env` en `.dockerignore`) | runtime vía `environment` |
| `no-new-privileges` en todos los servicios; `cap_drop: [ALL]` en `frontend` y `laravel` | `docker-compose.yml` |
| Red `backend_net` con `internal: true`         | la BD y PHP no salen a Internet |
| MySQL y PHP-FPM sin puertos publicados         | `docker-compose.yml`          |
| `server_tokens off`, `expose_php = Off`, sin `X-Powered-By` | Nginx / PHP / Next  |
| Cabeceras `X-Frame-Options`, `nosniff`, `Referrer-Policy` | `nginx/default.conf` |
| Bloqueo de `.env`, archivos ocultos y cualquier `.php` distinto de `index.php` | `nginx/default.conf` |
| Límites de CPU/memoria y rotación de logs      | `deploy.resources`, `logging` |

## 8. Redes y volúmenes (semana 17)

- **Redes bridge personalizadas** con DNS interno: los servicios se encuentran por **nombre** (`db`, `laravel`, `backend`), nunca por IP.
- Nginx re-resuelve `laravel` cada 10 s (`resolver 127.0.0.11`). Si PHP-FPM se reinicia y cambia de IP, el proxy sigue funcionando (el problema de las IPs dinámicas visto en clase).
- **Segmentación**: el frontend **no puede** llegar a MySQL; solo ve a Nginx.
- **Named volume** para la base (portabilidad y gestión por Docker) en lugar de un bind mount, que queda para desarrollo local.

**Pagos con Stripe:** la API llama a Stripe desde `laravel`, y `internal: true` bloquea esa salida. Para procesar pagos, quita `internal: true` de `backend_net` y recrea la red (`docker compose down && docker compose up -d`). MySQL sigue sin publicar su puerto, pero `backend_net` deja de estar aislada de Internet.

## 9. Calidad (semana 16)

- **Pruebas en el build** (base de la pirámide de testing):
  ```bash
  docker build -f frontend/Dockerfile --target test frontend/KodiStore   # sin pruebas por ahora
  docker build -f backend/Dockerfile --build-context docker-config=backend/docker \
    --target test backend/e-commerce                                     # PHPUnit
  ```
  Si una prueba falla, el build falla y la imagen no se genera.
- **Smoke test** del ecosistema completo:
  ```bash
  ./scripts/smoke-test.sh
  ```
  Verifica que el frontend, Nginx, Laravel y la conexión a MySQL respondan, que MySQL **no** esté expuesto al host, que PHP-FPM y Next.js no sean root y que exista el volumen.

## 10. Flujo DevOps: Build → Tag → Push → Pull → Run

```bash
# Build + Tag (versión explícita, nunca solo "latest")
TAG=1.0.0 REGISTRY=tuusuario docker compose build
# Push al registry (Docker Hub, GHCR, ECR...)
docker login
TAG=1.0.0 REGISTRY=tuusuario docker compose push frontend backend laravel
# En el servidor: Pull + Run
TAG=1.0.0 REGISTRY=tuusuario docker compose pull
TAG=1.0.0 REGISTRY=tuusuario docker compose up -d
```

## 11. Evidencias sugeridas para la entrega

1. `docker compose ps` con los 4 servicios en estado **healthy**.
2. Salida de `./scripts/compare-images.sh` (tabla de tamaños antes y después).
3. `docker history bootcamp/frontend:1.0.0` (pocas capas y livianas).
4. Rebuild después de cambiar solo un componente: las capas de dependencias aparecen como `CACHED`.
5. Salida de `./scripts/smoke-test.sh` → `TODO OK`.
6. Persistencia: crear un registro, ejecutar `docker compose down && docker compose up -d` y comprobar que sigue ahí.
7. `docker network inspect bootcamp-prod_backend_net` mostrando `"Internal": true`.
8. Vista de la extensión Docker de VS Code con contenedores, imágenes, redes y volúmenes.

## 12. Problemas comunes

| Síntoma | Causa y solución |
|---------|------------------|
| `ERROR: agrega output: 'standalone'` | Falta `output: "standalone"` en `next.config.ts` |
| `pnpm install` falla por el lockfile | Ejecuta `pnpm install` en local y versiona `pnpm-lock.yaml` |
| `APP_KEY no está definida` | Genérala (sección 4.2) y pégala en `.env` |
| `laravel` queda *unhealthy* o se reinicia | `docker compose logs laravel`. Suele ser una migración fallida o credenciales de BD incorrectas |
| `exec ... entrypoint: no such file or directory` | Saltos de línea CRLF de Windows; el `.gitattributes` incluido lo evita (o convierte el archivo a LF en VS Code) |
| El navegador muestra error de CORS | `FRONTEND_URL` en `.env` debe coincidir exactamente con la URL del frontend |
| Cambié `NEXT_PUBLIC_API_BASE_URL` y no se aplica | Es de build: `docker compose build frontend` |
| El script SQL no se ejecutó | Solo corre con el volumen vacío: `docker compose down -v` (borra los datos) |
| El build de Next falla descargando fuentes | `next/font/google` necesita Internet durante el build |

## 13. Nota sobre versiones

La actividad recomienda `node:20-alpine` y `mysql:8.0`, y esos son los valores de `.env.example`. Sin embargo, **Node.js 20 y MySQL 8.0 llegaron a su fin de soporte en abril de 2026** y ya no reciben parches de seguridad. Para un despliegue real, cambia en `.env`:

```env
NODE_VERSION=24-alpine   # LTS activa
MYSQL_VERSION=8.4        # LTS actual
```
No hace falta tocar ningún Dockerfile.

La actividad pide PHP 8.2 o superior. Este proyecto usa **PHP 8.4** y no admite una versión menor: las dependencias de `composer.lock` exigen PHP ≥ 8.4.1.

## 14. Validación realizada

- `docker build --check` (linter de BuildKit) en **todas** las etapas de ambos Dockerfiles: sin advertencias.
- `docker compose config`: archivo válido; falla de forma controlada si falta un secreto.
- `nginx -t` y `php-fpm -t` sobre las configuraciones incluidas. Además, Nginx + PHP-FPM corriendo juntos: enrutamiento a `index.php`, archivos estáticos, cabeceras de seguridad, bloqueo de `.env` y de otros `.php`, paso de variables de entorno y healthcheck `ping/pong`.
- Build real de una app **Next.js 16.4** reproduciendo las etapas `deps → builder → runner` y `runner-start`: ambas sirven la página, los assets de `_next/static` y `public/`, y el healthcheck responde.
- Build real de **KodiStore** (Next.js 16.3.8) con `node:20-alpine` (etapa `runner`) y con `node:24-alpine` (`runner` y `runner-start`): las tres responden 200.
- Etapa `test` del backend (PHPUnit): pasa.
