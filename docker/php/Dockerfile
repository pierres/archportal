FROM php:8-fpm-alpine

RUN apk add libarchive git

COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/bin/
RUN install-php-extensions opcache ffi apcu intl pdo_mysql
RUN echo 'ffi.enable = true' >> $PHP_INI_DIR/conf.d/docker-php-ext-ffi.ini
RUN echo 'arg_separator.input=";&"' > $PHP_INI_DIR/conf.d/legacy.ini

COPY --from=composer /usr/bin/composer /usr/bin/composer

ADD https://github.com/maxmind/MaxMind-DB/raw/main/test-data/GeoIP2-Country-Test.mmdb /usr/share/GeoIP/GeoLite2-Country.mmdb
RUN chmod 644 /usr/share/GeoIP/GeoLite2-Country.mmdb
