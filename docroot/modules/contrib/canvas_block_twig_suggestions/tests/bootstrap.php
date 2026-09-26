<?php

/**
 * @file
 * Bootstrap file for canvas_block_twig_suggestions unit tests.
 *
 * Lightweight alternative to Drupal core's test bootstrap. Registers the
 * autoloader and the namespaces needed for unit tests without requiring
 * Behat/Mink or a full Drupal kernel.
 */

declare(strict_types=1);

// Load the Composer autoloader.
$autoloader = require __DIR__ . '/../../../../../vendor/autoload.php';

// Register core test classes (Drupal\Tests\UnitTestCase etc.).
$autoloader->add('Drupal\\Tests', __DIR__ . '/../../../../core/tests');
$autoloader->add('Drupal\\TestTools', __DIR__ . '/../../../../core/tests');

// Register this module's src and test namespaces.
$autoloader->addPsr4(
  'Drupal\\canvas_block_twig_suggestions\\',
  [__DIR__ . '/../src']
);
$autoloader->addPsr4(
  'Drupal\\Tests\\canvas_block_twig_suggestions\\',
  [__DIR__ . '/src']
);

// Set sane locale settings (matches core bootstrap).
setlocale(LC_ALL, 'C.UTF-8', 'C');
mb_internal_encoding('utf-8');
mb_language('uni');
date_default_timezone_set('Australia/Sydney');
