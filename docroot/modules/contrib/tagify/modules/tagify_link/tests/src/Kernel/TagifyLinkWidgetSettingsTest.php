<?php

namespace Drupal\Tests\tagify_link\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\tagify_link\Plugin\Field\FieldWidget\TagifyLinkWidget;

/**
 * Tests TagifyLinkWidget default settings.
 *
 * @group tagify_link
 */
class TagifyLinkWidgetSettingsTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field',
    'link',
    'system',
    'tagify',
    'tagify_link',
    'user',
  ];

  /**
   * Tests that defaultSettings() returns expected values.
   */
  public function testDefaultSettings(): void {
    $defaults = TagifyLinkWidget::defaultSettings();

    $this->assertSame('CONTAINS', $defaults['match_operator']);
    $this->assertSame(20, $defaults['match_limit']);
    $this->assertSame(1, $defaults['suggestions_dropdown']);
    $this->assertSame('', $defaults['placeholder']);
    $this->assertSame(0, $defaults['show_entity_id']);
    $this->assertSame(0, $defaults['show_info_label']);
    $this->assertSame('', $defaults['info_label']);
  }

}
