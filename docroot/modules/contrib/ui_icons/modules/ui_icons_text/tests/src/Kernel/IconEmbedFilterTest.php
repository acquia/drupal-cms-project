<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_icons_text\Kernel;

use Drupal\Core\Theme\Icon\IconDefinition;
use Drupal\KernelTests\KernelTestBase;
use Drupal\ui_icons_text\Plugin\Filter\IconEmbed;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Test the IconPreview class.
 *
 * @internal
 */
#[CoversClass(IconPreview::class)]
#[Group('ui_icons')]
class IconEmbedFilterTest extends KernelTestBase {

  /**
   * Icon pack from ui_icons_test module.
   */
  private const TEST_ICON_PACK_ID = 'test_path';

  /**
   * Icon from ui_icons_test module.
   */
  private const TEST_ICON_ID = 'foo';

  /**
   * Icon filename from ui_icons_test module.
   */
  private const TEST_ICON_FILENAME = 'foo.png';

  /**
   * Icon class from ui_icons_test module.
   */
  private const TEST_ICON_CLASS = 'foo';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'filter',
    'ui_icons',
    'ui_icons_text',
    'ui_icons_test',
  ];

  /**
   * The icon embed filter plugin.
   *
   * @var \Drupal\ui_icons_text\Plugin\Filter\IconEmbed
   */
  protected $filter;

  /**
   * The icon pack manager service.
   *
   * @var \Drupal\Core\Theme\Icon\Plugin\IconPackManagerInterface
   */
  protected $pluginManagerIconPack;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['system', 'filter', 'ui_icons']);

    $this->pluginManagerIconPack = $this->container->get('plugin.manager.icon_pack');

    /** @var \Drupal\filter\FilterPluginManager $filterManager */
    $filterManager = $this->container->get('plugin.manager.filter');
    $configuration = [];
    $plugin_id = 'icon_embed';
    $plugin_definition = $filterManager->getDefinition($plugin_id);

    $this->filter = new IconEmbed(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $this->pluginManagerIconPack,
      $this->container->get('renderer'),
      $this->container->get('logger.factory')
    );
  }

  /**
   * Data provider for ::testProcess().
   *
   * @return \Generator
   *   The test cases.
   */
  public static function providerTestProcess(): iterable {
    $icon_full_id = IconDefinition::createIconId(self::TEST_ICON_PACK_ID, self::TEST_ICON_ID);

    yield 'no icon tag' => [
      '<p>This is a test paragraph without icons.</p>',
      FALSE,
    ];

    yield 'icon tag without id' => [
      '<p>Test empty icon: <drupal-icon data-foo="bar"></drupal-icon></p>',
      FALSE,
    ];

    yield 'icon tag with empty id' => [
      '<p>Test empty icon: <drupal-icon data-icon-id=""></drupal-icon></p>',
      FALSE,
    ];

    yield 'icon valid' => [
      '<p>Test icon: <drupal-icon data-icon-id="' . $icon_full_id . '" /></p>',
      TRUE,
      [
        '<span class="drupal-icon">',
        self::TEST_ICON_CLASS,
        self::TEST_ICON_FILENAME,
      ],
    ];

    yield 'icon invalid id' => [
      '<p>Test icon: <drupal-icon data-icon-id="invalid:icon" /></p>',
      TRUE,
      [
        '<span class="drupal-icon"></span>',
      ],
    ];

    yield 'icon with settings' => [
      '<p>Test icon and settings: <drupal-icon data-icon-id="' . $icon_full_id . '" data-icon-settings=\'{"width":100}\' /></p>',
      TRUE,
      [
        '<span class="drupal-icon">',
        self::TEST_ICON_CLASS,
        self::TEST_ICON_FILENAME,
        'width="100"',

      ],
    ];

    yield 'icon with additional attributes' => [
      '<p>Test icon and attributes: <drupal-icon data-icon-id="' . $icon_full_id . '" class="custom-class" aria-label="Custom Label" /></p>',
      TRUE,
      [
        '<span class="custom-class drupal-icon"',
        'aria-label="Custom Label"',
      ],
    ];

    yield 'icon with attribute role remove label' => [
      '<p>Test attributes: <drupal-icon data-icon-id="' . $icon_full_id . '" aria-label="foo" aria-hidden="true" role="presentation" /></p>',
      TRUE,
      [
        '<span aria-hidden role="presentation" class="drupal-icon">',
      ],
    ];
  }

  /**
   * Test the IconEmbed::process() method.
   *
   * @param string $html
   *   The html text to test.
   * @param bool $is_transformed
   *   The html text is transformed.
   * @param array $expected_contains
   *   The html text string processed must contain.
   */
  #[DataProvider('providerTestProcess')]
  public function testProcess(string $html, bool $is_transformed, array $expected_contains = []): void {
    $result = $this->filter->process($html, 'en');
    if (!$is_transformed) {
      $this->assertEquals($html, $result->getProcessedText());
      return;
    }
    foreach ($expected_contains as $contain) {
      $this->assertStringContainsString($contain, $result->getProcessedText());
    }
  }

}
