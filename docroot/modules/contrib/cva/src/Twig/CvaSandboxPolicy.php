<?php

declare(strict_types=1);

namespace Drupal\cva\Twig;

use Drupal\Core\Template\TwigSandboxPolicy;

/**
 * Decorate the Twig sandbox security policy to allow CVA methods.
 *
 * This extends Drupal's default security policy to allow calling methods
 * on Twig\Extra\Html\Cva objects returned by the html_cva function.
 */
final class CvaSandboxPolicy extends TwigSandboxPolicy {

  /**
   * {@inheritdoc}
   */
  public function __construct() {
    parent::__construct();
    $this->allowed_classes['Twig\Extra\Html\Cva'] = TRUE;
  }

}

