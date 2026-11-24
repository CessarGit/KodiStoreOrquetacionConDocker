# syntax=docker/dockerfile:1
# =============================================================================
#  BACKEND · API REST Laravel · PHP 8.2-FPM + Nginx · Build multi-stage
# -----------------------------------------------------------------------------
#  Etapas:
#    base        -> php:8.2-fpm-alpine + extensiones + php.ini de producción
#    vendor      -> Composer instala dependencias (--no-dev). Composer NO
#                   llega a la imagen final.
#    test        -> (opcional) instala dev-deps y ejecuta `php artisan test`
#    production  -> (target `laravel`) PHP-FPM con app + vendor/ únicamente
#    nginx       -> (target `nginx`) proxy inverso con solo /public
#
#  Contexto principal: la carpeta del proyecto (backend/e-commerce).
#  Contexto adicional `docker-config`: backend/docker (php/, nginx/, entrypoint).
#  Build (desde la raíz del repo):
#    docker build -f backend/Dockerfile --build-context docker-config=backend/docker \
#      --target production -t bootcamp/api-php:1.0.0 backend/e-commerce
#    (cambia --target por nginx o test)
# =============================================================================

# Laravel 13 + Symfony 8.1 (composer.lock) exigen PHP >= 8.4.1
ARG PHP_VERSION=8.4
ARG NGINX_VERSION=1.27
ARG COMPOSER_VERSION=2

# Alias de la imagen de Composer para copiar solo su binario
FROM composer:${COMPOSER_VERSION} AS composer-bin

# -----------------------------------------------------------------------------
# 1) BASE: runtime PHP-FPM con las extensiones requeridas
# -----------------------------------------------------------------------------
FROM php:${PHP_VERSION}-fpm-alpine AS base
WORKDIR /var/www/html

# - fcgi: cliente FastCGI diminuto para el healthcheck de PHP-FPM
# - $PHPIZE_DEPS (gcc, make, autoconf...) solo se usan para compilar y se
#   eliminan en la MISMA instrucción RUN -> no quedan en ninguna capa.
# - mbstring, openssl, tokenizer, xml, ctype y json ya vienen compiladas en la
#   imagen oficial; se instalan pdo_mysql, opcache y bcmath.
RUN set -eux; \
    apk add --no-cache fcgi; \
    apk add --no-cache --virtual .build-deps $PHPIZE_DEPS; \
    docker-php-ext-install -j"$(nproc)" pdo_mysql opcache bcmath; \
    apk del --no-network .build-deps; \
    rm -rf /tmp/* /var/cache/apk/*; \
    for ext in pdo_mysql mbstring openssl tokenizer xml ctype json bcmath curl sodium fileinfo dom; do \
      php -m | grep -qix "$ext" || { echo "Falta la extension: $ext" >&2; exit 1; }; \
    done; \
    php -m | grep -qi 'Zend OPcache'

# php.ini de producción + ajustes propios (OPcache, seguridad, FPM)
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY --from=docker-config php/app.ini     /usr/local/etc/php/conf.d/zz-app.ini
COPY --from=docker-config php/opcache.ini /usr/local/etc/php/conf.d/zz-opcache.ini
COPY --from=docker-config php/www.conf    /usr/local/etc/php-fpm.d/zz-app.conf

# -----------------------------------------------------------------------------
# 2) VENDOR: dependencias de Composer (sin dev) en etapa de build
# -----------------------------------------------------------------------------
FROM base AS vendor
COPY --from=composer-bin /usr/bin/composer /usr/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_CACHE_DIR=/tmp/composer-cache

# Primero SOLO los manifiestos: esta capa (la más lenta) se reutiliza
# mientras composer.json / composer.lock no cambien.
COPY composer.json composer.lock ./
RUN --mount=type=cache,target=/tmp/composer-cache \
    composer install \
      --no-dev --no-interaction --no-progress --prefer-dist \
      --no-scripts --no-autoloader

# Luego el código, para generar el autoloader optimizado (classmap)
COPY . .
RUN composer dump-autoload --no-dev --optimize --no-interaction --no-scripts

# -----------------------------------------------------------------------------
# 3) TEST (opcional, semana 16): PHPUnit/Pest dentro del pipeline de build
# -----------------------------------------------------------------------------
FROM vendor AS test
ENV APP_ENV=testing
RUN --mount=type=cache,target=/tmp/composer-cache \
    composer install --no-interaction --no-progress --prefer-dist --no-scripts \
 && cp -n .env.example .env \
 && php artisan key:generate --force \
 && php artisan test

# -----------------------------------------------------------------------------
# 4) PRODUCTION: imagen final de PHP-FPM
#    Del build solo se copia vendor/. Sin Composer, sin compiladores,
#    sin tests, sin .env, sin .git.
# -----------------------------------------------------------------------------
FROM base AS production

ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr

# Código fuente (filtrado por .dockerignore) + vendor/ desde la etapa de build.
# Propiedad de root: el proceso (www-data) no puede modificar el código.
COPY . .
COPY --from=vendor /var/www/html/vendor ./vendor

RUN set -eux; \
    mkdir -p storage/framework/cache/data storage/framework/sessions \
             storage/framework/views storage/logs bootstrap/cache; \
    php artisan package:discover --ansi; \
    chown -R www-data:www-data storage bootstrap/cache; \
    chmod -R ug+rwX storage bootstrap/cache

COPY --chmod=0755 --from=docker-config entrypoint.sh /usr/local/bin/entrypoint

# Usuario sin privilegios (uid 82 en Alpine)
USER www-data

# PHP-FPM escucha FastCGI en 9000 (solo red interna; Nginx expone el 80)
EXPOSE 9000

HEALTHCHECK --interval=15s --timeout=5s --start-period=20s --retries=3 \
  CMD env -i SCRIPT_NAME=/ping SCRIPT_FILENAME=/ping REQUEST_METHOD=GET \
      cgi-fcgi -bind -connect 127.0.0.1:9000 | grep -q pong || exit 1

ENTRYPOINT ["entrypoint"]
CMD ["php-fpm"]

# -----------------------------------------------------------------------------
# 5) NGINX: proxy inverso. Solo necesita /public (assets estáticos);
#    el PHP se ejecuta en el contenedor `laravel` vía FastCGI.
# -----------------------------------------------------------------------------
FROM nginx:${NGINX_VERSION}-alpine AS nginx
RUN rm -f /etc/nginx/conf.d/default.conf
COPY --from=docker-config nginx/default.conf /etc/nginx/conf.d/laravel.conf
COPY public /var/www/html/public
EXPOSE 80
HEALTHCHECK --interval=15s --timeout=5s --start-period=10s --retries=3 \
  CMD wget -q -O /dev/null http://127.0.0.1/nginx-health || exit 1
