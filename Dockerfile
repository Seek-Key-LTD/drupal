FROM drupal:latest

# 先装 GMP（oidc 依赖），再跑 composer（需要用 GMP），最后装其余扩展
RUN apt-get update && apt-get install -y libgmp-dev \
    && docker-php-ext-install gmp \
    && rm -rf /var/lib/apt/lists/*

# 更新 Drupal 核心并安装必要的模块：Simple OAuth、Drush、FontAwesome、Redis、SMTP
RUN composer require drupal/core-recommended:^11.4 \
    drupal/core-composer-scaffold:^11.4 \
    drupal/core-project-message:^11.4 \
    drupal/simple_oauth:^6.1 \
    drupal/fontawesome:^3.0 \
    drupal/redis:^1.11 \
    drupal/smtp:^1.4 \
    drupal/gin \
    drupal/gin_toolbar \
    drupal/bootstrap_barrio \
    drupal/oidc:^2.3 \
    drush/drush \
    --update-with-all-dependencies --no-interaction

# 安装其余编译依赖、GD、Redis、APCu、uploadprogress
RUN apt-get update && apt-get install -y     gcc make libc-dev unzip \
    libavif-dev libwebp-dev libjpeg62-turbo-dev libpng-dev libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp --with-avif \
    && docker-php-ext-install -j4 gd \
    && pecl install redis apcu uploadprogress \
    && docker-php-ext-enable redis apcu uploadprogress \
    && rm -rf /tmp/pear /var/lib/apt/lists/*
