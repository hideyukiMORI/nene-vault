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
# Expects the entire monorepo context (../) including ../NENE2 so composer can
# resolve the path dependency. Run:
#   docker build --target prod -f nene-vault/Dockerfile ..
FROM base AS prod

WORKDIR /var/www/html

# Copy the nene-vault project and NENE2 sibling (required by composer path repo)
COPY nene-vault/ /var/www/html/
COPY NENE2/ /NENE2/

RUN composer install --no-dev --optimize-autoloader --no-interaction \
    && chown -R www-data:www-data /var/www/html \
    && rm -rf /NENE2

COPY nene-vault/docker/init.sh /usr/local/bin/init.sh
RUN chmod +x /usr/local/bin/init.sh

ENV APACHE_RUN_USER=www-data \
    APACHE_RUN_GROUP=www-data \
    APACHE_LOG_DIR=/var/log/apache2

ENTRYPOINT ["/usr/local/bin/init.sh"]
