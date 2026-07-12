<?php
/**
 * Drush: 删除旧 consumer → 用固定 secret 重建 → 输出 .env 格式
 * 运行: drush php:script drush-regenerate-consumers.php
 */

$users = \Drupal::entityTypeManager()->getStorage('user')->loadMultiple();
$storage = \Drupal::entityTypeManager()->getStorage('consumer');

// 删除所有旧 consumer（保留 ben uid=1 的）
$old = $storage->loadMultiple();
foreach ($old as $c) {
  if (str_starts_with($c->get('client_id')->value ?? '', 'agent-')) {
    $c->delete();
  }
}

// 重建
$env = [];
foreach ($users as $u) {
  if ($u->id() == 0 || $u->id() == 1) continue;
  $name = $u->getAccountName();
  $client_id = 'agent-' . $name;
  $secret = bin2hex(random_bytes(16));

  $consumer = $storage->create([
    'label'       => 'Agent: ' . $name,
    'description' => "API consumer for {$name}",
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

  $key = 'DRUPAL_CLIENT_ID_' . strtoupper($name);
  $env[] = "{$key}={$client_id}";
  $key = 'DRUPAL_CLIENT_SECRET_' . strtoupper($name);
  $env[] = "{$key}={$secret}";
  echo "  {$name}: {$client_id} / {$secret}\n";
}

// 写入 /tmp 供外部读取
file_put_contents('/tmp/agent-drupal-credentials.env', implode("\n", $env) . "\n");
echo "\nWrote /tmp/agent-drupal-credentials.env\n";
