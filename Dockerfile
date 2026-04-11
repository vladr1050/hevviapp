FROM php:8.4-fpm-alpine

# ✅ Определяем APP_MODE в самом начале (build-time аргумент)
ARG APP_MODE=dev

# Установка системных библиотек и зависимостей
RUN apk add --no-cache \
    bash \
    git \
    curl \
    openssl \
    chromium \
    ttf-dejavu \
    icu-dev \
    libzip-dev \
    oniguruma-dev \
    zlib-dev \
    libpng-dev \
    libjpeg-turbo-dev \
    libwebp-dev \
    freetype-dev \
    postgresql-dev \
    shadow \
    unzip \
    make \
    g++ \
    autoconf \
    pkgconf \
    $PHPIZE_DEPS \
    nodejs \
    npm \
    openssh-client \
    linux-headers \
    rabbitmq-c \
    rabbitmq-c-dev \
    openssl-dev

# Сборка падает, если пакет chromium не попал в образ (иначе на проде тихий сбой PDF).
RUN chromium --version

# Установка PHP-расширений
RUN docker-php-ext-install \
    intl \
    pdo \
    pdo_pgsql \
    opcache \
    zip \
    bcmath \
    sockets \
    mbstring

# Установка Redis через PECL
RUN pecl install redis && docker-php-ext-enable redis

# ✅ Установка AMQP через PECL
RUN pecl install amqp && docker-php-ext-enable amqp

# Установка Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Установка Yarn напрямую через npm (без corepack)
RUN npm install -g yarn

# Создание базовой настройки PHP (условно для dev/prod)
RUN mkdir -p /usr/local/log/php && \
    { \
      echo 'short_open_tag = Off'; \
      echo 'expose_php = Off'; \
      echo 'log_errors = On'; \
      echo 'error_log = /proc/self/fd/2'; \
      echo 'upload_tmp_dir = /tmp/'; \
      echo 'allow_url_fopen = On'; \
      echo 'upload_max_filesize = 25M'; \
      echo 'post_max_size = 30M'; \
    } > /usr/local/etc/php/conf.d/php.ini && \
    if [ "$APP_MODE" = "dev" ]; then \
      { \
        echo 'display_errors = On'; \
        echo 'display_startup_errors = On'; \
        echo 'error_reporting = E_ALL'; \
        echo 'realpath_cache_size = 4096k'; \
        echo 'realpath_cache_ttl = 120'; \
        echo 'opcache.enable = 0'; \
        echo 'opcache.enable_cli = 0'; \
        echo 'opcache.validate_timestamps = 1'; \
        echo 'opcache.revalidate_freq = 0'; \
      } >> /usr/local/etc/php/conf.d/php.ini; \
    else \
      { \
        echo 'display_errors = Off'; \
        echo 'realpath_cache_size = 4096k'; \
        echo 'realpath_cache_ttl = 600'; \
        echo 'opcache.enable = 1'; \
        echo 'opcache.memory_consumption = 256'; \
        echo 'opcache.max_accelerated_files = 20000'; \
        echo 'opcache.validate_timestamps = 0'; \
      } >> /usr/local/etc/php/conf.d/php.ini; \
    fi

# Настройка логов php-fpm:
# - access.log -> stdout
# - error_log уже идёт в stderr через php.ini
RUN { \
      echo '[www]'; \
      echo 'access.log = /proc/self/fd/1'; \
      echo 'access.format = "%R - %t \\"%m %f\\" %s"'; \
  } > /usr/local/etc/php-fpm.d/zz-logs.conf

# Настройка прав пользователя
RUN usermod -u 1000 www-data

# Рабочая директория контейнера
WORKDIR /var/www/app

# Настройка SSH для доступа к приватным репозиториям
RUN mkdir -p /root/.ssh && \
    ssh-keyscan github.com >> /root/.ssh/known_hosts

# Копируем composer файлы для кеширования слоев
COPY composer.json composer.lock symfony.lock ./
# Патчи для vendor (composer-patches), иначе install падает: файлы из extra.patches не найдены
COPY patches ./patches/

# Устанавливаем PHP зависимости (условно в зависимости от APP_MODE)
# Без --mount=type=ssh для сборки на сервере; для приватных репо добавь mount локально
RUN if [ "$APP_MODE" = "dev" ]; then \
        composer install --no-scripts --no-autoloader --prefer-dist; \
    else \
        composer install --no-dev --no-scripts --no-autoloader --prefer-dist --optimize-autoloader; \
    fi

# Копируем package.json для кеширования слоев
COPY package.json package-lock.json ./

# Устанавливаем Node.js зависимости
RUN npm install --frozen-lockfile

# Копируем webpack конфиг
COPY webpack.config.js ./

# Копируем tsconfig для сборки frontend
COPY tsconfig.json ./

# Копируем assets для сборки frontend
COPY assets ./assets

# Собираем frontend (dev или production в зависимости от APP_MODE)
RUN if [ "$APP_MODE" = "dev" ]; then npm run dev; else npm run build; fi

# Копируем весь остальной код приложения
COPY . .

# ✅ Устанавливаем ENV переменные из ARG (APP_DEBUG задаётся в compose/runtime)
ENV APP_ENV=${APP_MODE}
ENV APP_DEBUG=1

# Завершаем установку Composer (autoloader и scripts)
RUN if [ "$APP_MODE" = "dev" ]; then \
        composer dump-autoload --optimize; \
    else \
        composer dump-autoload --optimize --classmap-authoritative --no-dev; \
    fi

# COPY . . must not replace vendor/ (see .dockerignore); otherwise autoload_runtime.php disappears.
RUN test -f vendor/autoload_runtime.php

# Symfony assets:install (после копирования кода и composer).
# config/jwt/*.pem в .gitignore — на CI/VPS их нет, без ключей Lexik JWT и консоль падают (exit 255).
# Сгенерированные ключи только для прохождения build; в проде замените volume/секретами.
RUN set -eux; \
    if [ ! -s config/jwt/private.pem ]; then \
      BUILD_PASS="$(openssl rand -hex 32)"; \
      openssl genrsa -aes256 -passout pass:"${BUILD_PASS}" -out config/jwt/private.pem 4096; \
      openssl rsa -pubout -passin pass:"${BUILD_PASS}" -in config/jwt/private.pem -out config/jwt/public.pem; \
      export JWT_PASSPHRASE="${BUILD_PASS}"; \
    fi; \
    APP_SECRET="${APP_SECRET:-0123456789abcdef0123456789abcdef}" \
    DATABASE_URL="${DATABASE_URL:-postgresql://app:build@127.0.0.1:5432/app?serverVersion=16&charset=utf8}" \
    php bin/console assets:install --symlink --relative public --no-interaction --env="${APP_ENV}" --no-debug

# Создаем необходимые директории с правильными правами
RUN mkdir -p var/cache var/log var/invoices public/build && \
    chown -R www-data:www-data var public

# Копируем entrypoint.sh в контейнер
COPY entrypoint.sh /usr/local/bin/entrypoint.sh

# Делаем его исполняемым
RUN chmod +x /usr/local/bin/entrypoint.sh

# Открываем порт
EXPOSE 9000

# Точка входа
ENTRYPOINT ["bash", "/usr/local/bin/entrypoint.sh"]
