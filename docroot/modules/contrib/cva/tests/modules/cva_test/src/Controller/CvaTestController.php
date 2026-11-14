<?php

declare(strict_types=1);

namespace Drupal\cva_test\Controller;

/**
 * Controller for CVA test routes.
 */
final class CvaTestController {

  /**
   * Renders a component that uses html_cva().
   */
  public function render(): array {
    return [
      'nice' => [
        '#type' => 'component',
        '#component' => 'cva_test:cva',
        '#props' => ['friendliness' => 'nice'],
      ],
      'mean' => [
        '#type' => 'component',
        '#component' => 'cva_test:cva',
        '#props' => ['friendliness' => 'mean'],
      ],
    ];
  }

}
