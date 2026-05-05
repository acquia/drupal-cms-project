<?php

declare(strict_types=1);

namespace Drupal\ui_icons_field\Kernel\Plugin;

use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Form\FormState;
use Drupal\KernelTests\KernelTestBase;
use Drupal\entity_test\Entity\EntityTest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Test the IconWidget class.
 *
 * @internal
 */
#[CoversClass(IconWidget::class)]
#[Group('ui_icons')]
class IconWidgetKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field',
    'system',
    'user',
    'entity_test',
    'ui_icons',
    'ui_icons_field',
    'ui_icons_test',
  ];

  /**
   * The base field definition.
   *
   * @var \Drupal\Core\Field\BaseFieldDefinition
   */
  private BaseFieldDefinition $baseField;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // @todo test with entity_test_rev?
    $this->baseField = BaseFieldDefinition::create('ui_icon')
      ->setName('icon');
    $this->container->get('state')->set('entity_test.additional_base_field_definitions', [
      'icon' => $this->baseField,
    ]);

    $this->installEntitySchema('entity_test');
    $this->installConfig(['system']);
  }

  /**
   * Tests the formElement method.
   */
  public function testFormElement(): void {
    $entity = EntityTest::create([
      'name' => 'sample entity',
    ]);
    $entity->save();
    $element = $this->buildWidgetForm($entity);

    $this->assertArrayHasKey('value', $element);
    $this->assertSame('icon_autocomplete', $element['value']['#type']);
    $this->assertNull($element['value']['#default_value']);
    $this->assertEmpty($element['value']['#allowed_icon_pack']);
    $this->assertFalse($element['value']['#show_settings']);
    $this->assertFalse($element['value']['#required']);

    // Test that the field can be attached to an entity.
    $entity = EntityTest::create([
      'name' => 'sample entity 2',
    ]);
    $entity->icon = [
      'target_id' => 'test_minimal:foo',
    ];
    $entity->save();
    // Reload the entity and check that the field value is correct.
    $entity = EntityTest::load($entity->id());

    $this->assertEquals('test_minimal:foo', $entity->icon->target_id);
  }

  /**
   * Build the icon widget form.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to build the form for.
   * @param array $settings
   *   The settings to pass to the widget, default empty array.
   *
   * @return array
   *   A built form array of the icon widget.
   */
  protected function buildWidgetForm($entity, array $settings = []): array {
    $form = [
      '#parents' => [],
    ];
    return $this->container->get('plugin.manager.field.widget')->createInstance('icon_widget', [
      'field_definition' => $this->baseField,
      'settings' => $settings,
      'third_party_settings' => [],
    ])->formElement($entity->icon, 0, ['#description' => ''], $form, new FormState());
  }

}
