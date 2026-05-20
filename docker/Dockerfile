FROM php:8.4-cli

ARG USER_ID=1000
ARG GROUP_ID=1000

ENV DEBIAN_FRONTEND=noninteractive

RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        unzip \
        curl \
        ca-certificates \
        libzip-dev \
        libonig-dev \
        libicu-dev \
        libpng-dev \
        libxml2-dev \
        default-mysql-client \
    && docker-php-ext-install \
        pdo_mysql \
        mbstring \
        bcmath \
        intl \
        zip \
        pcntl \
        opcache \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN groupadd --gid ${GROUP_ID} app \
    && useradd --uid ${USER_ID} --gid app --shell /bin/bash --create-home app

WORKDIR /var/www/html

COPY --chown=app:app . /var/www/html

USER app

EXPOSE 8000

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
