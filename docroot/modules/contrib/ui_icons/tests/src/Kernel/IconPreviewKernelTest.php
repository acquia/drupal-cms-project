<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_icons\Kernel;

use Drupal\Core\Theme\Icon\Plugin\IconPackManagerInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\ui_icons\IconPreview;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Test the IconPreview class.
 *
 * @internal
 */
#[RunTestsInSeparateProcesses]
#[CoversClass(IconPreview::class)]
#[Group('ui_icons')]
class IconPreviewKernelTest extends KernelTestBase {

  private const TEST_MODULE_PATH = __DIR__ . '/../../tests/modules/ui_icons_test';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'ui_icons',
    'ui_icons_test',
  ];

  /**
   * The IconPackManager instance.
   *
   * @var \Drupal\Core\Theme\Icon\Plugin\IconPackManagerInterface
   */
  private IconPackManagerInterface $pluginManagerIconPack;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->pluginManagerIconPack = $this->container->get('plugin.manager.icon_pack');
  }

  /**
   * Tests the getPreview method of the IconAutocompleteController.
   */
  public function testGetPreview(): void {
    $icon = $this->pluginManagerIconPack->getIcon('test_svg:foo');
    $preview = IconPreview::getPreview($icon, ['data-foo' => 'bar']);
    // Source use path and is variant on CI.
    unset($preview['#source']);

    $expected = [
      '#theme' => 'icon_preview',
      '#icon_label' => 'test_svg:foo',
      '#icon_id' => 'foo',
      '#pack_id' => 'test_svg',
      '#extractor' => 'svg',
      '#library' => NULL,
      '#settings' => [
        'data-foo' => 'bar',
      ],
    ];
    $this->assertSame($preview, $expected);

    $icon = $this->pluginManagerIconPack->getIcon('test_preview:foo');
    $preview = IconPreview::getPreview($icon, ['data-foo' => 'bar']);
    // Source use path and is variant on CI.
    unset($preview['#context']['source']);

    $expected = [
      '#type' => 'inline_template',
      '#template' => '_preview_ {{ icon_id }}',
      '#context' => [
        'data-foo' => 'bar',
        'label' => 'test_preview:foo',
        'icon_id' => 'foo',
        'pack_id' => 'test_preview',
        'extractor' => 'path',
        'content' => NULL,
        // @todo access private const?
        'size' => 48,
      ],
    ];
    $this->assertSame($preview, $expected);
  }

}
