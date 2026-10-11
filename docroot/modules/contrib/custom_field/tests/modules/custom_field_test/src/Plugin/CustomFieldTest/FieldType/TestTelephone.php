<?php

declare(strict_types=1);

namespace Drupal\custom_field_test\Plugin\CustomFieldTest\FieldType;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\custom_field_test\Attribute\TestFieldType;
use Drupal\custom_field_test\Plugin\FieldTypeTestBase;

/**
 * Plugin implementation of the 'telephone' field type test.
 */
#[TestFieldType(
  id: 'telephone',
  label: new TranslatableMarkup('Telephone'),
)]
class TestTelephone extends FieldTypeTestBase {

  /**
   * {@inheritdoc}
   */
  public function getDefaultWidget(): array {
    return [
      'id' => 'telephone',
      'class' => 'Drupal\custom_field\Plugin\CustomField\FieldWidget\TelephoneWidget',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultFormatter(): array {
    return [
      'id' => 'telephone_link',
      'class' => 'Drupal\custom_field\Plugin\CustomField\FieldFormatter\TelephoneLinkFormatter',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function testCases(string $name, array $settings): array {
    return [
      $this->buildTestCase($name, '+0123456789'),
      $this->buildTestCase($name, '+41' . rand(1000000, 9999999)),
    ];
  }

}
