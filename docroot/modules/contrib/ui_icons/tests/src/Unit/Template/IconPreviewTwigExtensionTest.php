<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_icons\Unit;

use Drupal\Core\Theme\Icon\IconDefinitionInterface;
use Drupal\Core\Theme\Icon\Plugin\IconPackManagerInterface;
use Drupal\Tests\Core\Theme\Icon\IconTestTrait;
use Drupal\Tests\UnitTestCase;
use Drupal\ui_icons\IconPreview;
use Drupal\ui_icons\Template\IconPreviewTwigExtension;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Test the IconPreviewTwigExtension class.
 *
 * @internal
 */
#[CoversClass(IconPreviewTwigExtension::class)]
#[Group('ui_icons')]
class IconPreviewTwigExtensionTest extends UnitTestCase {

  use IconTestTrait;

  /**
   * The plugin manager.
   *
   * @var \Drupal\ui_icons\Plugin\IconPackManagerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  private IconPackManagerInterface $pluginManagerIconPack;

  /**
   * The twig extension.
   *
   * @var \Drupal\ui_icons\Template\IconPreviewTwigExtension
   */
  private IconPreviewTwigExtension $iconPreviewTwigExtension;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->pluginManagerIconPack = $this->createMock(IconPackManagerInterface::class);
    $this->iconPreviewTwigExtension = new IconPreviewTwigExtension($this->pluginManagerIconPack);
  }

  /**
   * Test the getFunctions method.
   */
  public function testGetFunctions(): void {
    $functions = $this->iconPreviewTwigExtension->getFunctions();
    $this->assertCount(1, $functions);
    $this->assertEquals('icon_preview', $functions[0]->getName());
  }

  /**
   * Test the getIconPreview method.
   */
  public function testGetIconPreview(): void {
    $settings = ['foo' => 'bar'];

    $iconPreview = $this->createMock(IconPreview::class);
    $iconPreview->method('getPreview')
      ->with($settings)
      ->willReturn(['preview_icon'] + $settings);

    $icon = $this->createMock(IconDefinitionInterface::class);
    $icon
      ->method('getPackId')
      ->willReturn('pack_id');
    $icon
      ->method('getId')
      ->willReturn('icon_id');
    $icon
      ->method('getIconId')
      ->willReturn('pack_id:icon_id');
    $icon
      ->method('getSource')
      ->willReturn('foo/bar.svg');
    $this->pluginManagerIconPack
      ->method('getIcon')
      ->willReturn($icon);

    $result = $this->iconPreviewTwigExtension->getIconPreview('pack_id', 'icon_id', $settings);
    $expected = [
      '#theme' => 'icon_preview',
      '#icon_label' => 'icon_id',
      '#icon_id' => 'pack_id:icon_id',
      '#pack_id' => 'pack_id',
      '#extractor' => NULL,
      '#source' => 'foo/bar.svg',
      '#library' => NULL,
      '#settings' => $settings,
    ];
    $this->assertEquals($expected, $result);
  }

  /**
   * Test the getIconPreview method with invalid icon.
   */
  public function testGetIconPreviewInvalidIcon(): void {
    $this->pluginManagerIconPack
      ->method('getIcon')
      ->willReturn(NULL);

    $result = $this->iconPreviewTwigExtension->getIconPreview('pack_id', 'icon_id', []);
    $this->assertEquals([], $result);
  }

}
