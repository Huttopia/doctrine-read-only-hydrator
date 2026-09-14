ARG PHP_VERSION=8.5

FROM php:${PHP_VERSION}-cli-alpine

ARG UID=1000
ARG GID=1000

# git et unzip : composer s'en sert pour récupérer et extraire les paquets
RUN apk add --update --no-cache bash git unzip

RUN addgroup -S php -g $GID \
    && adduser -S -G php -u $UID php

ENV COMPOSER_HOME=/tmp/.composer

# le cache composer est un volume nommé : il doit appartenir à php, pas à root
RUN mkdir -p $COMPOSER_HOME && chown php:php $COMPOSER_HOME

COPY --from=composer:2.9 /usr/bin/composer /usr/bin/composer
