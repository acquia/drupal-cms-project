<?php

declare(strict_types=1);

namespace Drupal\automatic_updates_test_api;

use Drupal\automatic_updates\UpdateSandboxManager;
use Drupal\package_manager_test_api\ApiController as PackageManagerApiController;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A controller for running updates directly in build tests.
 */
class ApiController extends PackageManagerApiController {

  /**
   * {@inheritdoc}
   */
  protected $finishedRoute = 'automatic_updates_test_api.finish';

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get(UpdateSandboxManager::class),
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function createAndApplyStage(Request $request): string {
    $id = $this->stage->begin($request->get('projects', []));
    $this->stage->stage();
    $this->stage->apply();
    return $id;
  }

  /**
   * Deletes last cron run time, so Automated Cron will run during this request.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The response.
   */
  public function resetCron(): Response {
    \Drupal::state()->delete('system.cron_last');
    return new Response('cron reset');
  }

}
