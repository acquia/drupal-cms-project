<?php

declare(strict_types=1);

namespace Drupal\custom_field_test\Plugin\CustomFieldTest\FieldType;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\custom_field_test\Attribute\TestFieldType;
use Drupal\custom_field_test\Plugin\FieldTypeTestBase;
use Drupal\file\Entity\File;

/**
 * Plugin implementation of the 'image' field type test.
 */
#[TestFieldType(
  id: 'image',
  label: new TranslatableMarkup('Image'),
)]
class TestImage extends FieldTypeTestBase {

  /**
   * {@inheritdoc}
   */
  public function getDefaultWidget(): array {
    return [
      'id' => 'image_image',
      'class' => 'Drupal\custom_field\Plugin\CustomField\FieldWidget\ImageWidget',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultFormatter(): array {
    return [
      'id' => 'image',
      'class' => 'Drupal\custom_field\Plugin\CustomField\FieldFormatter\ImageFormatter',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function testCases(string $name, array $settings): array {
    $filenames = ['example.jpg', 'example-2.jpg'];
    $query = \Drupal::entityQuery('file')
      ->condition('filename', $filenames, 'IN')
      ->accessCheck(FALSE);
    $fids = $query->execute();
    $files = [];
    if (!empty($fids)) {
      foreach ($fids as $fid) {
        $files[] = File::load($fid);
      }
    }
    $properties = [
      $name,
      $name . '__alt',
      $name . '__title',
    ];
    return [
      $this->buildTestCase($properties, [
        $properties[0] => $files[0]->id(),
        $properties[1] => $this->random->sentences(4, TRUE),
        $properties[2] => $this->random->sentences(3, TRUE),
      ]),
      $this->buildTestCase($properties, [
        $properties[0] => $files[1]->id(),
        $properties[1] => $this->random->sentences(4, TRUE),
        $properties[2] => $this->random->sentences(3, TRUE),
      ]),
      // Test extra properties when the main property is NULL.
      $this->buildTestCase($properties, [
        $properties[1] => $this->random->sentences(4, TRUE),
        $properties[2] => $this->random->sentences(3, TRUE),
      ]),
    ];
  }

}
