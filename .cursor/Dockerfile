FROM ubuntu:24.04

ENV DEBIAN_FRONTEND=noninteractive

# PHP 8.3 (matches composer.json "php": "^8.3") plus the extensions required by
# Laravel / Orchestra Testbench and the Schematic test suite.
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        ca-certificates \
        curl \
        git \
        unzip \
        php8.3-cli \
        php8.3-mbstring \
        php8.3-xml \
        php8.3-curl \
        php8.3-sqlite3 \
        php8.3-bcmath \
        php8.3-intl \
        php8.3-zip \
        php8.3-gd \
    && rm -rf /var/lib/apt/lists/*

# Composer (pinned major version; self-verified installer).
RUN curl -sS https://getcomposer.org/installer -o /tmp/composer-setup.php \
    && php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer \
    && rm -f /tmp/composer-setup.php

USER ubuntu
