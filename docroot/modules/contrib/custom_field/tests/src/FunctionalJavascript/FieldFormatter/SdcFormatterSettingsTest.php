<?php

declare(strict_types=1);

namespace Drupal\Tests\custom_field\FunctionalJavascript\FieldFormatter;

use Drupal\Core\Entity\Entity\EntityViewDisplay;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the SingleDirectoryComponentFormatter settings form.
 *
 * @group custom_field
 * @runTestsInSeparateProcesses
 */
#[Group('custom_field')]
#[RunTestsInSeparateProcesses]
final class SdcFormatterSettingsTest extends FormatterJavascriptTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'custom_field_test',
    'custom_field_sdc_test',
    'node',
    'field_ui',
    'sdc_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $display = EntityViewDisplay::load('node.custom_field_entity_test.default');
    $this->assertNotNull($display, 'Entity view display node.custom_field_entity_test.default must exist.');
    $display->setComponent($this->fieldName, [
      'type' => 'custom_field_sdc',
      'label' => 'above',
      'settings' => [
        'component' => '',
        'variant' => '',
        'props' => [],
        'slots' => [],
      ],
      'weight' => 1,
      'region' => 'content',
    ])->save();
    $this->drupalLogin($this->adminUser);
  }

  /**
   * Opens the SDC formatter settings form for the test field.
   */
  protected function openFormatterSettings(): void {
    $this->drupalGet($this->getManageDisplayPath());
    $fieldNameHyphen = str_replace('_', '-', $this->fieldName);
    $selector = '[data-drupal-selector="edit-fields-' . $fieldNameHyphen . '-settings-edit"]';

    // Wait for the button to be present before clicking.
    $button = $this->assertSession()->waitForElementVisible('css', $selector);
    $this->assertNotNull($button, "Formatter settings edit button not found for selector: $selector");
    $button->click();

    $this->assertSession()->waitForElementVisible('css', 'select[name*="[component]"]');
  }

  /**
   * Tests that the settings form renders correctly with no component selected.
   */
  public function testSettingsFormRendersWithNoComponent(): void {
    $this->openFormatterSettings();
    $session = $this->assertSession();
    $session->elementExists('css', 'select[name*="[component]"]');
    $session->elementNotExists('css', 'details[id*="edit-slots"]');
    $session->elementNotExists('css', 'details[id*="edit-props"]');
  }

  /**
   * Tests that selecting a component via AJAX reveals prop settings.
   */
  public function testSelectingComponentRevealsPropSettings(): void {
    $this->openFormatterSettings();
    $session = $this->assertSession();

    $this->getSession()->getPage()->selectFieldOption(
      $session->elementExists('css', 'select[name*="[component]"]')->getAttribute('name'),
      'sdc_test:my-banner',
    );

    $session->waitForElementVisible('css', 'details[id*="props"]');
    $session->elementExists('css', 'details[id*="props"]');
  }

  /**
   * Tests that selecting a component with slots reveals slot configuration.
   */
  public function testSelectingComponentWithSlotsRevealsSlotSettings(): void {
    $this->openFormatterSettings();
    $session = $this->assertSession();

    $this->getSession()->getPage()->selectFieldOption(
      $session->elementExists('css', 'select[name*="[component]"]')->getAttribute('name'),
      'sdc_test:my-banner',
    );

    $session->waitForElementVisible('css', 'details[id*="slots"]');
    $session->elementExists('css', 'details[id*="slots"]');
  }

  /**
   * Tests that changing component back to empty resets slot and prop settings.
   */
  public function testChangingComponentResetsSettings(): void {
    $display = EntityViewDisplay::load('node.custom_field_entity_test.default');
    $component = $display->getComponent($this->fieldName);
    $component['settings']['component'] = 'sdc_test:my-banner';
    $display->setComponent($this->fieldName, $component)->save();

    $this->openFormatterSettings();
    $session = $this->assertSession();

    $this->getSession()->getPage()->selectFieldOption(
      $session->elementExists('css', 'select[name*="[component]"]')->getAttribute('name'),
      '',
    );

    $this->assertSession()->assertWaitOnAjaxRequest();
    $session->elementNotExists('css', 'details[id*="slots"]');
    $session->elementNotExists('css', 'details[id*="props"]');
  }

  /**
   * Tests that formatter settings save correctly through the form.
   */
  public function testFormatterSettingsSaveThroughForm(): void {
    $this->openFormatterSettings();
    $session = $this->assertSession();

    $this->getSession()->getPage()->selectFieldOption(
      $session->elementExists('css', 'select[name*="[component]"]')->getAttribute('name'),
      'sdc_test:my-banner',
    );
    $this->assertSession()->assertWaitOnAjaxRequest();

    $this->getSession()->getPage()->pressButton('Save');

    $display = EntityViewDisplay::load('node.custom_field_entity_test.default');
    $component = $display->getComponent($this->fieldName);
    $this->assertSame('sdc_test:my-banner', $component['settings']['component']);
  }

}
