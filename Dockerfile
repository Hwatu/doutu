FROM php:8.2-cli

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libfreetype6-dev \
        libjpeg62-turbo-dev \
        libpng-dev \
        libwebp-dev \
        fonts-wqy-zenhei \
        imagemagick \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" gd \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

# 复制文件
COPY . .

# 创建目录和设置权限
RUN set -eux; \
    mkdir -p storage/fonts storage/configs storage/output storage/cache; \
    chown -R www-data:www-data /var/www/html; \
    chmod 755 public/index.php font.html upload.html; \
    chmod 755 -R storage; \
    if [ -f wxid.txt ]; then chmod 666 wxid.txt; fi

EXPOSE 80

# 使用 PHP 内置服务器
CMD ["php", "-S", "0.0.0.0:80", "-t", "/var/www/html/public"]
