<?php

declare(strict_types=1);

namespace Drupal\custom_field_test\Plugin\CustomFieldTest\FieldType;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\custom_field_test\Attribute\TestFieldType;
use Drupal\custom_field_test\Plugin\FieldTypeTestBase;

/**
 * Plugin implementation of the 'link' field type test.
 */
#[TestFieldType(
  id: 'link',
  label: new TranslatableMarkup('Link'),
)]
class TestLink extends FieldTypeTestBase {

  /**
   * {@inheritdoc}
   */
  public function getDefaultWidget(): array {
    return [
      'id' => 'link_default',
      'class' => 'Drupal\custom_field\Plugin\CustomField\FieldWidget\LinkWidget',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultFormatter(): array {
    return [
      'id' => 'link',
      'class' => 'Drupal\custom_field\Plugin\CustomField\FieldFormatter\LinkFormatter',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function testCases(string $name, array $settings): array {
    $url = 'https://www.drupal.org?test_param=test_value';
    $parsed_url = UrlHelper::parse($url);
    $properties = [
      $name,
      $name . '__title',
      $name . '__options',
    ];
    return [
      $this->buildTestCase($properties, [
        $properties[0] => $parsed_url['path'],
        $properties[1] => $this->random->machineName(),
        $properties[2] => [
          'query' => $parsed_url['query'],
          'attributes' => [
            'class' => $this->random->machineName(),
          ],
        ],
      ]),
      $this->buildTestCase($properties, [
        $properties[0] => 'https://www.drupal.org',
        $properties[1] => $this->random->machineName(),
        $properties[2] => [
          'query' => NULL,
          'attributes' => [
            'class' => $this->random->machineName(),
          ],
        ],
      ]),
      $this->buildTestCase($properties, [
        $properties[0] => 'internal:/',
        $properties[2] => [
          'query' => NULL,
        ],
      ]),
      // Test extra properties when the main property is NULL.
      $this->buildTestCase($properties, [
        $properties[1] => $this->random->machineName(),
        $properties[2] => [
          'query' => NULL,
          'attributes' => [
            'class' => $this->random->machineName(),
          ],
        ],
      ]),
      // Test access constraint.
      $this->buildTestCase($name, 'internal:/node/add', TRUE, "The path 'internal:/node/add' is inaccessible."),
    ];
  }

}
