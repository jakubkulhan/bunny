FROM php:7.4-cli

RUN apt-get update \
    && apt-get dist-upgrade -y \
    && apt-get install -y \
        git \
        iputils-ping \
        vim \
        zip

RUN pecl install xdebug \
    && docker-php-ext-enable xdebug

RUN curl --silent --show-error https://getcomposer.org/installer | php
RUN mv composer.phar /usr/local/bin/composer

RUN useradd -ms /bin/bash bunny

RUN mkdir -p /opt/bunny
RUN chown -R bunny:bunny /opt/bunny

VOLUME ["/opt/bunny"]

USER bunny
WORKDIR /opt/bunny

CMD ["/usr/bin/tail", "-f", "/dev/null"]
