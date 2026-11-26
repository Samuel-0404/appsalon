# AppSalon - imagen de producción (PHP 8.3 + Apache)
# Construir:  docker compose build      Ejecutar: docker compose up -d

# ---------- Etapa 1: dependencias PHP (Composer) ----------
# Se instala vendor/ en una etapa aparte: la imagen final no lleva Composer ni su caché.
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --prefer-dist \
        --optimize-autoloader --no-scripts --ignore-platform-reqs

# ---------- Etapa 2: aplicación ----------
FROM php:8.3-apache

# Extensiones: pdo_mysql (BD). curl, mbstring y openssl ya vienen en la imagen oficial.
# mod_rewrite: rutas limpias de la API (public/.htaccess). mod_headers: cabeceras de seguridad.
RUN docker-php-ext-install pdo_mysql \
 && a2enmod rewrite headers \
 && mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY docker/php.ini    $PHP_INI_DIR/conf.d/zz-appsalon.ini
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf

WORKDIR /var/www/html
COPY --chown=www-data:www-data . .
COPY --from=vendor --chown=www-data:www-data /app/vendor ./vendor

# La configuración del contenedor se toma de variables de entorno (config.example.php las lee).
# config/config.php local NO entra a la imagen (.dockerignore): así no se filtran credenciales.
RUN cp config/config.example.php config/config.php \
 && mkdir -p docs/correos_enviados \
 && chown -R www-data:www-data docs/correos_enviados

EXPOSE 80
HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 \
  CMD curl -fsS http://localhost/index.php?url=servicios/index -o /dev/null || exit 1
