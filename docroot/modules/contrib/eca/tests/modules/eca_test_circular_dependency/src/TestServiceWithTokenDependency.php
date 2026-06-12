<?php

namespace Drupal\eca_test_circular_dependency;

use Drupal\Core\Utility\Token;

/**
 * Test service that injects token service to test for circular dependencies.
 *
 * This service is used in tests to verify that the token service can be
 * properly injected without causing circular dependency errors during
 * container compilation.
 */
class TestServiceWithTokenDependency {

  /**
   * Constructs a new TestServiceWithTokenDependency object.
   *
   * @param \Drupal\Core\Utility\Token $token
   *   The token service.
   */
  public function __construct(
    protected Token $token,
  ) {}

  /**
   * Uses the token service to replace tokens.
   *
   * @param string $text
   *   The text containing tokens.
   *
   * @return string
   *   The text with tokens replaced.
   */
  public function replaceTokens(string $text): string {
    return $this->token->replace($text);
  }

}
