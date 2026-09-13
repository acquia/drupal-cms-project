<?php

declare(strict_types=1);

namespace Drupal\Tests\custom_field\FunctionalJavascript\FieldFormatter;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;

/**
 * Base class for JavaScript-dependent formatter tests.
 */
abstract class FormatterJavascriptTestBase extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'custom_field_test',
    'node',
    'field_ui',
  ];

  /**
   * An admin user.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * The field name.
   *
   * @var string
   */
  protected string $fieldName = 'field_test';

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'starterkit_theme';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->adminUser = $this->drupalCreateUser([
      'access content',
      'administer node display',
    ]);
  }

  /**
   * Returns the path to the manage display form.
   *
   * @return string
   *   The path to the manage display form.
   */
  protected function getManageDisplayPath(): string {
    return '/admin/structure/types/manage/custom_field_entity_test/display';
  }

}
