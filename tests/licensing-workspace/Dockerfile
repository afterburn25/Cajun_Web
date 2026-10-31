FROM php:8.4-cli
RUN apt-get update -qq && apt-get install -y --no-install-recommends libzip-dev && docker-php-ext-install pdo_mysql zip && rm -rf /var/lib/apt/lists/*
WORKDIR /workspace
