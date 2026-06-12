<?php

declare(strict_types=1);

/**
 * @codeCoverageIgnore
 */

use drupol\PhpCsFixerConfigsDrupal\Config\Drupal8;

$finder = PhpCsFixer\Finder::create()
  ->name('*.module')
  ->name('*.inc')
  ->name('*.install')
  ->name('*.test')
  ->name('*.profile')
  ->name('*.theme')
  ->notPath('*.md')
  ->notPath('*.info.yml')
;

$config = new Drupal8();

$config->setParallelConfig(PhpCsFixer\Runner\Parallel\ParallelConfigFactory::detect());
$config->setFinder($finder);

$rules = [];
$rules = $config->getRules();

// Deprecated rule.
unset($rules['visibility_required']);

$local_rules = [
  'declare_strict_types' => true,
  'blank_line_after_opening_tag' => true,
  'ordered_imports' => true,
  'ordered_interfaces' => true,
  'php_unit_strict' => false,
  'return_assignment' => false,
  'php_unit_test_class_requires_covers' => false,
  'new_expression_parentheses' => ['use_parentheses' => true],
  'php_unit_data_provider_method_order' => true,
  'method_argument_space' => ['on_multiline' => 'ensure_fully_multiline'],
  // 'ordered_class_elements' => ['sort_algorithm' => 'alpha', 'case_sensitive' => false],
  'ordered_class_elements' => ['case_sensitive' => false],
  '@PHP8x3Migration' => false,
  '@PHP8x4Migration' => false,
  'native_function_invocation' => ['include' => ['@internal'], 'scope' => 'all', 'strict' => true],
  'modifier_keywords' => ['elements' => ['const', 'method', 'property']]
];

$rules = \array_merge($rules, $local_rules);

$config->setRules($rules);

return $config;
