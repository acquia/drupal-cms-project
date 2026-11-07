<?php

namespace Drupal\Tests\smart_date\FunctionalJavascript;

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\node\Entity\Node;
use Drupal\smart_date_recur\Entity\SmartDateRule;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests AJAX removal of Smart Date rows containing hidden recurring instances.
 *
 * @group smart_date
 * @runTestsInSeparateProcesses
 */
#[Group('smart_date')]
#[RunTestsInSeparateProcesses]
class SmartDateRemoveRowTest extends WebDriverTestBase {

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
    FieldStorageConfig::create([
      'field_name' => 'field_dates',
      'entity_type' => 'node',
      'type' => 'smartdate',
      'cardinality' => -1,
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_dates',
      'entity_type' => 'node',
      'bundle' => 'event',
      'label' => 'Dates',
      'default_value' => [
        [
          'default_duration' => 60,
          'default_duration_increments' => "30\n60\ncustom",
          'default_date_type' => '',
          'default_date' => '',
        ],
      ],
    ])
      ->setThirdPartySetting('smart_date_recur', 'allow_recurring', TRUE)
      ->setThirdPartySetting('smart_date_recur', 'month_limit', 2)
      ->save();
    EntityFormDisplay::load('node.event.default')->setComponent('field_dates', [
      'type' => 'smartdate_default',
      'settings' => ['show_extra' => FALSE],
    ])->save();
    $this->drupalLogin($this->drupalCreateUser([
      'bypass node access',
      'access content',
    ]));
  }

  /**
   * Tests remaining rows survive removal, saving, and reopening the edit form.
   *
   * @param string[] $layout
   *   The saved row order; R and S each expand to three recurring instances,
   *   of which only the first is shown in the widget.
   * @param string $remove
   *   The row to remove.
   * @param string[] $new_rows
   *   Manual rows to append through AJAX before removing a row.
   *
   * @dataProvider removalProvider
   */
  #[DataProvider('removalProvider')]
  public function testRemoveRow(array $layout, string $remove, array $new_rows = []): void {
    $values = [];
    $deltas = [];
    $expected_items = [];
    $expected_rows = [];
    foreach ($layout as $label) {
      // The widget collapses each rule to its first instance, so every row,
      // recurring or not, occupies a single delta in the form.
      $deltas[$label] = count($deltas);
      $start = $this->startTime($label);
      if (in_array($label, ['R', 'S'], TRUE)) {
        $rule = SmartDateRule::create([
          'freq' => 'DAILY',
          'limit' => 'COUNT=3',
          'parameters' => 'INTERVAL=1',
          'entity_type' => 'node',
          'bundle' => 'event',
          'field_name' => 'field_dates',
          'start' => $start,
          'end' => $start + 3600,
        ]);
        $rule->setTimezone('UTC');
        $rule->save();
        $instances = $rule->getRuleInstances();
        $this->assertCount(3, $instances);
        foreach ($instances as $index => $instance) {
          $values[] = $instance + [
            'duration' => 60,
            'timezone' => 'UTC',
            'rrule' => $rule->id(),
            'rrule_index' => $index,
          ];
          if ($label !== $remove) {
            $expected_items[] = [(int) $instance['value'], (int) $instance['end_value'], (int) $rule->id()];
          }
        }
      }
      else {
        $values[] = [
          'value' => $start,
          'end_value' => $start + 3600,
          'duration' => 60,
          'timezone' => 'UTC',
        ];
        // Edit saved manual times in the browser to verify unsaved input too.
        if ($label !== $remove) {
          $expected_items[] = [$start + 3600, $start + 7200, 0];
        }
      }
      if ($label !== $remove) {
        $expected_rows[] = $this->expectedRow($label);
      }
    }
    $node = Node::create([
      'type' => 'event',
      'title' => 'Smart Date removal test',
      'status' => 0,
      'field_dates' => $values,
    ]);
    $node->save();
    $this->drupalGet('node/' . $node->id() . '/edit');
    $page = $this->getSession()->getPage();

    foreach ($layout as $label) {
      // Check the saved order actually produced the requested visible deltas.
      $date = $page->findField('field_dates[' . $deltas[$label] . '][value][date]');
      $this->assertNotNull($date);
      $this->assertSame(gmdate('Y-m-d', $this->startTime($label)), $date->getValue());
      if (!in_array($label, ['R', 'S'], TRUE)) {
        $this->fillManualRow($deltas[$label], $label);
      }
    }
    foreach ($new_rows as $label) {
      $page->pressButton('Add another item');
      $this->assertSession()->assertWaitOnAjaxRequest();
      $inputs = $page->findAll('css', 'input[type="date"][name$="[value][date]"]');
      $input = end($inputs);
      $this->assertNotFalse($input);
      preg_match('/^field_dates\[(\d+)\]/', $input->getAttribute('name'), $matches);
      $deltas[$label] = (int) $matches[1];
      $this->fillManualRow($deltas[$label], $label);
      if ($label !== $remove) {
        $start = $this->startTime($label);
        $expected_items[] = [$start + 3600, $start + 7200, 0];
        $expected_rows[] = $this->expectedRow($label);
      }
    }

    $page->findButton('field_dates_' . $deltas[$remove] . '_remove_button')->press();
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertRows($expected_rows);

    $page->pressButton('Save');
    $this->assertTrue($this->assertSession()->waitForText('has been updated'));
    $this->drupalGet('node/' . $node->id() . '/edit');
    $this->assertRows($expected_rows);

    // Read storage directly to avoid stale entities in the test process cache.
    $stored = $this->container->get('database')->select('node__field_dates', 'd')
      ->fields('d', ['field_dates_value', 'field_dates_end_value', 'field_dates_rrule'])
      ->condition('entity_id', $node->id())
      ->execute()->fetchAll();
    $actual_items = array_map(static fn($item) => [
      (int) $item->field_dates_value,
      (int) $item->field_dates_end_value,
      (int) $item->field_dates_rrule,
    ], $stored);
    sort($expected_items);
    sort($actual_items);
    $this->assertSame($expected_items, $actual_items);
  }

  /**
   * Provides manual and recurring row arrangements and rows to remove.
   */
  public static function removalProvider(): array {
    return [
      'original first manual' => [['R'], 'A', ['A', 'B']],
      'original last manual' => [['R'], 'B', ['A', 'B']],
      'manual before rule' => [['A', 'R'], 'A'],
      'manuals both sides remove first' => [['A', 'R', 'B'], 'A'],
      'manuals both sides remove last' => [['A', 'R', 'B'], 'B'],
      'two rules remove first manual' => [['A', 'R', 'B', 'S', 'C'], 'A'],
      'two rules remove middle manual' => [['A', 'R', 'B', 'S', 'C'], 'B'],
      'remove recurring row' => [['A', 'R', 'B'], 'R'],
      'two rules remove first rule' => [['A', 'R', 'B', 'S', 'C'], 'R'],
      'two rules remove second rule' => [['A', 'R', 'B', 'S', 'C'], 'S'],
    ];
  }

  /**
   * Returns a distinct start timestamp for each fixture row.
   */
  protected function startTime(string $label): int {
    $day = ['A' => 2, 'R' => 10, 'B' => 20, 'S' => 22, 'C' => 29][$label];
    return strtotime(sprintf('2030-11-%02d 09:00:00 UTC', $day));
  }

  /**
   * Returns the expected visible date and time inputs for a row.
   */
  protected function expectedRow(string $label): array {
    $date = gmdate('Y-m-d', $this->startTime($label));
    return in_array($label, ['R', 'S'], TRUE)
      ? [$date, '09:00', $date, '10:00']
      : [$date, '10:00', $date, '11:00'];
  }

  /**
   * Fills HTML date/time inputs without relying on the browser's date locale.
   */
  protected function fillManualRow(int $delta, string $label): void {
    $date = gmdate('Y-m-d', $this->startTime($label));
    $fields = [
      "field_dates[$delta][value][date]" => $date,
      "field_dates[$delta][value][time]" => '10:00',
      "field_dates[$delta][end_value][date]" => $date,
      "field_dates[$delta][end_value][time]" => '11:00',
    ];
    $this->getSession()->executeScript('const fields = ' . json_encode($fields) . ';' . <<<'JS'
      for (const [name, value] of Object.entries(fields)) {
        const input = document.getElementsByName(name)[0];
        input.value = value;
        input.dispatchEvent(new Event('input', {bubbles: true}));
        input.dispatchEvent(new Event('change', {bubbles: true}));
      }
JS);
    $this->getSession()->getPage()->findField('title[0][value]')->click();
  }

  /**
   * Checks all visible rows, including their end dates and times.
   */
  protected function assertRows(array $expected): void {
    $page = $this->getSession()->getPage();
    $actual = [];
    foreach ($page->findAll('css', 'input[type="date"][name$="[value][date]"]') as $input) {
      $prefix = substr($input->getAttribute('name'), 0, -strlen('[value][date]'));
      $actual[] = [
        $input->getValue(),
        substr($page->findField($prefix . '[value][time]')->getValue(), 0, 5),
        $page->findField($prefix . '[end_value][date]')->getValue(),
        substr($page->findField($prefix . '[end_value][time]')->getValue(), 0, 5),
      ];
    }
    sort($expected);
    sort($actual);
    $this->assertSame($expected, $actual);
  }

}
