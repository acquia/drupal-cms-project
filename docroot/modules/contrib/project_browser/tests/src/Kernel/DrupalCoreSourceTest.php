<?php

declare(strict_types=1);

namespace Drupal\Tests\project_browser\Kernel;

use Drupal\Core\Extension\Extension;
use Drupal\Core\Extension\ProfileExtensionList;
use Drupal\KernelTests\KernelTestBase;
use Drupal\project_browser\Plugin\ProjectBrowserSource\DrupalCore;
use Drupal\project_browser\Plugin\ProjectBrowserSourceManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the Drupal core source plugin.
 */
#[Group('project_browser')]
#[CoversClass(DrupalCore::class)]
final class DrupalCoreSourceTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['project_browser', 'user'];

  /**
   * Tests that install profiles are ignored by the drupal_core source.
   */
  public function testProfilesAreIgnored(): void {
    // Find all available profiles, to confirm that the source returns none of
    // them.
    $available_profiles = array_map(
      fn (Extension $profile): string => $profile->getName(),
      $this->container->get(ProfileExtensionList::class)->getList(),
    );

    $source = $this->container->get(ProjectBrowserSourceManager::class)
      ->createInstance('drupal_core');
    $project_names = array_column($source->getProjects()->list, 'machineName');
    $this->assertNotEmpty($project_names);

    // No profiles should be listed in the results.
    $this->assertEmpty(array_intersect($available_profiles, $project_names));
  }

}
