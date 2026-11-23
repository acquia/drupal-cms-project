<?php

namespace Drupal\Tests\smart_date\FunctionalJavascript;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests keyboard handling of the time inputs and the duration overlay.
 *
 * @group smart_date
 * @runTestsInSeparateProcesses
 */
#[Group('smart_date')]
#[RunTestsInSeparateProcesses]
class SmartDateKeyboardTest extends WebDriverTestBase {

  /**
   * WebDriver key codes.
   */
  protected const ARROW_UP = "\u{E013}";
  protected const ARROW_DOWN = "\u{E015}";
  protected const ENTER = "\u{E007}";

  /**
   * The name prefix of the widget's inputs.
   */
  protected const PREFIX = 'field_when[0]';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['node', 'smart_date'];

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
    FieldStorageConfig::create([
      'field_name' => 'field_when',
      'entity_type' => 'node',
      'type' => 'smartdate',
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_when',
      'entity_type' => 'node',
      'bundle' => 'event',
      'label' => 'When',
      'default_value' => [
        [
          'default_duration' => 60,
          'default_duration_increments' => "30\n60|1 hour\n90\n120|2 hours\ncustom",
          'default_date_type' => '',
          'default_date' => '',
        ],
      ],
    ])->save();
    \Drupal::service('entity_display.repository')->getFormDisplay('node', 'event')
      ->setComponent('field_when', ['type' => 'smartdate_default'])
      ->save();

    $this->drupalLogin($this->drupalCreateUser([
      'access content',
      'create event content',
    ]));
    $this->drupalGet('node/add/event');
    $this->setValues([
      static::PREFIX . '[value][date]' => '2030-11-02',
      static::PREFIX . '[value][time]' => '09:00',
    ]);
    $this->assertEndTime('10:00');
  }

  /**
   * Tests that "a" and "p" set the meridiem from the hour part of a time.
   */
  public function testMeridiemKeys(): void {
    $this->assertTrue(
      $this->getSession()->evaluateScript("new Intl.DateTimeFormat(navigator.language, {hour: 'numeric'}).resolvedOptions().hour12 === true"),
      'The browser uses a 12-hour clock.',
    );
    $start = static::PREFIX . '[value][time]';

    $this->sendKeys($start, 'p');
    $this->assertSame('21:00', $this->getTime($start));
    // The end time follows the start time.
    $this->assertEndTime('22:00');

    // Pressing "p" again leaves the time unchanged.
    $this->sendKeys($start, 'p');
    $this->assertSame('21:00', $this->getTime($start));

    $this->sendKeys($start, 'a');
    $this->assertSame('09:00', $this->getTime($start));
    $this->assertEndTime('10:00');
  }

  /**
   * Tests moving through every suggestion in the overlay with the arrow keys.
   */
  public function testOverlayArrowKeys(): void {
    $end = static::PREFIX . '[end_value][time]';

    // Click into the end time so that one of its parts has focus, as the
    // browser then also steps that part on arrow keys unless prevented.
    $this->assertSession()->fieldExists($end)->click();
    $this->assertActiveSuggestion('60');
    // Record whether each key press was kept from the browser. Not every
    // browser steps a time it is being set by script, so check this directly.
    $this->getSession()->executeScript(<<<'JS'
      window.smartDateKeysPrevented = [];
      document.addEventListener('keydown', (event) => {
        window.smartDateKeysPrevented.push(event.defaultPrevented);
      });
JS);
    $this->sendKeys($end, static::ARROW_DOWN);
    $this->assertActiveSuggestion('90');
    $this->assertEndTime('10:30');

    $this->sendKeys($end, static::ARROW_DOWN);
    $this->assertActiveSuggestion('120');
    $this->assertEndTime('11:00');

    // Moving past the last suggestion wraps around to the first.
    $this->sendKeys($end, static::ARROW_DOWN);
    $this->assertActiveSuggestion('30');
    $this->assertEndTime('09:30');

    $this->sendKeys($end, static::ARROW_UP);
    $this->assertActiveSuggestion('120');
    $this->assertEndTime('11:00');

    $this->assertSame(
      [TRUE, TRUE, TRUE, TRUE],
      $this->getSession()->evaluateScript('window.smartDateKeysPrevented'),
      'The browser did not also handle the arrow keys.',
    );
  }

  /**
   * Tests closing and reopening the overlay.
   */
  public function testOverlayDismissal(): void {
    $end = static::PREFIX . '[end_value][time]';
    $page = $this->getSession()->getPage();

    // Enter closes the overlay without submitting the form.
    $this->sendKeys($end, static::ARROW_DOWN);
    $this->assertActiveSuggestion('90');
    $this->sendKeys($end, static::ENTER);
    $this->assertOverlayClosed();
    $this->assertStringEndsWith('node/add/event', $this->getSession()->getCurrentUrl());
    $this->assertEndTime('10:30');

    // An arrow key reopens it without changing the time.
    $this->sendKeys($end, static::ARROW_DOWN);
    $this->assertActiveSuggestion('90');
    $this->assertEndTime('10:30');

    // Clicking a suggestion applies it and closes the overlay.
    $page->find('css', '.smart-date--duration-list li[data-value="120"]')->click();
    $this->assertEndTime('11:00');
    $this->assertOverlayClosed();

    // The overlay comes back once the end time loses and regains focus.
    $this->sendKeys(static::PREFIX . '[value][date]', '');
    $this->sendKeys($end, '');
    $this->assertActiveSuggestion('120');
  }

  /**
   * Sends real key presses to an input, focusing it first.
   *
   * Mink's keyPress() only dispatches synthetic events, which do not trigger
   * the browser's own handling of the keys in a time input.
   */
  protected function sendKeys(string $name, string $keys): void {
    $xpath = $this->assertSession()->fieldExists($name)->getXpath();
    $element = $this->getSession()->getDriver()->getWebDriverSession()->element('xpath', $xpath);
    $element->postValue(['text' => $keys]);
  }

  /**
   * Gets the hours and minutes of a time input.
   */
  protected function getTime(string $name): string {
    return substr($this->assertSession()->fieldExists($name)->getValue(), 0, 5);
  }

  /**
   * Asserts the hours and minutes of the end time.
   */
  protected function assertEndTime(string $expected): void {
    $this->assertSame($expected, $this->getTime(static::PREFIX . '[end_value][time]'));
  }

  /**
   * Asserts that the overlay is open with the given duration highlighted.
   */
  protected function assertActiveSuggestion(string $duration): void {
    $active = $this->assertSession()->waitForElementVisible('css', '.smart-date--duration-list li.active');
    $this->assertNotNull($active, 'The duration overlay is open.');
    $this->assertSame($duration, $active->getAttribute('data-value'));
  }

  /**
   * Asserts that the overlay is closed, and stays closed.
   */
  protected function assertOverlayClosed(): void {
    // Wait past the delay used to hide the overlay when focus is lost.
    $this->getSession()->wait(500);
    $this->assertSession()->elementNotExists('css', '.smart-date--duration-list');
  }

  /**
   * Fills inputs in order, without relying on the browser's date locale.
   */
  protected function setValues(array $fields): void {
    $this->getSession()->executeScript('const fields = ' . json_encode($fields) . ';' . <<<'JS'
      for (const [name, value] of Object.entries(fields)) {
        const input = document.getElementsByName(name)[0];
        input.value = value;
        input.dispatchEvent(new Event('input', {bubbles: true}));
        input.dispatchEvent(new Event('change', {bubbles: true}));
      }
JS);
  }

}
