<?php

declare(strict_types=1);

namespace Drupal\custom_field_test\Plugin\CustomFieldTest\FieldType;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\custom_field_test\Attribute\TestFieldType;
use Drupal\custom_field_test\Plugin\FieldTypeTestBase;

/**
 * Plugin implementation of the 'email' field type test.
 */
#[TestFieldType(
  id: 'email',
  label: new TranslatableMarkup('Email'),
)]
class TestEmail extends FieldTypeTestBase {

  /**
   * {@inheritdoc}
   */
  public function getDefaultWidget(): array {
    return [
      'id' => 'email',
      'class' => 'Drupal\custom_field\Plugin\CustomField\FieldWidget\EmailWidget',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultFormatter(): array {
    return [
      'id' => 'email_mailto',
      'class' => 'Drupal\custom_field\Plugin\CustomField\FieldFormatter\MailToFormatter',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function testCases(string $name, array $settings): array {
    return [
      $this->buildTestCase($name, 'test@example.com'),
      $this->buildTestCase($name, 'test2@example.com'),
    ];
  }

}
