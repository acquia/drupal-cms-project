<?php

declare(strict_types=1);

namespace Drupal\Tests\project_browser\Kernel;

use Drupal\Core\Session\AccountInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\project_browser\QueryManager;
use Drupal\project_browser\ProjectBrowser\Normalizer;
use Drupal\project_browser\ProjectRepository;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the Normalizer class.
 *
 * @group project_browser
 */
#[CoversClass(NormalizerTest::class)]
#[Group('project_browser')]
final class NormalizerTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['project_browser', 'field', 'system', 'user'];

  /**
   * Test that tasks returned by activators are filtered by user access.
   */
  public function testTasksAreFilteredByAccess(): void {
    $this->config('project_browser.admin_settings')
      ->set('enabled_sources', [
        'drupal_core' => [],
      ])
      ->save();

    // Prime the project cache.
    $this->container->get(QueryManager::class)
      ->getProjects('drupal_core');
    $project = $this->container->get(ProjectRepository::class)
      ->get('drupal_core/field');

    $this->assertFalse(
      $this->container->get(AccountInterface::class)->hasPermission('administer modules'),
    );
    $normalizer = $this->container->get(Normalizer::class);
    $normalized = $normalizer->normalize($project, context: ['source' => 'drupal_core']);
    $this->assertEmpty($normalized['tasks']);

    // If we normalize with a user who can administer modules, we should get the
    // uninstall task.
    $this->installEntitySchema('user');
    $account = $this->createUser(['administer modules']);
    $normalized = $normalizer->normalize($project, context: [
      'source' => 'drupal_core',
      'account' => $account,
    ]);
    $this->assertSame('Uninstall', $normalized['tasks'][0]['text']);
  }

}
