<?php

namespace Drupal\project_browser\Routing;

use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\project_browser\Controller\InstallerController;
use Drupal\project_browser\Form\RecipeForm;
use Symfony\Component\Routing\Route;

/**
 * Provides routes for Project Browser.
 *
 * @internal
 *   This is an internal part of Project Browser and may be changed or removed
 *   at any time. It should not be used by external code.
 */
final class ProjectBrowserRoutes implements ContainerInjectionInterface {

  use AutowireTrait;

  public function __construct(
    private readonly ModuleHandlerInterface $moduleHandler,
  ) {}

  /**
   * Returns an array of route objects.
   *
   * @return \Symfony\Component\Routing\Route[]
   *   An array of route objects.
   */
  public function routes(): array {
    if (!$this->moduleHandler->moduleExists('package_manager')) {
      return [];
    }
    $routes = [];
    $routes['project_browser.stage.begin'] = new Route(
      '/admin/modules/project_browser/install-begin',
      [
        '_controller' => InstallerController::class . '::begin',
        '_title' => 'Create phase',
      ],
      [
        '_permission' => 'administer modules',
        '_custom_access' => InstallerController::class . '::access',
      ]
    );
    $routes['project_browser.stage.require'] = new Route(
      '/admin/modules/project_browser/install-require/{sandbox_id}',
      [
        '_controller' => InstallerController::class . '::require',
        '_title' => 'Require phase',
      ],
      [
        '_permission' => 'administer modules',
        '_custom_access' => InstallerController::class . '::access',
      ],
      [
        'requirements' => [
          '_format' => 'json',
          'sandbox_id' => '\w+',
        ],
        'methods' => ['POST'],
      ]
    );
    $routes['project_browser.stage.apply'] = new Route(
      '/admin/modules/project_browser/install-apply/{sandbox_id}',
      [
        '_controller' => InstallerController::class . '::apply',
        '_title' => 'Apply phase',
      ],
      [
        '_permission' => 'administer modules',
        '_custom_access' => InstallerController::class . '::access',
      ],
      [
        'requirements' => [
          'sandbox_id' => '\w+',
        ],
      ]
    );
    $routes['project_browser.stage.post_apply'] = new Route(
      '/admin/modules/project_browser/install-post_apply/{sandbox_id}',
      [
        '_controller' => InstallerController::class . '::postApply',
        '_title' => 'Post apply phase',
      ],
      [
        '_permission' => 'administer modules',
        '_custom_access' => InstallerController::class . '::access',
      ],
      [
        'requirements' => [
          'sandbox_id' => '\w+',
        ],
      ]
    );
    $routes['project_browser.stage.destroy'] = new Route(
      '/admin/modules/project_browser/install-destroy/{sandbox_id}',
      [
        '_controller' => InstallerController::class . '::destroy',
        '_title' => 'Destroy phase',
      ],
      [
        '_permission' => 'administer modules',
        '_custom_access' => InstallerController::class . '::access',
      ],
      [
        'requirements' => [
          'sandbox_id' => '\w+',
        ],
      ]
    );
    $routes['project_browser.install.unlock'] = new Route(
      '/admin/modules/project_browser/install/unlock',
      [
        '_controller' => InstallerController::class . '::unlock',
        '_title' => 'Unlock',
      ],
      [
        '_permission' => 'administer modules',
        '_csrf_token' => 'TRUE',
        '_custom_access' => InstallerController::class . '::access',
      ],
    );
    $routes['project_browser.recipe_input'] = new Route(
      '/admin/modules/browse/recipe-input',
      [
        '_form' => RecipeForm::class,
      ],
      [
        '_permission' => 'administer modules',
      ],
    );

    return $routes;
  }

}
