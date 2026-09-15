<?php

declare(strict_types=1);

namespace Drupal\custom_field_test\Plugin\CustomFieldTest\FieldType;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\custom_field_test\Attribute\TestFieldType;
use Drupal\custom_field_test\Plugin\FieldTypeTestBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'uuid' field type test.
 */
#[TestFieldType(
  id: 'uuid',
  label: new TranslatableMarkup('UUID'),
)]
class TestUuid extends FieldTypeTestBase {

  /**
   * The Uuid service.
   *
   * @var \Drupal\Component\Uuid\UuidInterface
   */
  protected UuidInterface $uuidService;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->uuidService = $container->get('uuid');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultWidget(): array {
    return [
      'id' => 'uuid',
      'class' => 'Drupal\custom_field\Plugin\CustomField\FieldWidget\UuidWidget',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultFormatter(): array {
    return [
      'id' => 'string',
      'class' => 'Drupal\custom_field\Plugin\CustomField\FieldFormatter\StringFormatter',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function testCases(string $name, array $settings): array {
    $uuid = $this->uuidService->generate();
    return [
      $this->buildTestCase($name, $uuid),
    ];
  }

}
