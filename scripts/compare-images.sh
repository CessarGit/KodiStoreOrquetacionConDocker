#!/usr/bin/env bash
# Construye las imágenes "antes" (básicas) y "después" (multi-stage) y
# muestra la comparación de tamaño y tiempo de build. Útil como evidencia.
# Uso:  ./scripts/compare-images.sh
set -euo pipefail
cd "$(dirname "$0")/.."

FRONT_CTX=./frontend/KodiStore
API_CTX=./backend/e-commerce
FRONT_DF=(-f frontend/Dockerfile)
API_DF=(-f backend/Dockerfile --build-context docker-config=backend/docker)

build() { # $1=tag  $2=contexto  $3..=args extra
  local tag=$1 ctx=$2; shift 2
  local start end
  start=$(date +%s)
  docker build -q -t "$tag" "$@" "$ctx" >/dev/null
  end=$(date +%s)
  printf "%-32s build: %4ss\n" "$tag" "$((end - start))"
}

echo "== Construyendo imágenes =="
build cmp/frontend:basic   "$FRONT_CTX" -f docs/baseline/frontend/Dockerfile.basic
build cmp/frontend:multi   "$FRONT_CTX" "${FRONT_DF[@]}" --target runner
build cmp/api:basic        "$API_CTX"   -f docs/baseline/backend/Dockerfile.basic
build cmp/api-php:multi    "$API_CTX"   "${API_DF[@]}" --target production
build cmp/api-nginx:multi  "$API_CTX"   "${API_DF[@]}" --target nginx

echo
echo "== Tamaño de imágenes =="
docker image ls --format 'table {{.Repository}}:{{.Tag}}\t{{.Size}}' | grep -E '^(REPOSITORY|cmp/)'

echo
echo "== Capas de la imagen final del frontend =="
docker history --format 'table {{.Size}}\t{{.CreatedBy}}' cmp/frontend:multi | head -15

echo
echo "== Usuario con el que corre cada imagen final =="
for img in cmp/frontend:multi cmp/api-php:multi; do
  printf "%-22s USER=%s\n" "$img" "$(docker image inspect -f '{{.Config.User}}' "$img")"
done

echo
echo "== Rebuild con caché (sin cambios en dependencias) =="
build cmp/frontend:multi "$FRONT_CTX" "${FRONT_DF[@]}" --target runner
build cmp/api-php:multi  "$API_CTX"   "${API_DF[@]}" --target production
