<?php

namespace Drupal\Tests\google_tag\Kernel\Migrate;

use Drupal\google_tag\Hook\GoogleTagRequirements;
use Drupal\Tests\google_tag\Kernel\GoogleTagTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests hook google_tag_requirements for updating the module.
 *
 * @group google_tag
 * @requires module google_analytics
 */
#[Group('google_tag')]
#[RunTestsInSeparateProcesses]
class GoogleTagRequirementsTest extends GoogleTagTestCase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'google_analytics',
    'node',
    'taxonomy',
    'language',
  ];

  /**
   * {@inheritDoc}
   */
  protected function setUp(): void {
    parent::setUp();
    // Defines REQUIREMENT_ERROR before Drupal 11.2, as update.php does.
    require_once $this->root . '/core/includes/install.inc';
    $this->loadFixture('google_analytics_migrate.php');
    $this->loadFixture('container_migrate.php');
  }

  /**
   * Tests that the module is not allowed to be updated.
   *
   * If both google analytics and google tag 1.x were installed.
   *
   * @throws \Exception
   */
  public function testGoogleTagRequirements(): void {
    $hooks = $this->container->get('class_resolver')->getInstanceFromDefinition(GoogleTagRequirements::class);
    $this->assertRequirements($hooks->updateRequirements());

    // Drupal 11.2 and earlier still call the procedural hook.
    $this->container->get('module_handler')->loadInclude('google_tag', 'install');
    $this->assertRequirements(google_tag_requirements('update'));
    self::assertSame([], google_tag_requirements('runtime'));
  }

  /**
   * Asserts the update requirement that blocks an ambiguous upgrade.
   *
   * @param array $requirements
   *   The requirements returned by the hook.
   */
  private function assertRequirements(array $requirements): void {
    self::assertArrayHasKey('google_tag', $requirements);
    $google_tag_requirements = $requirements['google_tag'];
    self::assertEquals(
      'In order to use Google Tag 2.x, you must decide the upgrade path between Google Tag 1.x and Google Analytics.',
      $google_tag_requirements['description']
    );
    self::assertEquals('Google Tag', $google_tag_requirements['title']);
    self::assertSame(GoogleTagRequirements::errorSeverity(), $google_tag_requirements['severity']);
    self::assertEquals(
      'Google Tag 2.x is incompatible with Google Analytics while upgrading from 1.x.',
      $google_tag_requirements['value']
    );
  }

}
