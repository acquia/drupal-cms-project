<?php

declare(strict_types=1);

namespace Drupal\Tests\project_browser\Kernel;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\KernelTests\KernelTestBase;
use Drupal\project_browser\ActivationManager;
use Drupal\project_browser\Activator\ActivationStatus;
use Drupal\project_browser\Activator\ActivatorInterface;
use Drupal\project_browser\ProjectBrowser\Project;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the activation manager service.
 */
#[Group('project_browser')]
#[CoversClass(ActivationManager::class)]
final class ActivationManagerTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['project_browser', 'user'];

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container): void {
    parent::register($container);

    $container->register('activator1', MockActivator::class)
      ->addTag('project_browser.activator', ['priority' => 5])
      ->setProperty('shouldBeChosen', FALSE);

    $container->register('activator2', MockActivator::class)
      ->addTag('project_browser.activator', ['priority' => 10])
      ->setProperty('shouldBeChosen', TRUE);
  }

  /**
   * Tests that activators are ordered by priority.
   */
  public function testActivatorPriority(): void {
    $project = new Project(
      logo: NULL,
      isCompatible: TRUE,
      machineName: 'test',
      body: [],
      title: 'Test',
      packageName: 'drupal/test',
    );
    $activator = $this->container->get(ActivationManager::class)
      ->getActivatorForProject($project);
    assert($activator instanceof MockActivator);
    $this->assertTrue($activator->shouldBeChosen);
  }

}

/**
 * A mock activator to test activator prioritization.
 */
class MockActivator implements ActivatorInterface {

  /**
   * Whether we expect this activator to be the one chosen for the project.
   */
  public bool $shouldBeChosen;

  /**
   * {@inheritdoc}
   */
  public function getStatus(Project $project): ActivationStatus {
    return ActivationStatus::Active;
  }

  /**
   * {@inheritdoc}
   */
  public function supports(Project $project): bool {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function activate(Project $project): ?array {
    return NULL;
  }

}
