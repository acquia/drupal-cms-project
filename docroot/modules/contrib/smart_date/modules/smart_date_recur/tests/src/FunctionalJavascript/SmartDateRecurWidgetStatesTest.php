<?php

namespace Drupal\Tests\smart_date_recur\FunctionalJavascript;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the #states of recurring rule widgets with several recurring fields.
 *
 * @group smart_date
 */
#[Group('smart_date')]
#[RunTestsInSeparateProcesses]
class SmartDateRecurWidgetStatesTest extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['node', 'smart_date', 'smart_date_recur'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->drupalCreateContentType(['type' => 'event']);
    $display = \Drupal::service('entity_display.repository')->getFormDisplay('node', 'event');
    foreach (['field_alpha', 'field_beta'] as $field_name) {
      FieldStorageConfig::create([
        'field_name' => $field_name,
        'entity_type' => 'node',
        'type' => 'smartdate',
      ])->save();
      FieldConfig::create([
        'field_name' => $field_name,
        'entity_type' => 'node',
        'bundle' => 'event',
        'label' => $field_name,
      ])
        ->setDefaultValue([
          'default_duration' => 60,
          'default_duration_increments' => "30\r\n60|1 hour\r\n90\r\n120|2 hours\r\ncustom",
          'default_date_type' => '',
          'default_date' => '',
          'min' => '',
          'max' => '',
        ])
        ->setThirdPartySetting('smart_date_recur', 'allow_recurring', TRUE)
        ->setThirdPartySetting('smart_date_recur', 'month_limit', 12)
        ->save();
      $display->setComponent($field_name, ['type' => 'smartdate_default']);
    }
    $display->save();

    $this->drupalLogin($this->drupalCreateUser([
      'access content',
      'create event content',
      'make smart dates recur',
    ]));
  }

  /**
   * Tests that each repeat select only controls its own field's elements.
   */
  public function testStatesAreScopedToTheirField(): void {
    $page = $this->getSession()->getPage();
    $this->drupalGet('node/add/event');

    // Nothing repeats yet, so the rule options are hidden everywhere.
    $this->assertRuleVisible('field_alpha', 0, FALSE);
    $this->assertRuleVisible('field_beta', 0, FALSE);

    // Changing the second field must not affect the first.
    $page->selectFieldOption('field_beta[0][repeat]', 'WEEKLY');
    $this->assertRuleVisible('field_beta', 0, TRUE);
    $this->assertRuleVisible('field_alpha', 0, FALSE);

    $page->selectFieldOption('field_alpha[0][repeat]', 'MONTHLY');
    $this->assertRuleVisible('field_alpha', 0, TRUE);

    // A state that depends on both selects of the same field.
    $page->selectFieldOption('field_alpha[0][repeat-end]', 'COUNT');
    $this->assertTrue($page->findField('field_alpha[0][repeat-end-count]')->isVisible());
    $this->assertFalse($page->findField('field_beta[0][repeat-end-count]')->isVisible());

    $page->selectFieldOption('field_beta[0][repeat]', '');
    $this->assertRuleVisible('field_beta', 0, FALSE);
    $this->assertRuleVisible('field_alpha', 0, TRUE);
  }

  /**
   * Asserts whether the options of a recurring rule are visible.
   *
   * @param string $field_name
   *   The name of the field.
   * @param int $delta
   *   The delta of the field item.
   * @param bool $visible
   *   Whether the options are expected to be visible.
   */
  protected function assertRuleVisible(string $field_name, int $delta, bool $visible): void {
    $page = $this->getSession()->getPage();
    $name = $field_name . '[' . $delta . ']';
    $selector = 'edit-' . str_replace('_', '-', $field_name) . '-' . $delta . '-repeat-advanced';

    $this->assertSame($visible, $page->findField($name . '[interval]')->isVisible(), "$name interval");
    $this->assertSame($visible, $page->findField($name . '[repeat-end]')->isVisible(), "$name repeat-end");
    $this->assertSame($visible, $page->find('css', '[data-drupal-selector="' . $selector . '"]')->isVisible(), "$name advanced");
  }

}
