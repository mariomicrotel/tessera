FROM php:8.4-cli-bookworm

# System dependencies
RUN apt-get update && apt-get install -y \
    git curl zip unzip sqlite3 \
    libzip-dev libpng-dev libonig-dev libxml2-dev libsqlite3-dev \
    && docker-php-ext-install \
        pdo pdo_sqlite pdo_mysql mbstring xml zip bcmath gd fileinfo \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Node.js 22 LTS
RUN curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y nodejs \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

# Aumenta memory_limit per DomPDF e per i test (default PHP CLI = 128M, insufficiente per PDF)
RUN echo "memory_limit = 512M" > /usr/local/etc/php/conf.d/memory.ini

EXPOSE 8000
