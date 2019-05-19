FROM composer:1.8.5 AS composer

FROM php:7.1.3-alpine

ENV \
    # <https://github.com/alanxz/rabbitmq-c>
    RABBITMQ_VERSION="0.9.0" \
    # ext-amqp <https://github.com/pdezwart/php-amqp>
    PHP_AMQP_VERSION="1.9.4" \
    COMPOSER_ALLOW_SUPERUSER="1" \
    COMPOSER_HOME="/tmp/composer"

COPY --from=composer /usr/bin/composer /usr/bin/composer

ENV RUNTIME_DEPS \
    openssl-dev \
    autoconf \
    pkgconf \
    cmake \
    unzip \
    curl \
    make \
    file \
    re2c \
    g++ \
    gcc

ENV PERMANENT_DEPS \
    # fix xdebug error "/usr/local/bin/docker-php-ext-enable: line 83: nm: not found"
    binutils \
    git

RUN echo $PATH && exit

RUN set -xe \
    && apk add --no-cache ${PERMANENT_DEPS} \
    && apk add --no-cache --virtual .build-deps ${RUNTIME_DEPS} \
    # workaround for rabbitmq linking issue
    && ln -s /usr/lib /usr/local/lib64 \
    # this c-library is required for 'php-amqp'
    && ( git clone --branch v${RABBITMQ_VERSION} https://github.com/alanxz/rabbitmq-c.git /tmp/rabbitmq \
        && cd /tmp/rabbitmq \
        && mkdir build && cd build \
        && cmake .. \
        && cmake --build . --target install ) \
        && rm -Rf /tmp/rabbitmq \
    && ( git clone --branch v${PHP_AMQP_VERSION} https://github.com/pdezwart/php-amqp.git /tmp/php-amqp \
        && cd /tmp/php-amqp \
        && phpize --clean \
        && phpize \
        && ./configure \
        && make \
        && make install \
        && echo 'extension=amqp.so' > /usr/local/etc/php/conf.d/amqp.ini ) \
        && rm -Rf /tmp/php-amqp \
    # install xdebug (for testing with code coverage), but not enable it
    && pecl install xdebug-2.7.2 \
    && apk del .build-deps \
    && mkdir /src ${COMPOSER_HOME} \
    && composer global require 'hirak/prestissimo' --no-interaction --no-suggest --prefer-dist \
    && ln -s /usr/bin/composer /usr/bin/c \
    && chmod -R 777 ${COMPOSER_HOME} \
    && composer --version \
    && php -v \
    && php -m

WORKDIR /src

VOLUME ["/src"]
