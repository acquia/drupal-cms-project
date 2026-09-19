<?php

declare(strict_types=1);

namespace Drupal\automatic_updates_extensions_test_api;

use Drupal\automatic_updates\ExtensionUpdateSandboxManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Drupal\package_manager_test_api\ApiController as PackageManagerApiController;

/**
 * Provides API endpoint to interact with stage directory in functional tests.
 */
class ApiController extends PackageManagerApiController {

  /**
   * {@inheritdoc}
   */
  protected $finishedRoute = 'automatic_updates_extensions_test_api.finish';

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get(ExtensionUpdateSandboxManager::class),
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function createAndApplyStage(Request $request): string {
    $id = $this->stage->begin($request->query->all());
    $this->stage->stage();
    $this->stage->apply();
    return $id;
  }

}
