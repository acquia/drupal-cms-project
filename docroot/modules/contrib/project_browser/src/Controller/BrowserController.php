<?php

namespace Drupal\project_browser\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\project_browser\Plugin\ProjectBrowserSourceInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Defines a controller to provide the Project Browser UI.
 *
 * @internal
 *   This is an internal part of Project Browser and may be changed or removed
 *   at any time. It should not be used by external code.
 */
final class BrowserController extends ControllerBase {

  /**
   * Builds the browse page for a particular source.
   *
   * @param \Drupal\project_browser\Plugin\ProjectBrowserSourceInterface|null $source
   *   The source plugin to query for projects, or NULL to use the configured
   *   default.
   *
   * @return array|\Symfony\Component\HttpFoundation\RedirectResponse
   *   A render array, or a redirect if $source is NULL and there is a default
   *   source configured.
   */
  public function browse(?ProjectBrowserSourceInterface $source = NULL): array|RedirectResponse {
    $source ??= $this->config('project_browser.admin_settings')
      ->get('default_source');

    if (is_string($source)) {
      return $this->redirect('project_browser.browse', ['source' => $source]);
    }
    elseif ($source instanceof ProjectBrowserSourceInterface) {
      return [
        '#type' => 'project_browser',
        '#source' => $source,
      ];
    }
    throw new NotFoundHttpException();
  }

}
