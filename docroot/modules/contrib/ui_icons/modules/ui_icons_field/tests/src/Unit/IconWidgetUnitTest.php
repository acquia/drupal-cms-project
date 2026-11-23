<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_icons_field\Unit\Plugin;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Theme\Icon\IconDefinition;
use Drupal\Core\Theme\Icon\IconDefinitionInterface;
use Drupal\Tests\Core\Theme\Icon\IconTestTrait;
use Drupal\Tests\UnitTestCase;
use Drupal\ui_icons_field\Plugin\Field\FieldWidget\IconWidget;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Test the IconWidget class.
 *
 * @internal
 */
#[CoversClass(IconWidget::class)]
#[Group('ui_icons')]
class IconWidgetUnitTest extends UnitTestCase {

  use IconTestTrait;

  /**
   * The field widget under test.
   *
   * @var \Drupal\ui_icons_field\Plugin\Field\FieldWidget\IconWidget
   */
  private IconWidget $widget;

  /**
   * The container.
   *
   * @var \Drupal\Core\DependencyInjection\ContainerBuilder
   */
  private ContainerBuilder $container;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->container = new ContainerBuilder();
    $this->container->set('string_translation', $this->createMock(TranslationInterface::class));
    \Drupal::setContainer($this->container);

    $fieldDefinition = $this->createMock('Drupal\Core\Field\FieldDefinition');

    $this->widget = new IconWidget(
      'icon_widget',
      [],
      $fieldDefinition,
      [],
      []
    );
  }

  /**
   * Tests the massageFormValues method.
   */
  public function testMassageFormValues(): void {
    $form_state = $this->createMock('Drupal\Core\Form\FormState');

    $values = [];

    // Invalid icon.
    $values[]['value'] = [
      'icon' => NULL,
    ];

    // Icon with data.
    $values[]['value'] = [
      'icon' => $this->createMockIcon([
        'pack_id' => 'foo',
        'icon_id' => 'bar',
      ]),
    ];

    $actual = $this->widget->massageFormValues($values, [], $form_state);

    foreach ($actual as $delta => $value) {
      if (NULL === $values[$delta]['value']['icon']) {
        $this->assertArrayNotHasKey('target_id', $value);
      }
      else {
        $this->assertEquals('foo:bar', $value['target_id']);
      }
    }
  }

  /**
   * Create a mock icon.
   *
   * @param array<string, string>|null $iconData
   *   The icon data to create.
   *
   * @return \Drupal\Core\Theme\Icon\IconDefinitionInterface
   *   The icon mocked.
   */
  private function createMockIcon(?array $iconData = NULL): IconDefinitionInterface {
    if (NULL === $iconData) {
      $iconData = [
        'pack_id' => 'foo',
        'icon_id' => 'bar',
      ];
    }

    $icon = $this->prophesize(IconDefinitionInterface::class);
    $icon
      ->getRenderable(['width' => $iconData['width'] ?? '', 'height' => $iconData['height'] ?? ''])
      ->willReturn(['#markup' => '<svg></svg>']);

    $icon_full_id = IconDefinition::createIconId($iconData['pack_id'], $iconData['icon_id']);
    $icon
      ->getId()
      ->willReturn($icon_full_id);

    return $icon->reveal();
  }

}
