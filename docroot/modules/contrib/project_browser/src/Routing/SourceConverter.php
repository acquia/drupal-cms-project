<?php

namespace Drupal\project_browser\Routing;

use Drupal\Core\ParamConverter\ParamConverterInterface;
use Drupal\project_browser\Plugin\ProjectBrowserSourceInterface;
use Drupal\project_browser\Plugin\ProjectBrowserSourceManager;
use Symfony\Component\Routing\Route;

/**
 * Loads the source plugin if it is in the list of enabled sources.
 *
 * @internal
 *   This is an internal part of Project Browser and may be changed or removed
 *   at any time. It should not be used by external code.
 */
final class SourceConverter implements ParamConverterInterface {

  public function __construct(
    private readonly ProjectBrowserSourceManager $sourceManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function convert($value, $definition, $name, array $defaults): ?ProjectBrowserSourceInterface {
    if ($value) {
      return $this->sourceManager->getAllEnabledSources()[$value] ?? NULL;
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function applies($definition, $name, Route $route): bool {
    return !empty($definition['project_browser.source']);
  }

}
