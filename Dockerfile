FROM amazon/aws-cli:2.13.0 AS awscli

ARG AWS_ACCESS_KEY_ID
ARG AWS_SECRET_ACCESS_KEY
ARG AWS_REGION=eu-central-1
ENV AWS_ACCESS_KEY_ID=${AWS_ACCESS_KEY_ID} \
    AWS_SECRET_ACCESS_KEY=${AWS_SECRET_ACCESS_KEY} \
    AWS_DEFAULT_REGION=${AWS_REGION} \
    AWS_REGION=${AWS_REGION}

RUN aws s3 cp \
      s3://keboola-drivers/hive-odbc/clouderahiveodbc_2.8.2.1002-2_amd64.deb \
      /tmp/hive-odbc.deb

FROM php:7.4-cli-bullseye

ARG COMPOSER_FLAGS="--prefer-dist --no-interaction"
ARG DEBIAN_FRONTEND=noninteractive
ENV COMPOSER_ALLOW_SUPERUSER 1
ENV COMPOSER_PROCESS_TIMEOUT 3600

WORKDIR /code/

COPY docker/php/php-prod.ini /usr/local/etc/php/php.ini
COPY docker/php/composer-install.sh /tmp/composer-install.sh

RUN apt-get update && apt-get install -y --no-install-recommends \
        ssh \
        git \
        locales \
        unzip \
        unixodbc \
        unixodbc-dev \
        libiodbc2 \
        libsasl2-dev \
        libsasl2-2 \
        libsasl2-modules \
        libsasl2-modules-db \
        libsasl2-modules-sql \
        libsasl2-modules-gssapi-mit \
        libsasl2-modules-ldap \
	&& rm -r /var/lib/apt/lists/* \
	&& REAL_SO=$(find /usr/lib -name 'libiodbcinst.so.*' | head -n1) \
	&& ln -sf "$REAL_SO" /usr/lib/libiodbcinst.so \
	&& sed -i 's/^# *\(en_US.UTF-8\)/\1/' /etc/locale.gen \
	&& locale-gen \
	&& chmod +x /tmp/composer-install.sh \
	&& /tmp/composer-install.sh

# PHP ODBC
# https://github.com/docker-library/php/issues/103#issuecomment-353674490
RUN set -ex; \
    docker-php-source extract; \
    { \
        echo '# https://github.com/docker-library/php/issues/103#issuecomment-353674490'; \
        echo 'AC_DEFUN([PHP_ALWAYS_SHARED],[])dnl'; \
        echo; \
        cat /usr/src/php/ext/odbc/config.m4; \
    } > temp.m4; \
    mv temp.m4 /usr/src/php/ext/odbc/config.m4; \
    docker-php-ext-configure odbc --with-unixODBC=shared,/usr; \
    docker-php-ext-install odbc; \
    docker-php-source delete

# Cloudera Hive Driver
COPY --from=awscli /tmp/hive-odbc.deb /tmp/hive-odbc.deb
RUN dpkg -i /tmp/hive-odbc.deb || true \
    && apt-get update \
    && apt-get install -f -y \
    && rm -rf /var/lib/apt/lists/* \
    && rm /tmp/hive-odbc.deb \
    && cp /opt/cloudera/hiveodbc/Setup/odbc.ini /etc/odbc.ini \
    && cp /opt/cloudera/hiveodbc/Setup/odbcinst.ini /etc/odbcinst.ini

ENV LANGUAGE=en_US.UTF-8
ENV LANG=en_US.UTF-8
ENV LC_ALL=en_US.UTF-8

## Composer - deps always cached unless changed
# First copy only composer files
COPY composer.* /code/

# Download dependencies, but don't run scripts or init autoloaders as the app is missing
RUN composer install $COMPOSER_FLAGS --no-scripts --no-autoloader

# copy rest of the app
COPY . /code/

# run normal composer - all deps are cached already
RUN composer install $COMPOSER_FLAGS

CMD ["php", "/code/src/run.php"]
