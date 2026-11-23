<?php

namespace Drupal\Tests\smart_date\FunctionalJavascript;

use Drupal\Component\Utility\UrlHelper;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the widget libraries with different Smart Date widgets on one form.
 *
 * @group smart_date
 * @runTestsInSeparateProcesses
 */
#[Group('smart_date')]
#[RunTestsInSeparateProcesses]
class SmartDateWidgetLibrariesTest extends WebDriverTestBase {

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
    $display = \Drupal::service('entity_display.repository')->getFormDisplay('node', 'event');
    foreach (['field_inline' => 'smartdate_inline', 'field_date_only' => 'smartdate_only'] as $field_name => $widget) {
      FieldStorageConfig::create([
        'field_name' => $field_name,
        'entity_type' => 'node',
        'type' => 'smartdate',
        'cardinality' => -1,
      ])->save();
      FieldConfig::create([
        'field_name' => $field_name,
        'entity_type' => 'node',
        'bundle' => 'event',
        'label' => $field_name,
        'default_value' => [
          [
            'default_duration' => 60,
            'default_duration_increments' => "30\n60|1 hour\ncustom",
            'default_date_type' => '',
            'default_date' => '',
          ],
        ],
      ])->save();
      $display->setComponent($field_name, ['type' => $widget]);
    }
    $display->save();

    $this->drupalLogin($this->drupalCreateUser([
      'access content',
      'create event content',
    ]));
  }

  /**
   * Tests that each widget keeps its own behavior when both are on the page.
   */
  public function testWidgetsOnSamePage(): void {
    $this->drupalGet('node/add/event');

    $this->assertInlineRowEnhanced(0);
    $this->assertDateOnlyRowEnhanced(0);
  }

  /**
   * Tests that rows added through AJAX bring their libraries with them.
   */
  public function testAddedRowsLoadLibraries(): void {
    $this->drupalGet('node/add/event');
    $page = $this->getSession()->getPage();

    // Make the page look as though the Smart Date libraries were never
    // loaded, as when the first widget is built by an AJAX request. The AJAX
    // response must then carry the libraries for the new row to work.
    $session = $this->getSession();
    $libraries = UrlHelper::uncompressQueryParameter($session->evaluateScript('drupalSettings.ajaxPageState.libraries'));
    $libraries = array_filter(explode(',', $libraries), static fn($library) => !str_starts_with($library, 'smart_date/'));
    $session->executeScript(sprintf(
      'drupalSettings.ajaxPageState.libraries = %s; delete Drupal.behaviors.smartDate; delete Drupal.behaviors.smartDateOnly;',
      json_encode(UrlHelper::compressQueryParameter(implode(',', $libraries))),
    ));

    $page->pressButton('field_inline_add_more');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertInlineRowEnhanced(1);

    $page->pressButton('field_date_only_add_more');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertDateOnlyRowEnhanced(1);
  }

  /**
   * Checks that smart_date.js has enhanced an inline widget row.
   */
  protected function assertInlineRowEnhanced(int $delta): void {
    $prefix = "field_inline[$delta][time_wrapper]";
    $this->assertJsCondition(sprintf(
      "document.getElementsByName(%s)[0]?.closest('.smartdate--widget').querySelector('input.allday') !== null",
      json_encode($prefix . '[value][date]'),
    ));

    $this->setValues([
      $prefix . '[value][date]' => '2030-11-02',
      $prefix . '[value][time]' => '09:00',
    ]);
    $page = $this->getSession()->getPage();
    $this->assertSame('2030-11-02', $page->findField($prefix . '[end_value][date]')->getValue());
    $this->assertSame('10:00', substr($page->findField($prefix . '[end_value][time]')->getValue(), 0, 5));
  }

  /**
   * Checks that date_only.js has enhanced a date-only widget row.
   */
  protected function assertDateOnlyRowEnhanced(int $delta): void {
    $prefix = "field_date_only[$delta][time_wrapper]";
    $this->assertNotNull($this->assertSession()->waitForField($prefix . '[value][date]'));

    $this->setValues([$prefix . '[value][date]' => '2030-11-02']);
    $this->assertSame('2030-11-02', $this->getSession()->getPage()->findField($prefix . '[end_value][date]')->getValue());
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
