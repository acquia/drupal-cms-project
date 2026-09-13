<?php

namespace Drupal\Tests\eca_ui\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\KernelTests\KernelTestBase;
use Drupal\eca_ui\Form\Settings;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel tests for the ECA settings form.
 */
#[Group('eca')]
#[Group('eca_ui')]
#[RunTestsInSeparateProcesses]
class SettingsFormTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'eca',
    'eca_ui',
    'modeler_api',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installConfig(['eca']);
  }

  /**
   * Builds the settings form render array.
   *
   * @return array
   *   The built form.
   */
  protected function buildSettingsForm(): array {
    $form_object = Settings::create($this->container);
    $form_state = new FormState();
    $form = [];
    return $form_object->buildForm($form, $form_state);
  }

  /**
   * Tests that the debug settings are encapsulated in a fieldset.
   */
  public function testDebugSettingsFieldset(): void {
    $form = $this->buildSettingsForm();

    // The debug settings must live inside a dedicated container element with a
    // description that explains the values are stored in state, not config.
    $this->assertArrayHasKey('debug', $form);
    $this->assertContains($form['debug']['#type'], ['fieldset', 'details']);
    $this->assertArrayHasKey('#description', $form['debug']);
    $this->assertStringContainsStringIgnoringCase('state', (string) $form['debug']['#description']);

    // The four debug related fields must be children of the fieldset.
    foreach (['debug_mode', 'debug_data_depth', 'debug_data_cases', 'debug_test_timeout'] as $key) {
      $this->assertArrayHasKey($key, $form['debug'], sprintf('Field %s is inside the debug fieldset.', $key));
      $this->assertArrayNotHasKey($key, $form, sprintf('Field %s is no longer a top-level form element.', $key));
    }
  }

  /**
   * Tests that only the cases field is hidden until debug mode is enabled.
   */
  public function testDebugDataCasesVisibleState(): void {
    $form = $this->buildSettingsForm();

    // The cases field is only shown when debug mode is checked.
    $cases = $form['debug']['debug_data_cases'];
    $this->assertArrayHasKey('#states', $cases, 'The cases field defines a #states condition.');
    $this->assertArrayHasKey('visible', $cases['#states'], 'The cases field is conditionally visible.');
    $this->assertSame(
      ['checked' => TRUE],
      $cases['#states']['visible'][':input[name="debug_mode"]'],
      'The cases field becomes visible when debug mode is checked.',
    );

    // The debug mode checkbox, depth and timeout fields are always visible and
    // therefore must not define a #states condition.
    foreach (['debug_mode', 'debug_data_depth', 'debug_test_timeout'] as $key) {
      $this->assertArrayNotHasKey('#states', $form['debug'][$key], sprintf('Field %s is always visible.', $key));
    }
  }

  /**
   * Tests that the collapsible fieldsets are collapsed by default.
   */
  public function testFieldsetsCollapsedByDefault(): void {
    $form = $this->buildSettingsForm();

    $this->assertFalse($form['debug']['#open'], 'The debug fieldset is collapsed by default.');
    $this->assertFalse($form['dependency_calculation']['#open'], 'The dependency calculation fieldset is collapsed by default.');
  }

  /**
   * Tests that the form values are still flat and submittable.
   */
  public function testDebugSettingsRemainFlat(): void {
    $form = $this->buildSettingsForm();

    // The debug fieldset must not be a tree, so the submit handler keeps
    // reading the values by their flat keys.
    $this->assertNotTrue($form['debug']['#tree'] ?? FALSE, 'The debug fieldset is not a tree.');
  }

}
