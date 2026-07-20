#!/bin/sh
set -e

# 如果 settings.php 不存在或为空，用默认模板生成
if [ ! -f /opt/drupal/web/sites/default/settings.php ] || [ ! -s /opt/drupal/web/sites/default/settings.php ]; then
  cp /opt/drupal/web/sites/default/default.settings.php /opt/drupal/web/sites/default/settings.php
  cat >> /opt/drupal/web/sites/default/settings.php << 'SETTINGS'

$databases['default']['default'] = [
  'database' => getenv('DB_NAME') ?: 'drupal',
  'username' => getenv('DB_USER') ?: 'admin',
  'password' => getenv('DB_PASS') ?: '',
  'host' => getenv('DB_HOST') ?: '127.0.0.1',
  'port' => getenv('DB_PORT') ?: '6033',
  'driver' => 'mysql',
  'prefix' => 'drupal9_',
];

$settings['hash_salt'] = getenv('DRUPAL_HASH_SALT') ?: 'default_salt_change_me';

$settings['trusted_host_patterns'] = ['^drupal\.seekkey\.eu\.org$', '^localhost$', '^127\.0\.0\.1$'];
$settings['config_sync_directory'] = '/opt/drupal/config/sync';
$settings['enable_html5_validation'] = false;

if (getenv('REDIS_HOST')) {
  $settings['redis.connection']['host'] = getenv('REDIS_HOST');
  $settings['redis.connection']['port'] = getenv('REDIS_PORT') ?: 6379;
  $settings['cache']['default'] = 'cache.backend.redis';
}
SETTINGS
fi

exec apache2-foreground
