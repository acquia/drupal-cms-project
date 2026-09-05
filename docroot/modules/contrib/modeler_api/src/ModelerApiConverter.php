<?php

namespace Drupal\modeler_api;

use Drupal\Core\ParamConverter\ParamConverterInterface;
use Symfony\Component\Routing\Route;

/**
 * Converts parameters for upcasting entity IDs to full objects.
 */
class ModelerApiConverter implements ParamConverterInterface {

  /**
   * {@inheritdoc}
   */
  public function convert($value, $definition, $name, array $defaults) {
    if (isset($definition['type']) && isset($defaults[$definition['type']])) {
      return $defaults[$definition['type']];
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function applies($definition, $name, Route $route): bool {
    return (!empty($definition['provider']) && $definition['provider'] === 'modeler_api');
  }

}
