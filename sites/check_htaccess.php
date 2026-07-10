<?php
$htaccess_path = \Drupal::service('file_system')->realpath('public://') . '/.htaccess';
$exists = file_exists($htaccess_path);
$content = $exists ? file_get_contents($htaccess_path) : 'NOT FOUND';
$has_marker = strpos($content, 'Drupal_Security_Do_Not_Remove_See_SA_2006_006') !== false;
print 'Path: ' . $htaccess_path . PHP_EOL;
print 'Exists: ' . ($exists ? 'YES' : 'NO') . PHP_EOL;
print 'Has security marker: ' . ($has_marker ? 'YES' : 'NO') . PHP_EOL;
print 'Writeable: ' . (is_writable(dirname($htaccess_path)) ? 'YES' : 'NO') . PHP_EOL;
