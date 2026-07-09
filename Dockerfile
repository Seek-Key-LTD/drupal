FROM drupal:latest

# 更新 Drupal 核心并安装必要的模块：Simple OAuth、Drush、FontAwesome、Redis
RUN composer require drupal/core-recommended:^11.4     drupal/core-composer-scaffold:^11.4     drupal/core-project-message:^11.4     drupal/simple_oauth:^6.1     drupal/fontawesome:^3.0     drupal/redis:^1.11     drush/drush     --update-with-all-dependencies --no-interaction

# 安装依赖并编译支持 AVIF 的 GD 库、Redis、APCu 和 uploadprogress 扩展
RUN apt-get update && apt-get install -y     gcc make libc-dev unzip     libavif-dev libwebp-dev libjpeg62-turbo-dev libpng-dev libfreetype6-dev     && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp --with-avif     && docker-php-ext-install -j4 gd     && pecl install redis apcu uploadprogress     && docker-php-ext-enable redis apcu uploadprogress     && rm -rf /tmp/pear /var/lib/apt/lists/*
