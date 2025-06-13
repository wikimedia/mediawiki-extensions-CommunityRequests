<?php

$cfg = require __DIR__ . '/../vendor/mediawiki/mediawiki-phan-config/src/config.php';

// This value should match the PHP version specified in composer.json and PHPVersionCheck.php.
$cfg['minimum_target_php_version'] = '8.1.0';

return $cfg;
