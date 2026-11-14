<?php

namespace Drupal\eca\Token;

/**
 * Interface for Token data providers that supply dynamic token keys.
 *
 * Implement this interface when the available token keys are not statically
 * known and cannot be declared using #[Token] attributes. This applies to
 * data providers whose keys are determined at runtime, such as user-configured
 * token names or context data stacks.
 *
 * The Browser uses this interface as a complement to the #[Token] attribute
 * discovery mechanism: tokens declared via #[Token] are discovered through
 * reflection, while tokens from DynamicDataProviderInterface implementations
 * are discovered by querying getAvailableKeys() at runtime.
 */
interface DynamicDataProviderInterface extends DataProviderInterface {

  /**
   * Returns the list of currently available token keys.
   *
   * @return string[]
   *   An array of token key names that this provider currently has data for.
   */
  public function getAvailableKeys(): array;

}
