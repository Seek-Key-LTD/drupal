<?php
/**
 * Drush 脚本：为所有 agent 创建 Simple OAuth consumer (client_credentials)
 * 运行: drush php:script drush-init-consumers.php
 *
 * 前置条件：
 *   - 用户已通过 drush-init-columns.php 创建
 *   - Drupal role "columnist" 存在
 *   - oauth2_scope "columnist" 存在
 */

$users = \Drupal::entityTypeManager()->getStorage('user')->loadMultiple();
$storage = \Drupal::entityTypeManager()->getStorage('consumer');
$results = [];

foreach ($users as $u) {
  // Skip uid=0 (anonymous) and uid=1 (ben/admin — already has token)
  if ($u->id() == 0 || $u->id() == 1) continue;

  $name = $u->getAccountName();
  $client_id = 'agent-' . $name;

  // Check existing
  $existing = $storage->loadByProperties(['client_id' => $client_id]);
  if (!empty($existing)) {
    $c = reset($existing);
    echo "- {$name}: 已存在 (client_id={$client_id})\n";
    $results[] = [
      'name'     => $name,
      'client_id' => $client_id,
      'secret'    => '(already exists)',
    ];
    continue;
  }

  $secret = bin2hex(random_bytes(16));
  $consumer = $storage->create([
    'label'       => 'Agent: ' . $name,
    'description' => "API consumer for {$name} ({$u->getEmail()})",
    'client_id'   => $client_id,
    'secret'      => $secret,
    'grant_types' => ['client_credentials'],
    'confidential' => TRUE,
    'scopes'      => ['columnist'],
    'user_id'     => $u->id(),
    'access_token_expiration' => 3600,
    'automatic_authorization' => TRUE,
  ]);
  $consumer->save();

  echo "+ {$name}: client_id={$client_id} secret={$secret}\n";
  $results[] = [
    'name'     => $name,
    'client_id' => $client_id,
    'secret'    => $secret,
  ];
}

echo "\n=== DRUPAL_CLIENT_ID / DRUPAL_CLIENT_SECRET ===\n";
foreach ($results as $r) {
  echo "{$r['name']}: {$r['client_id']} / {$r['secret']}\n";
}
