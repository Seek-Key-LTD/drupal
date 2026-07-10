<?php
$storage = \Drupal::entityTypeManager()->getStorage('oauth2_scope');
$existing = $storage->load('columnist');
if ($existing) {
  $existing->delete();
}

$scope = $storage->create([
  'name' => 'columnist',
  'description' => 'Columnist Access',
  'granularity_id' => 'role',
  'granularity_configuration' => [
    'role' => 'columnist',
  ],
  'grant_types' => [
    'client_credentials' => [
      'status' => TRUE,
    ],
  ],
]);
$scope->save();
print "✓ oauth2_scope 'columnist' recreated with correct single role key.\n";
