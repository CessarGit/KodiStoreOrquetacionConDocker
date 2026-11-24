# syntax=docker/dockerfile:1
# =============================================================================
#  FRONTEND · Next.js 16+ (App Router) · Build multi-stage optimizado
# -----------------------------------------------------------------------------
#  Etapas:
#    base         -> imagen común (node:alpine) con variables mínimas
#    deps         -> instala dependencias con pnpm (capa cacheada por lockfile)
#    builder      -> compila la app con `next build` (output: "standalone")
#    test         -> (opcional) corre la suite de pruebas (Vitest) en el build
#    runner-start -> (alternativa) runtime clásico con `next start`
#    runner       -> (DEFAULT) runtime mínimo con el servidor standalone
#
#  Contexto: la carpeta del proyecto (frontend/KodiStore). Este Dockerfile y
#  Dockerfile.dockerignore viven un nivel arriba.
#  Build:   docker build -f frontend/Dockerfile -t bootcamp/frontend:1.0.0 frontend/KodiStore
#  Tests:   docker build -f frontend/Dockerfile --target test frontend/KodiStore
# =============================================================================

# Versión parametrizable. La actividad recomienda 20-alpine; Node 20 llegó a
# fin de soporte el 30/04/2026, así que en producción real conviene 24-alpine.
ARG NODE_VERSION=20-alpine

# -----------------------------------------------------------------------------
# 1) BASE: punto de partida común para todas las etapas
# -----------------------------------------------------------------------------
FROM node:${NODE_VERSION} AS base
WORKDIR /app
ENV NEXT_TELEMETRY_DISABLED=1 \
    COREPACK_ENABLE_DOWNLOAD_PROMPT=0
# Corepack instala la versión exacta de pnpm declarada en "packageManager"
RUN corepack enable

# -----------------------------------------------------------------------------
# 2) DEPS: solo manifiestos -> esta capa se reutiliza mientras el lockfile
#    no cambie, aunque modifiques el código fuente (caché de capas).
# -----------------------------------------------------------------------------
FROM base AS deps
# libc6-compat: algunas dependencias nativas (p. ej. SWC/sharp) lo requieren en Alpine
RUN apk add --no-cache libc6-compat
COPY package.json pnpm-lock.yaml pnpm-workspace.yaml ./
# Cache mount de BuildKit: el store de pnpm no queda dentro de la imagen
RUN --mount=type=cache,id=pnpm-store,target=/pnpm/store \
    pnpm install --frozen-lockfile --store-dir /pnpm/store

# -----------------------------------------------------------------------------
# 3) BUILDER: compila la aplicación
# -----------------------------------------------------------------------------
FROM base AS builder
COPY --from=deps /app/node_modules ./node_modules
COPY . .

# Las variables NEXT_PUBLIC_* se "incrustan" en el bundle del navegador
# durante el build, por eso entran como ARG y no en runtime.
ARG NEXT_PUBLIC_API_BASE_URL=http://localhost:8000/api
ARG NEXT_PUBLIC_APP_URL=http://localhost:3000
ENV NEXT_PUBLIC_API_BASE_URL=${NEXT_PUBLIC_API_BASE_URL} \
    NEXT_PUBLIC_APP_URL=${NEXT_PUBLIC_APP_URL} \
    NODE_ENV=production

# El caché incremental de Next (.next/cache) vive en un cache mount:
# acelera rebuilds y no infla la imagen.
RUN --mount=type=cache,target=/app/.next/cache \
    pnpm run build

# Verificación temprana: sin standalone la etapa runner no puede funcionar.
# mkdir -p public: garantiza que exista aunque el proyecto no la tenga.
RUN if [ ! -f .next/standalone/server.js ]; then \
      echo "ERROR: agrega  output: 'standalone'  en next.config.(ts|js|mjs)" >&2; exit 1; \
    fi \
 && mkdir -p public

# -----------------------------------------------------------------------------
# 4) TEST (opcional, semana 16): falla el build si fallan las pruebas.
#    No forma parte de la imagen final.
# -----------------------------------------------------------------------------
FROM builder AS test
ENV CI=true
RUN pnpm run --if-present test

# -----------------------------------------------------------------------------
# 5) RUNNER-START (alternativa): `next start` con dependencias de producción.
#    Útil si tu proyecto no puede usar standalone. Más pesada que `runner`.
#    Build: docker build --target runner-start -t bootcamp/frontend:start .
# -----------------------------------------------------------------------------
FROM base AS runner-start
ENV NODE_ENV=production PORT=3000 HOSTNAME=0.0.0.0
COPY package.json pnpm-lock.yaml pnpm-workspace.yaml ./
RUN --mount=type=cache,id=pnpm-store,target=/pnpm/store \
    pnpm install --frozen-lockfile --prod --store-dir /pnpm/store
COPY --from=builder /app/public ./public
COPY --from=builder /app/next.config.* ./
COPY --from=builder /app/.next ./.next
RUN rm -rf .next/standalone \
 && mkdir -p .next/cache && chown node:node .next/cache
USER node
EXPOSE 3000
CMD ["node_modules/.bin/next", "start"]

# -----------------------------------------------------------------------------
# 6) RUNNER (DEFAULT): imagen final mínima.
#    Solo contiene el servidor standalone (código trazado + node_modules
#    estrictamente necesarios), los assets estáticos y /public.
#    Sin npm cache, sin código fuente, sin devDependencies.
# -----------------------------------------------------------------------------
FROM node:${NODE_VERSION} AS runner
WORKDIR /app

ENV NODE_ENV=production \
    NEXT_TELEMETRY_DISABLED=1 \
    PORT=3000 \
    HOSTNAME=0.0.0.0

# Archivos propiedad de root (solo lectura para la app) -> si un atacante
# compromete el proceso, no puede modificar el código.
COPY --from=builder /app/public ./public
COPY --from=builder /app/.next/standalone ./
COPY --from=builder /app/.next/static ./.next/static

# Único directorio escribible: caché de ISR / optimización de imágenes
RUN mkdir -p .next/cache && chown node:node .next/cache

# Usuario sin privilegios (uid 1000) incluido en la imagen oficial de Node
USER node

EXPOSE 3000

HEALTHCHECK --interval=15s --timeout=5s --start-period=20s --retries=3 \
  CMD node -e "fetch('http://127.0.0.1:3000/').then(r=>process.exit(r.status<500?0:1)).catch(()=>process.exit(1))"

# server.js es generado por `next build` en modo standalone
# (equivalente optimizado de `next start`)
CMD ["node", "server.js"]
