#!/bin/sh
# Entrypoint del contenedor Laravel (PHP-FPM).
# Las cachés se generan al ARRANCAR (no en el build) porque dependen de las
# variables de entorno de cada ambiente: así la MISMA imagen sirve para
# staging y producción sin reconstruirla.
set -e

if [ "$1" = "php-fpm" ]; then
  if [ -z "${APP_KEY:-}" ]; then
    echo "ERROR: APP_KEY no está definida. Revisa el archivo .env" >&2
    exit 1
  fi

  php artisan config:cache
  php artisan route:cache
  php artisan view:cache
  php artisan event:cache

  if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    echo "Ejecutando migraciones..."
    php artisan migrate --force
  fi
fi

# exec: PHP-FPM pasa a ser PID 1 y recibe las señales (docker stop)
exec "$@"
