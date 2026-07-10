<?php
// Check what Drupal's own status check sees
$requirements = \Drupal::moduleHandler()->invokeAll('requirements', ['runtime']);
foreach ($requirements as $key => $req) {
  if (isset($req['title']) && (stripos($req['title'], 'file') !== false || stripos($req['title'], 'htaccess') !== false || stripos($req['title'], 'public') !== false)) {
    $severity = isset($req['severity']) ? $req['severity'] : 0;
    print "[$key] severity=$severity title=" . (string)$req['title'] . PHP_EOL;
    if (isset($req['description'])) {
      print "  desc=" . (string)$req['description'] . PHP_EOL;
    }
  }
}
