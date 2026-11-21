<?php

/**
 * @file
 * Allows for specific PHPStan ignores for different versions of Drupal core.
 */

declare(strict_types=1);

use Composer\InstalledVersions;
use Composer\Semver\VersionParser;

$includes = [];
if (InstalledVersions::satisfies(new VersionParser(), 'drupal/core', '^11')) {
  $includes[] = 'phpstan-baseline-d11.neon';
}

$config = [];
$config['includes'] = $includes;
return $config;
