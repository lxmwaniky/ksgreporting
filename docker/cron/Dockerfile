FROM php:8.2-cli

# Install PHP extensions + cron daemon
RUN apt-get update && apt-get install -y \
    libpq-dev \
    libonig-dev \
    libxml2-dev \
    cron \
    && docker-php-ext-install \
        pdo \
        pdo_pgsql \
        mbstring \
        xml \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock* ./
RUN composer install --no-dev --optimize-autoloader --no-interaction

COPY . .

# Create log directory for cron output
RUN mkdir -p /var/log/ksg-cron

# Install the crontab
COPY docker/cron/crontab /etc/cron.d/ksg
RUN chmod 0644 /etc/cron.d/ksg

CMD ["cron", "-f"]
