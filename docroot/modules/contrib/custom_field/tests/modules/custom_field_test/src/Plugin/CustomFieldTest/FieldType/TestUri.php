<?php

declare(strict_types=1);

namespace Drupal\custom_field_test\Plugin\CustomFieldTest\FieldType;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\custom_field_test\Attribute\TestFieldType;
use Drupal\custom_field_test\Plugin\FieldTypeTestBase;

/**
 * Plugin implementation of the 'uri' field type test.
 */
#[TestFieldType(
  id: 'uri',
  label: new TranslatableMarkup('Uri'),
)]
class TestUri extends FieldTypeTestBase {

  /**
   * {@inheritdoc}
   */
  public function getDefaultWidget(): array {
    return [
      'id' => 'url',
      'class' => 'Drupal\custom_field\Plugin\CustomField\FieldWidget\UrlWidget',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultFormatter(): array {
    return [
      'id' => 'uri_link',
      'class' => 'Drupal\custom_field\Plugin\CustomField\FieldFormatter\UriLinkFormatter',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function testCases(string $name, array $settings): array {
    $violation_value = 'Not a URL';
    return [
      $this->buildTestCase($name, $violation_value, TRUE, (string) $this->t("The path '@uri' is invalid.", ['@uri' => $violation_value])),
      $this->buildTestCase($name, 'https://www.drupal.com'),
    ];
  }

}
