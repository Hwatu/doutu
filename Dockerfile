FROM php:8.2-cli

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libfreetype6-dev \
        libjpeg62-turbo-dev \
        libpng-dev \
        libwebp-dev \
        fonts-wqy-zenhei \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" gd \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

COPY . .

RUN set -eux; \
    mkdir -p fonts configs output img; \
    chown -R www-data:www-data /var/www/html; \
    chmod 755 index.php font.html upload.html; \
    chmod 777 fonts configs output img; \
    if [ -f wxid.txt ]; then chmod 666 wxid.txt; fi

EXPOSE 80

CMD ["apache2-foreground"]
