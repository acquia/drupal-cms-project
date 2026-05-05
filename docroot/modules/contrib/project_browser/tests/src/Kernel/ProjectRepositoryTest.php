<?php

declare(strict_types=1);

namespace Drupal\Tests\project_browser\Kernel;

use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\KernelTests\KernelTestBase;
use Drupal\project_browser\ProjectBrowser\Project;
use Drupal\project_browser\ProjectRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Tests the project repository service.
 */
#[CoversClass(ProjectRepository::class)]
#[Group('project_browser')]
final class ProjectRepositoryTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['project_browser', 'user'];

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container): void {
    parent::register($container);

    // Kernel tests normally use a memory key-value store, but that means we
    // cannot test that a new instance of the project is returned. So, construct
    // the repository with the database-backed key-value store instead.
    $container->getDefinition(ProjectRepository::class)
      ->setArgument('$keyValueFactory', new Reference('keyvalue.database'));
  }

  /**
   * Tests that source-specific cache tags are invalidated when data is cleared.
   */
  public function testClearInvalidatesCache(): void {
    // Set up a mock cache tag invalidator so we can be sure it gets cleared.
    $mock_invalidator = $this->createMock(CacheTagsInvalidatorInterface::class);
    $mock_invalidator->expects($this->once())
      ->method('invalidateTags')
      ->with(['project_browser:test']);

    $this->container->get(CacheTagsInvalidatorInterface::class)
      ->addInvalidator($mock_invalidator);
    $this->container->get(ProjectRepository::class)->clear('test');
  }

  /**
   * Tests loading a stored project.
   */
  public function testGetStoredProject(): void {
    $project = new Project(
      logo: NULL,
      isCompatible: TRUE,
      machineName: 'test',
      body: [],
      title: 'Test',
      packageName: 'drupal/test',
    );
    $repository = $this->container->get(ProjectRepository::class);
    $repository->store('test', $project);
    $project_again = $repository->get('test/' . $project->id);
    $this->assertNotSame($project, $project_again);
    $this->assertSame($project->toArray(), $project_again->toArray());

    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage("Project 'sight/unseen' was not found in non-volatile storage.");
    $repository->get('sight/unseen');
  }

}
