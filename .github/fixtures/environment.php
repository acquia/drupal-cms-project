<?php

use Acquia\Drupal\RecommendedSettings\Helpers\EnvironmentDetector;

include_once __DIR__ . '/../../vendor/autoload.php';

$foo = EnvironmentDetector::getEnvironments();
print_r($foo);