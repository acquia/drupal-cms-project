<?php

declare(strict_types=1);

namespace Drupal\cva\Twig;

use Twig\Extra\Html\HtmlExtension;
use Twig\Extension\AbstractExtension;

/**
 * Twig extension that exposes the html_cva function from Twig's HtmlExtension.
 *
 * @see https://twig.symfony.com/doc/3.x/functions/html_cva.html
 */
final class CvaTwigExtension extends AbstractExtension {

  private readonly HtmlExtension $htmlExtension;

  public function __construct() {
    $this->htmlExtension = new HtmlExtension();
  }

  /**
   * {@inheritdoc}
   */
  public function getFunctions(): array {
    $functions = $this->htmlExtension->getFunctions();
    // Filter to only return the html_cva function.
    return array_filter(
      $functions,
      fn($function) => $function->getName() === 'html_cva'
    );
  }

}

