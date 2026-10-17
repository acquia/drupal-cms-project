<?php

namespace Drupal\Tests\custom_field\Functional\FieldFormatter;

use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Tests\BrowserTestBase;

/**
 * Base class for testing custom field formatter plugins.
 *
 * Test cases provided in this class apply to all widget plugins.
 */
abstract class FormatterTestBase extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'custom_field_test',
    'node',
    'field_ui',
  ];

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * A field storage to use in this test class.
   *
   * @var \Drupal\field\FieldStorageConfigInterface
   */
  protected $fieldStorage;

  /**
   * The field used in this test class.
   *
   * @var \Drupal\Core\Field\FieldDefinitionInterface
   */
  protected $field;

  /**
   * The custom fields on the test entity bundle.
   *
   * @var array|\Drupal\Core\Field\FieldDefinitionInterface[]
   */
  protected array $fields = [];

  /**
   * The field manager service.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * An admin user.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * The custom field type manager service.
   *
   * @var \Drupal\custom_field\Plugin\CustomFieldTypeManagerInterface
   */
  protected $customFieldTypeManager;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'starterkit_theme';

  /**
   * The field name.
   *
   * @var string
   */
  protected $fieldName = 'field_test';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->adminUser = $this->drupalCreateUser(['access content', 'administer node display']);
    $this->entityFieldManager = $this->container->get('entity_field.manager');
    $this->entityTypeManager = $this->container->get('entity_type.manager');
    $this->customFieldTypeManager = $this->container->get('plugin.manager.custom_field_type');
    $this->dateFormatter = $this->container->get('date.formatter');
    $this->fields = $this->entityFieldManager->getFieldDefinitions('node', 'custom_field_entity_test');
    $this->field = $this->fields[$this->fieldName];
    $this->fieldStorage = FieldStorageConfig::loadByName('node', $this->fieldName);
  }

}
