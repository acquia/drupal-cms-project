<?php

declare(strict_types=1);

namespace Drupal\custom_field_test\Plugin\CustomFieldTest\FieldType;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\custom_field_test\Attribute\TestFieldType;
use Drupal\custom_field_test\Plugin\FieldTypeTestBase;

/**
 * Plugin implementation of the 'map_string' field type test.
 */
#[TestFieldType(
  id: 'map_string',
  label: new TranslatableMarkup('Map string'),
)]
class TestMapString extends FieldTypeTestBase {

  /**
   * {@inheritdoc}
   */
  public function getDefaultWidget(): array {
    return [
      'id' => 'map_text',
      'class' => 'Drupal\custom_field\Plugin\CustomField\FieldWidget\MapTextWidget',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultFormatter(): array {
    return [
      'id' => 'map_list',
      'class' => 'Drupal\custom_field\Plugin\CustomField\FieldFormatter\MapListFormatter',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function testCases(string $name, array $settings): array {
    $map_string = ['text1', 'text2', 'text3', 'text4'];
    $new_map_string = ['new text1', 'new text2', 'new text3'];
    return [
      $this->buildTestCase($name, $map_string),
      $this->buildTestCase($name, $new_map_string),
    ];
  }

}
