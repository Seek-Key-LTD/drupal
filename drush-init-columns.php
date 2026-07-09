<?php
/**
 * Drush 批量创建专栏与作者账号脚本 (通用 JSON 版本)
 * 运行方式: drush php:script drush-init-columns.php
 */

use Drupal\user\Entity\User;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;

$config_file = '/opt/drupal/init_config.json';
if (!file_exists($config_file)) {
  print "⚠️ 配置文件 /opt/drupal/init_config.json 不存在。请先写入配置。\n";
  exit(1);
}

$config = json_decode(file_get_contents($config_file), TRUE);
if (!$config) {
  print "❌ JSON 解析失败，请检查配置文件格式。\n";
  exit(1);
}

// 1. 创建 Columnist 角色（如果不存在）
$role_id = 'columnist';
if (!\Drupal\user\Entity\Role::load($role_id)) {
  $role = \Drupal\user\Entity\Role::create([
    'id' => $role_id,
    'label' => '专栏作家',
  ]);
  $role->grantPermission('create article content');
  $role->grantPermission('edit own article content');
  $role->save();
  print "✓ '专栏作家' 角色创建完成。\n";
}

// 2. 批量创建作者账号
$authors = $config['authors'] ?? [];
$user_entities = [];
foreach ($authors as $username => $email) {
  $existing = user_load_by_name($username);
  if (!$existing) {
    $password = user_password(16);
    $user = User::create([
      'name' => $username,
      'mail' => $email,
      'pass' => $password,
      'status' => 1,
    ]);
    $user->addRole($role_id);
    $user->save();
    print "✓ 账号创建成功: {$username} (密码: {$password})\n";
    $user_entities[$username] = $user;
  } else {
    print "- 账号已存在: {$username}\n";
    $user_entities[$username] = $existing;
  }
}

// 3. 创建 Columns 分类词汇表（如果不存在）
$vocab_id = 'columns';
if (!Vocabulary::load($vocab_id)) {
  $vocabulary = Vocabulary::create([
    'vid' => $vocab_id,
    'name' => '专栏分类',
  ]);
  $vocabulary->save();
  print "✓ '专栏分类' 词汇表创建完成。\n";
}

// 4. 批量创建专栏并绑定作者
$columns_mapping = $config['columns'] ?? [];
foreach ($columns_mapping as $col_name => $allowed_users) {
  $terms = \Drupal::entityTypeManager()
    ->getStorage('taxonomy_term')
    ->loadByProperties(['name' => $col_name, 'vid' => $vocab_id]);
    
  if (empty($terms)) {
    $term = Term::create([
      'name' => $col_name,
      'vid' => $vocab_id,
    ]);
    
    $uids = [];
    foreach ($allowed_users as $username) {
      if (isset($user_entities[$username])) {
        $uids[] = ['target_id' => $user_entities[$username]->id()];
      } else {
        // 如果是已存在的用户，重新加载
        $user_obj = user_load_by_name($username);
        if ($user_obj) {
          $uids[] = ['target_id' => $user_obj->id()];
        }
      }
    }
    
    if ($term->hasField('field_column_authors')) {
      $term->set('field_column_authors', $uids);
    }
    
    $term->save();
    print "✓ 专栏已建立: {$col_name} (特许作者: " . implode(', ', $allowed_users) . ")\n";
  } else {
    print "- 专栏已存在: {$col_name}\n";
  }
}
