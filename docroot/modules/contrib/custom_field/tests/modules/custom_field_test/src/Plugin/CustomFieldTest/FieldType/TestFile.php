<?php

declare(strict_types=1);

namespace Drupal\custom_field_test\Plugin\CustomFieldTest\FieldType;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\custom_field_test\Attribute\TestFieldType;
use Drupal\custom_field_test\Plugin\FieldTypeTestBase;
use Drupal\file\Entity\File;

/**
 * Plugin implementation of the 'file' field type test.
 */
#[TestFieldType(
  id: 'file',
  label: new TranslatableMarkup('File'),
)]
class TestFile extends FieldTypeTestBase {

  /**
   * {@inheritdoc}
   */
  public function getDefaultWidget(): array {
    return [
      'id' => 'file_generic',
      'class' => 'Drupal\custom_field\Plugin\CustomField\FieldWidget\FileWidget',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultFormatter(): array {
    return [
      'id' => 'file_default',
      'class' => 'Drupal\custom_field\Plugin\CustomField\FieldFormatter\GenericFileFormatter',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function testCases(string $name, array $settings): array {
    $filenames = ['example.txt', 'example2.txt'];
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
    return [
      $this->buildTestCase($name, $files[0]?->id()),
      $this->buildTestCase($name, $files[1]?->id()),
    ];
  }

}
