FROM rabbitmq:3.7.4-management-alpine

ENV PLUGIN_NAME="rabbitmq_delayed_message_exchange" \
    PLUGIN_DATE="20171201" \
    FOR_RABBIT_VER="3.7.x"

RUN set -xe \
    && apk add --no-cache --virtual .build-deps curl unzip \
    && mkdir /tmp/${PLUGIN_NAME} \
    && curl -L https://dl.bintray.com/rabbitmq/community-plugins/${FOR_RABBIT_VER}/${PLUGIN_NAME}/${PLUGIN_NAME}-${PLUGIN_DATE}-${FOR_RABBIT_VER}.zip -o /tmp/${PLUGIN_NAME}/plugin.zip \
    && unzip /tmp/${PLUGIN_NAME}/plugin.zip -d /tmp/${PLUGIN_NAME} \
    && mv /tmp/${PLUGIN_NAME}/*.ez ${RABBITMQ_HOME:-/opt/rabbitmq}/plugins/ \
    && rabbitmq-plugins enable ${PLUGIN_NAME} \
    && rm -Rfv /tmp/${PLUGIN_NAME} \
    && apk del .build-deps \
    && rabbitmq-plugins list
