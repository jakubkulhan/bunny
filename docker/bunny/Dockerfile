FROM php:8.2-cli

RUN apt-get update \
    && apt-get dist-upgrade -y \
    && apt-get install -y \
        git \
        iputils-ping \
        vim \
        zip

RUN docker-php-ext-configure pcntl --enable-pcntl \
    && docker-php-ext-install pcntl

RUN pecl install xdebug \
    && docker-php-ext-enable xdebug
RUN echo "xdebug.mode=coverage" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

RUN curl --silent --show-error https://getcomposer.org/installer | php
RUN mv composer.phar /usr/local/bin/composer

RUN useradd -ms /bin/bash bunny

RUN mkdir -p /opt/bunny
RUN chown -R bunny:bunny /opt/bunny

VOLUME ["/opt/bunny"]

USER bunny
WORKDIR /opt/bunny

CMD ["/usr/bin/tail", "-f", "/dev/null"]
