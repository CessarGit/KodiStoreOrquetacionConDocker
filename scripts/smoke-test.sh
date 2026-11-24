#!/usr/bin/env bash
# Smoke test (semana 16): verifica que el ecosistema "arranca" correctamente.
# Uso:  ./scripts/smoke-test.sh   (con el stack ya levantado)
set -uo pipefail
cd "$(dirname "$0")/.."
# Puertos: variable de entorno > .env > valor por defecto
envval() { [ -f .env ] && grep -E "^$1=" .env | tail -1 | cut -d= -f2-; }
FRONT=${FRONTEND_PORT:-$(envval FRONTEND_PORT)}; FRONT=${FRONT:-3000}
API=${API_PORT:-$(envval API_PORT)}; API=${API:-8000}
fail=0

check() { # $1=descripción  $2..=comando
  local desc=$1; shift
  if "$@" >/dev/null 2>&1; then echo "  OK    $desc"; else echo "  FALLA $desc"; fail=1; fi
}

echo "== Estado de los servicios =="
docker compose ps --format 'table {{.Service}}\t{{.Status}}'

echo
echo "== Pruebas =="
check "Frontend responde en :$FRONT"          curl -fsS "http://localhost:$FRONT/"
check "Nginx responde en :$API"               curl -fsS "http://localhost:$API/nginx-health"
check "Laravel responde vía Nginx"            sh -c "curl -sS -o /dev/null -w '%{http_code}' http://localhost:$API/ | grep -qE '^(200|302|404)$'"
check "Laravel conecta con MySQL"             docker compose exec -T laravel php artisan migrate:status
# Se revisa el contenedor (no el puerto 3306 del host, que puede ocupar un MySQL local)
check "MySQL NO está expuesto al host (3306)" sh -c "docker inspect -f '{{json .NetworkSettings.Ports}}' \$(docker compose ps -q db) | grep -qv HostPort"
check "PHP-FPM corre sin root"                sh -c "[ \"\$(docker compose exec -T laravel id -u)\" != 0 ]"
check "Next.js corre sin root"                sh -c "[ \"\$(docker compose exec -T frontend id -u)\" != 0 ]"
check "Volumen persistente de MySQL existe"   docker volume inspect bootcamp_db_data

echo
[ $fail -eq 0 ] && echo "Resultado: TODO OK" || { echo "Resultado: hay fallas"; exit 1; }
