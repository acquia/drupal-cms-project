<?php

declare(strict_types=1);

namespace Drupal\Tests\google_tag\Kernel;

use Drupal\Component\Plugin\Attribute\AttributeInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests Google Tag event plugin discovery.
 *
 * @coversDefaultClass \Drupal\google_tag\GoogleTagEventManager
 * @group google_tag
 */
#[Group('google_tag')]
#[RunTestsInSeparateProcesses]
final class GoogleTagEventManagerTest extends GoogleTagTestCase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['google_tag_attribute_test'];

  /**
   * Tests that annotated and attribute plugins are both discovered.
   */
  public function testDiscovery(): void {
    if (!interface_exists(AttributeInterface::class)) {
      $this->markTestSkipped('Attribute discovery requires Drupal 10.2 or later.');
    }
    $definitions = $this->container->get('plugin.manager.google_tag_event')->getDefinitions();

    // Annotated plugins are still discovered.
    self::assertArrayHasKey('login', $definitions);
    self::assertSame('login', $definitions['login']['event_name']);

    self::assertArrayHasKey('attribute_test', $definitions);
    $definition = $definitions['attribute_test'];
    self::assertInstanceOf(TranslatableMarkup::class, $definition['label']);
    self::assertSame('Attribute test', (string) $definition['label']);
    // The event name defaults to the plugin ID.
    self::assertSame('attribute_test', $definition['event_name']);

    // Plugins whose dependency is not installed are removed.
    self::assertArrayNotHasKey('attribute_test_dependency', $definitions);
    $this->enableModules(['node']);
    $definitions = $this->container->get('plugin.manager.google_tag_event')->getDefinitions();
    self::assertArrayHasKey('attribute_test_dependency', $definitions);
    self::assertSame('attribute_dependency', $definitions['attribute_test_dependency']['event_name']);
  }

}
