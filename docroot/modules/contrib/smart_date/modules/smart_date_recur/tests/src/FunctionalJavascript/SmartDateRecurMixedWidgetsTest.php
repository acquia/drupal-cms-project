<?php

namespace Drupal\Tests\smart_date_recur\FunctionalJavascript;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests widgets with and without recurring options on the same form.
 *
 * @group smart_date
 */
#[Group('smart_date')]
#[RunTestsInSeparateProcesses]
class SmartDateRecurMixedWidgetsTest extends WebDriverTestBase {

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
    $this->config('system.date')->set('timezone.default', 'UTC')->save();
    $display = \Drupal::service('entity_display.repository')->getFormDisplay('node', 'event');
    foreach (['field_recurring' => TRUE, 'field_single' => FALSE] as $field_name => $allow_recurring) {
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
        ->setThirdPartySetting('smart_date_recur', 'allow_recurring', $allow_recurring)
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
   * Tests changing the end time of a widget without recurring options.
   *
   * Any JavaScript error thrown along the way fails the test.
   */
  public function testEndTimeWithoutRecurringOptions(): void {
    $this->drupalGet('node/add/event');
    $this->assertSession()->fieldExists('field_recurring[0][repeat]');
    $this->assertSession()->fieldNotExists('field_single[0][repeat]');

    $this->getSession()->executeScript(<<<'JS'
      for (const [name, value] of Object.entries({
        'field_single[0][value][date]': '2030-11-02',
        'field_single[0][value][time]': '09:00',
      })) {
        const input = document.getElementsByName(name)[0];
        input.value = value;
        input.dispatchEvent(new Event('input', {bubbles: true}));
        input.dispatchEvent(new Event('change', {bubbles: true}));
      }
JS);

    $end = $this->assertSession()->fieldExists('field_single[0][end_value][time]');
    $end->click();
    $this->assertNotNull($this->assertSession()->waitForElementVisible('css', '.smart-date--duration-list li.active'));
    // Choose a suggestion with the arrow keys, then switch to PM, using real
    // key presses so that the change events fire as they would for a user.
    $this->getSession()->getDriver()->getWebDriverSession()
      ->element('xpath', $end->getXpath())
      ->postValue(['text' => "\u{E015}p"]);

    $this->assertSame('22:30', substr($end->getValue(), 0, 5));
  }

}
