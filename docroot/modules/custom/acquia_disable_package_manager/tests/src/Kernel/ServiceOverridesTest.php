<?php

namespace Drupal\Tests\acquia_disable_package_manager\Kernel;

use Drupal\acquia_disable_package_manager\AcquiaDisablePackageManagerServiceProvider;
use Drupal\acquia_disable_package_manager\AhActivator;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Extension\Requirement\RequirementSeverity;
use Drupal\KernelTests\KernelTestBase;
use Drupal\package_manager\Exception\SandboxEventException;
use Drupal\package_manager\PathExcluder\SiteFilesExcluder;
use Drupal\package_manager\StatusCheckTrait;
use Drupal\package_manager\ValidationResult;
use Drupal\project_browser\ActivationManager;
use Drupal\project_browser\Activator\ModuleActivator;
use Drupal\project_browser\Activator\RecipeActivator;
use Drupal\project_browser\ComposerInstaller\Installer;
use Drupal\project_browser\ProjectBrowser\Project;
use Drupal\project_browser\ProjectType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

#[CoversClass(AcquiaDisablePackageManagerServiceProvider::class)]
#[Group('acquia_disable_package_manager')]
#[RunTestsInSeparateProcesses]
class ServiceOverridesTest extends KernelTestBase {

  use StatusCheckTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'update',
    'user',
    'package_manager',
    'automatic_updates',
    'project_browser',
    'acquia_disable_package_manager',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    putenv('AH_SITE_ENVIRONMENT=dev');
    parent::setUp();
  }

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container): void {
    parent::register($container);

    $container->getDefinition(ModuleActivator::class)
      ->setPublic(TRUE);
    $container->getDefinition(RecipeActivator::class)
      ->setPublic(TRUE);
  }

  /**
   * Tests that container services are altered as expected.
   */
  public function testServiceAlterations(): void {
    // The module activator is straightforwardly decorated.
    $this->assertInstanceOf(
      AhActivator::class,
      $this->container->get(ModuleActivator::class),
    );
    // Project Browser's recipe activator is not decorated, because it's
    // an event subscriber. But the activation-ey parts of it are delegated
    // to our decorator.
    $this->assertInstanceOf(
      RecipeActivator::class,
      $this->container->get(RecipeActivator::class),
    );

    $activation_manager = $this->container->get(ActivationManager::class);
    assert($activation_manager instanceof ActivationManager);
    $this->assertInstanceOf(
      AhActivator::class,
      $activation_manager->getActivatorForProject(new Project(
        logo: NULL,
        isCompatible: TRUE,
        machineName: 'test_recipe',
        body: [],
        title: 'Test Recipe',
        packageName: 'drupal/test_recipe',
        type: ProjectType::Recipe,
      )),
    );

    $wrappers = $this->container->getDefinition(SiteFilesExcluder::class)
      ->getArgument(2);
    $this->assertNotContains('private', $wrappers);

    // If we try to do a status check for installation, we should be stopped in our tracks.
    $installer = $this->container->get(Installer::class);
    assert($installer instanceof Installer);
    $results = $this->runStatusCheck($installer);
    $this->assertCount(1, $results);
    $result = $results[0];
    assert($result instanceof ValidationResult);
    $this->assertSame(RequirementSeverity::Error->value, $result->severity);
    $this->assertStringStartsWith('Acquia Cloud is write-protected by design.', (string) $result->messages[0]);

    // If we disregard the status check and try to do an installation anyway,
    // we should get a harsher "no".
    $this->expectException(SandboxEventException::class);
    $this->expectExceptionMessage('Acquia Cloud is write-protected by design.');
    $installer->create();
  }

}
