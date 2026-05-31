# syntax=docker/dockerfile:1
FROM php:8.2-cli-alpine AS build

RUN apk add --no-cache curl-dev && \
    docker-php-ext-install curl && \
    apk add --no-cache composer

WORKDIR /app

COPY composer.json composer.lock* ./
RUN composer install --no-interaction --prefer-dist --no-progress --no-dev --optimize-autoloader

COPY . .

RUN php -d phar.readonly=0 bin/build-phar.php

FROM php:8.2-cli-alpine

RUN apk add --no-cache curl-dev && \
    docker-php-ext-install curl

COPY --from=build /app/build/eleload.phar /usr/local/bin/eleload

RUN chmod +x /usr/local/bin/eleload

ENTRYPOINT ["eleload"]
CMD ["help"]
