FROM ubuntu:24.04

ENV DEBIAN_FRONTEND=noninteractive
ENV TZ=UTC

# System deps
RUN apt-get update && apt-get install -y --no-install-recommends \
    php8.3-fpm php8.3-sqlite3 php8.3-xml php8.3-mbstring php8.3-curl \
    php8.3-tokenizer php8.3-bcmath php8.3-ctype php8.3-fileinfo \
    nginx supervisor sqlite3 curl unzip ca-certificates \
    && rm -rf /var/lib/apt/lists/*

# Node.js 22 (Ubuntu 24.04 apt only has Node 18, but Vite 7 requires 20+)
RUN curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
    && rm -rf /var/lib/apt/lists/*

# Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

# PHP deps
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-scripts

# Node deps + build
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
RUN npm run build
RUN composer dump-autoload --optimize

# Config
COPY docker/nginx.conf /etc/nginx/sites-available/default
COPY docker/supervisord.conf /etc/supervisord.conf
COPY docker/entrypoint.sh /entrypoint.sh
COPY .env.production /var/www/.env.production

# Fix php-fpm to listen on 127.0.0.1:9000 (Ubuntu uses socket by default)
RUN sed -i 's|^listen = .*|listen = 127.0.0.1:9000|' /etc/php/8.3/fpm/pool.d/www.conf

# Permissions
RUN chmod +x /entrypoint.sh \
    && chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache

# Data volume for SQLite
RUN mkdir -p /data && chown www-data:www-data /data
VOLUME /data

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=5s --start-period=30s --retries=3 \
    CMD curl -f http://localhost:80/health || exit 1

ENTRYPOINT ["/entrypoint.sh"]
