FROM php:7.4-cli

ARG COMPOSER_FLAGS="--prefer-dist --no-interaction"
ARG DEBIAN_FRONTEND=noninteractive
ENV COMPOSER_ALLOW_SUPERUSER 1
ENV COMPOSER_PROCESS_TIMEOUT 3600

WORKDIR /code/

COPY docker/php-prod.ini /usr/local/etc/php/php.ini

RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        locales \
        unzip \
        libaio1 \
	&& rm -r /var/lib/apt/lists/* \
	&& sed -i 's/^# *\(en_US.UTF-8\)/\1/' /etc/locale.gen \
	&& locale-gen

ENV LANGUAGE=en_US.UTF-8
ENV LANG=en_US.UTF-8
ENV LC_ALL=en_US.UTF-8

# Oracle instantclient
RUN mkdir /opt/oracle

COPY docker/oracle/instantclient-basiclite-linux.x64-12.2.0.1.0.zip /opt/oracle/
COPY docker/oracle/instantclient-sdk-linux.x64-12.2.0.1.0.zip /opt/oracle/

RUN unzip /opt/oracle/instantclient-basiclite-linux.x64-12.2.0.1.0.zip -d /opt/oracle \
    && unzip /opt/oracle/instantclient-sdk-linux.x64-12.2.0.1.0.zip -d /opt/oracle \
    && ln -s /opt/oracle/instantclient_12_2/libclntsh.so.12.1 /opt/oracle/instantclient_12_2/libclntsh.so \
    && ln -s /opt/oracle/instantclient_12_2/libclntshcore.so.12.1 /opt/oracle/instantclient_12_2/libclntshcore.so \
    && ln -s /opt/oracle/instantclient_12_2/libocci.so.12.1 /opt/oracle/instantclient_12_2/libocci.so \
    && rm -rf /opt/oracle/*.zip

RUN export LD_LIBRARY_PATH=/opt/oracle/instantclient_12_2/ php

# Install Oracle extensions
RUN docker-php-ext-configure oci8 --with-oci8=instantclient,/opt/oracle/instantclient_12_2,12.1
RUN docker-php-ext-install oci8
RUN echo /opt/oracle/instantclient_12_2 > /etc/ld.so.conf.d/oracle-insantclient.conf
RUN ldconfig

# Install composer
WORKDIR /root

COPY docker/composer-install.sh /tmp/composer-install.sh
RUN chmod +x /tmp/composer-install.sh \
	&& /tmp/composer-install.sh

## Composer - deps always cached unless changed
WORKDIR /code

# First copy only composer files
COPY composer.* /code/

# Download dependencies, but don't run scripts or init autoloaders as the app is missing
RUN composer install $COMPOSER_FLAGS --no-scripts --no-autoloader

# Copy rest of the app
COPY . /code/

# Run normal composer - all deps are cached already
RUN composer install $COMPOSER_FLAGS

CMD ["php", "/code/src/run.php"]
