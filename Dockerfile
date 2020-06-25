FROM php:7.2.23-alpine

ENV \
    # <https://github.com/alanxz/rabbitmq-c>
    RABBITMQ_VERSION="0.10.0" \
    # ext-amqp <https://github.com/pdezwart/php-amqp>
    PHP_AMQP_VERSION="1.10.2" \
    COMPOSER_ALLOW_SUPERUSER="1" \
    COMPOSER_HOME="/tmp/composer"

COPY --from=composer:1.10.7 /usr/bin/composer /usr/bin/composer

RUN set -x \
    && apk add --no-cache binutils git \
    && apk add --no-cache --virtual .build-deps \
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
        gcc 1>/dev/null \
    # workaround for rabbitmq linking issue
    && ln -s /usr/lib /usr/local/lib64 \
    # install xdebug (for testing with code coverage), but do not enable it
    && pecl install xdebug-2.9.1 1>/dev/null \
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
    && apk del .build-deps \
    && mkdir /src ${COMPOSER_HOME} \
    && composer global require 'hirak/prestissimo' --no-interaction --no-suggest --prefer-dist \
    && ln -s /usr/bin/composer /usr/bin/c \
    && chmod -R 777 ${COMPOSER_HOME} \
    && composer --version \
    && php -v \
    && php -m

WORKDIR /src
