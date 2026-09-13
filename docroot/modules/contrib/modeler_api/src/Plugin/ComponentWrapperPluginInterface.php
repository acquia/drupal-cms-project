<?php

namespace Drupal\modeler_api\Plugin;

use Drupal\Component\Plugin\ConfigurableInterface;

/**
 * Interface for component wrapper plugins.
 *
 * Such plugins should only be used for model owner components, that aren't
 * plugins already. That way, they can be wrapped in a plugin and still be
 * handed around the different places.
 */
interface ComponentWrapperPluginInterface extends ConfigurableInterface {

  /**
   * Gets the component type.
   *
   * @return int
   *   The component type.
   */
  public function getType(): int;

}
