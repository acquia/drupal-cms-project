<?php

declare(strict_types=1);

namespace Drupal\Tests\google_tag\FunctionalJavascript;

use Drupal\google_tag\Entity\TagContainer;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that gtag.js turns drupalSettings.gtag into dataLayer commands.
 *
 * The drupalSettings themselves are covered by the kernel tests.
 *
 * @group google_tag
 */
#[Group('google_tag')]
#[RunTestsInSeparateProcesses]
final class IntegrationFunctionalJavascriptTest extends GoogleTagTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'test_page_test',
    'token',
    'google_tag',
    'google_tag_test',
  ];

  /**
   * Tests the config, set and event commands pushed by gtag.js.
   */
  public function testDataLayer(): void {
    TagContainer::create([
      'id' => 'foo',
      'tag_container_ids' => [
        'GT-XXXXXX',
        'G-XXXXXX',
        'AW-XXXXXX',
        'DC-XXXXXX',
        'UA-XXXXXX',
      ],
      'events' => [
        'route_name' => [],
      ],
      'dimensions_metrics' => [
        ['type' => 'metric', 'name' => 'foo', 'value' => '6'],
        ['type' => 'dimension', 'name' => 'langcode', 'value' => '[language:langcode]'],
      ],
    ])->save();

    $this->drupalGet('test-page');
    $this->assertGoogleTagExists();
    $this->assertDataLayerContains(['config', 'GT-XXXXXX', ['foo' => 6, 'langcode' => 'en']]);
    $this->assertDataLayerContains(['config', 'G-XXXXXX']);
    $this->assertDataLayerContains(['config', 'AW-XXXXXX']);
    $this->assertDataLayerContains(['config', 'DC-XXXXXX']);
    $this->assertDataLayerContains(['config', 'UA-XXXXXX']);
    $this->assertDataLayerContains(['set', 'developer_id.dMDhkMT', TRUE]);
    $this->assertDataLayerContains(['event', 'route_name', ['route_name' => 'test_page_test.test_page']]);
    $this->assertGtagPushesArguments();
  }

}
