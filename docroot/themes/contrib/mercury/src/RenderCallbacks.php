<?php

declare(strict_types=1);

namespace Drupal\mercury;

use Drupal\Core\Security\TrustedCallbackInterface;

/**
 * Implements internal callbacks to help with rendering certain SDCs.
 */
final class RenderCallbacks implements TrustedCallbackInterface {

  /**
   * Pre-render callback for SDC elements.
   *
   * @param array $element
   *   The element being rendered.
   *
   * @return array
   *   The modified element.
   */
  public static function preRenderComponent(array $element): array {
    // In the hero-blog component, convert a UNIX timestamp into a string that
    // can be passed to strtotime() by Twig's date filter.
    if ($element['#component'] === 'mercury:hero-blog' && isset($element['#props']['date']) && is_int($element['#props']['date'])) {
      $element['#props']['date'] = date('Y-m-d', $element['#props']['date']);
    }
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks(): array {
    return ['preRenderComponent'];
  }

}
