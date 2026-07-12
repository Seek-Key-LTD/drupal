<?php
/**
 * Drush 初始化 OpenID Connect Client (OIDC) 脚本
 * 运行方式: drush php:script drush-init-oidc.php
 *
 * 前置条件：
 *   1. Dockerfile 已安装 drupal/oidc:^2.3 + ext-gmp
 *   2. settings.php 已配置 reverse_proxy（HTTPS 透传）
 *   3. Authentik OIDC Provider 已创建
 *      Client ID:     drupal-oidc-client
 *      Client Secret: drupal-secret-2026-seekkey
 *      Issuer:        https://authentik.capitaltrain.cn/application/o/drupal/
 */

// === 配置区 =============================================
$realm_id   = 'authentik';
$realm_name = 'Authentik';
$config_url = 'https://authentik.capitaltrain.cn/application/o/drupal/.well-known/openid-configuration';
$client_id     = 'drupal-oidc-client';
$client_secret = 'drupal-secret-2026-seekkey';
$scopes        = ['profile', 'email'];
// ========================================================

// 1. 启用 oidc 模块（依赖 externalauth）
if (!\Drupal::moduleHandler()->moduleExists('oidc')) {
  \Drupal::service('module_installer')->install(['oidc', 'externalauth']);
  print "✓ oidc / externalauth 模块已启用\n";
} else {
  print "- oidc 模块已启用\n";
}

// 2. 写入 realm 配置到 oidc.realm.generic.{realm_id}
$config = \Drupal::configFactory()->getEditable("oidc.realm.generic.{$realm_id}");
$config->set('name', $realm_name);
$config->set('config_url', $config_url);
$config->set('client_id', $client_id);
$config->set('client_secret', $client_secret);
$config->set('scopes', $scopes);
$config->set('request_userinfo', TRUE);
$config->set('id_claim', 'sub');
$config->set('username_claim', 'preferred_username');
$config->set('email_claim', 'email');
$config->set('given_name_claim', 'given_name');
$config->set('family_name_claim', 'family_name');
$config->set('auth_only', FALSE);
$config->set('display_name_format', '[user:account-name]');
$config->save();
print "✓ Realm 配置已创建: {$realm_id} ({$realm_name})\n";

// 3. 注册到 generic_realms 列表
$settings = \Drupal::configFactory()->getEditable('oidc.settings');
$realms = $settings->get('generic_realms') ?: [];
if (!in_array($realm_id, $realms)) {
  $realms[] = $realm_id;
  $realms = array_values(array_unique($realms));
  $settings->set('generic_realms', $realms)->save();
  print "✓ Realm {$realm_id} 已注册到 generic_realms\n";
} else {
  print "- Realm {$realm_id} 已存在 generic_realms\n";
}

// 4. 清理缓存
drupal_flush_all_caches();
print "✓ 缓存已清理\n";

// 5. 验证
print "\n--- 验证 ---\n";
$defs = \Drupal::service('plugin.manager.openid_connect_realm')->getDefinitions();
foreach ($defs as $id => $def) {
  $name = $def['name'] ?? 'unnamed';
  print "  {$id}: {$name}\n";
}
print "✓ OIDC 初始化完成\n";
