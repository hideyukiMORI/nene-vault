FROM php:8.4-apache AS base

# ── System dependencies ───────────────────────────────────────────────────────
RUN apt-get update \
    && DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends \
        unzip \
        curl \
        libzip-dev \
        libonig-dev \
        default-libmysqlclient-dev \
    && docker-php-ext-install pdo pdo_mysql mbstring zip \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Install Composer (used in init.sh for prod and as a tool reference)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# ── Apache configuration ──────────────────────────────────────────────────────
RUN a2enmod rewrite

RUN printf '<VirtualHost *:8080>\n\
    DocumentRoot /var/www/html/public_html\n\
    <Directory /var/www/html/public_html>\n\
        Options -Indexes +FollowSymLinks\n\
        AllowOverride All\n\
        Require all granted\n\
    </Directory>\n\
    ErrorLog ${APACHE_LOG_DIR}/error.log\n\
    CustomLog ${APACHE_LOG_DIR}/access.log combined\n\
</VirtualHost>\n' > /etc/apache2/sites-available/000-default.conf

RUN sed -i 's/Listen 80/Listen 8080/' /etc/apache2/ports.conf

# Harden the response surface (security assessment 2026-07-13, INFO-1):
# suppress the Apache version/OS banner in the `Server` header and error pages,
# and disable TRACE. Edit Debian's own conf-enabled/security.conf in place so
# our values win (a separately named conf can load before it and be overridden).
# Applies to the container we build for local + the disposable-org demo;
# shared-hosting (HETEML) banners stay host-controlled.
RUN sed -ri 's/^ServerTokens .*/ServerTokens Prod/; s/^ServerSignature .*/ServerSignature Off/; s/^TraceEnable .*/TraceEnable Off/' \
        /etc/apache2/conf-available/security.conf \
    && printf 'expose_php = Off\n' > /usr/local/etc/php/conf.d/zz-hardening.ini

EXPOSE 8080

# ── Development target ────────────────────────────────────────────────────────
# Source is mounted at runtime (docker compose volume mount).
# nene2 is a path-based dependency resolved on the host; the host's vendor/
# directory is mounted read-only. init.sh only runs migrations + seeds.
FROM base AS dev

WORKDIR /var/www/html

COPY docker/init.sh /usr/local/bin/init.sh
RUN chmod +x /usr/local/bin/init.sh

ENV APACHE_RUN_USER=www-data \
    APACHE_RUN_GROUP=www-data \
    APACHE_LOG_DIR=/var/log/apache2

ENTRYPOINT ["/usr/local/bin/init.sh"]

# ── Production target ─────────────────────────────────────────────────────────
# NENE2 is installed from Packagist (#159), so the build context is just this
# repository. Run:
#   docker build --target prod .
FROM base AS prod

WORKDIR /var/www/html

COPY . /var/www/html/

RUN composer install --no-dev --optimize-autoloader --no-interaction \
    && chown -R www-data:www-data /var/www/html

COPY docker/init.sh /usr/local/bin/init.sh
RUN chmod +x /usr/local/bin/init.sh

ENV APACHE_RUN_USER=www-data \
    APACHE_RUN_GROUP=www-data \
    APACHE_LOG_DIR=/var/log/apache2

ENTRYPOINT ["/usr/local/bin/init.sh"]
