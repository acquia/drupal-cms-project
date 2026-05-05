<?php

namespace Drupal\Tests\custom_field\FunctionalJavascript;

use Drupal\custom_field\Time;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;

/**
 * Base class for testing custom field widget plugins.
 *
 * Test cases provided in this class apply to all widget plugins.
 */
abstract class CustomFieldWidgetTestBase extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'custom_field_test',
    'user',
    'system',
    'field',
    'field_ui',
    'text',
    'node',
    'path',
  ];

  /**
   * The field manager service.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The custom field generate data service.
   *
   * @var \Drupal\custom_field\CustomFieldGenerateDataInterface
   */
  protected $customFieldDataGenerator;

  /**
   * The entity display repository.
   *
   * @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface
   */
  protected $entityDisplayRepository;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * The custom fields on the test entity bundle.
   *
   * @var array|\Drupal\Core\Field\FieldDefinitionInterface[]
   */
  protected array $fields = [];

  /**
   * URL to field's storage configuration form.
   *
   * @var string
   */
  protected string $fieldStorageConfigUrl;

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function setUp(): void {
    parent::setUp();
    $this->entityFieldManager = $this->container->get('entity_field.manager');
    $this->customFieldDataGenerator = $this->container->get('custom_field.generate_data');
    $this->entityDisplayRepository = $this->container->get('entity_display.repository');
    $this->fieldStorageConfigUrl = '/admin/structure/types/manage/custom_field_entity_test/fields/node.custom_field_entity_test.field_test';

    $this->fields = $this->entityFieldManager
      ->getFieldDefinitions('node', 'custom_field_entity_test');

    $this->drupalLogin($this->drupalCreateUser([], NULL, TRUE));
  }

  /**
   * Tests the custom field widgets for current form display.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   * @throws \Behat\Mink\Exception\ResponseTextException
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  public function testWidgets(): void {
    $this->drupalGet('/node/add/custom_field_entity_test');
    $generator = $this->customFieldDataGenerator;

    // Fill out the single cardinality field.
    $form_values = $generator->generateSampleFormData($this->fields['field_test']);
    $this->submitForm($form_values, 'Save');
    $this->assertSession()->pageTextContains('Custom Field Entity Test Test has been created.');
    // Ensure the values were properly persisted.
    $this->drupalGet('/node/1/edit');
    $this->assertSession()->waitForElementVisible('css', '#edit-field-test-0');
    // Test the generated form values.
    $this->processGeneratedFormValues($form_values);

    // Fill out the multiple cardinality field.
    $form_values = $generator->generateSampleFormData(
      $this->fields['field_test_multiple'],
      [0, 1, 2]
    );
    $this->submitForm($form_values, 'Save');
    $this->assertSession()->pageTextContains('Custom Field Entity Test Test has been updated.');
    // Ensure the values were properly persisted.
    $this->drupalGet('/node/1/edit');
    $this->assertSession()->waitForElementVisible('css', '#edit-field-test-0');
    // Test the generated form values.
    $this->processGeneratedFormValues($form_values);

    // Fill out the unlimited cardinality field (and add another several times).
    $page = $this->getSession()->getPage();
    for ($i = 0; $i < 4; ++$i) {
      $page->pressButton('Add another item');
      $this->assertSession()->assertWaitOnAjaxRequest();
    }
    $form_values = $generator->generateSampleFormData(
      $this->fields['field_test_unlimited'],
      [0, 1, 2, 3, 4]
    );
    $this->submitForm($form_values, 'Save');
    $this->assertSession()->pageTextContains('Custom Field Entity Test Test has been updated.');
    // Ensure the values were properly persisted.
    $this->drupalGet('/node/1/edit');
    $this->assertSession()->waitForElementVisible('css', '#edit-field-test-0');
    // Test the generated form values.
    $this->processGeneratedFormValues($form_values);

    // Verify elements are not visible now that form has data.
    $this->drupalGet('/admin/structure/types/manage/custom_field_entity_test/fields/node.custom_field_entity_test.field_test');
    // Verify the clone settings field no longer exists.
    $this->assertSession()->elementNotExists('css', '[name="field_storage[subform][settings][clone]"]');
    // Verify the add another button is hidden.
    $this->assertSession()->elementNotExists('css', '#edit-field-storage-subform-settings-actions-add');
  }

  /**
   * Loops through form values and validates their existence.
   *
   * @param string[] $form_values
   *   An array of form values keyed by selector name.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   */
  protected function processGeneratedFormValues(array $form_values): void {
    foreach ($form_values as $subfield => $value) {
      $assert_session = $this->assertSession();
      $page = $this->getSession()->getPage();
      $assert_session->elementExists('css', '[name="' . $subfield . '"]');
      $element = $page->find('css', '[name="' . $subfield . '"]');
      $id = $element->getAttribute('id');
      $field = $assert_session->waitForField($id);
      $this->assertNotNull($field, "Field $subfield was found.");
      $saved_value = $field->getValue();
      if (str_contains($subfield, 'boolean')) {
        // The 0 value in booleans appears to be treated as null, so skip it.
        if ($saved_value) {
          $this->assertTrue($field->isChecked(), 'Field ' . $subfield . ' was not checked.');
        }
        continue;
      }
      if (str_contains($subfield, 'time')) {
        // Convert the saved value format to what was submitted.
        $saved_value = Time::createFromHtml5Format($saved_value)
          ->format('h:iA');
      }
      // Confirm what was submitted matches what was saved.
      $this->assertEquals($value, $saved_value, "Field $subfield has expected value.");
    }
  }

  /**
   * Sets the site timezone to a given timezone.
   *
   * @param string $timezone
   *   The timezone identifier to set.
   */
  protected function setSiteTimezone(string $timezone): void {
    // Set an explicit site timezone, and disallow per-user timezones.
    $this->config('system.date')
      ->set('timezone.user.configurable', 0)
      ->set('timezone.default', $timezone)
      ->save();
  }

}
