<?php

namespace Drupal\Tests\smart_date\Kernel;

use Drupal\entity_test\Entity\EntityTest;
use Drupal\KernelTests\KernelTestBase;
use Drupal\views\Entity\View;
use Drupal\views\Views;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the Views date filter.
 *
 * @group smart_date
 */
#[Group('smart_date')]
#[RunTestsInSeparateProcesses]
class DateFilterTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'datetime',
    'entity_test',
    'options',
    'smart_date',
    'system',
    'user',
    'views',
  ];

  /**
   * A value with an explicit offset filters on exactly that moment.
   */
  public function testValueWithExplicitOffset(): void {
    $this->installEntitySchema('entity_test');
    $this->config('system.date')->set('timezone.default', 'Europe/Vienna')->save();
    date_default_timezone_set('Europe/Vienna');

    // 2026-10-12 15:23:23 UTC, 17:23:23 in Vienna. One entity a second
    // before, one at and one a second after that moment.
    $moment = 1791818603;
    $ids = [];
    foreach ([-1, 0, 1] as $offset) {
      $entity = EntityTest::create(['created' => $moment + $offset]);
      $entity->save();
      $ids[$offset] = $entity->id();
    }

    View::create([
      'id' => 'smart_date_filter_test',
      'base_table' => 'entity_test',
      'display' => [
        'default' => [
          'id' => 'default',
          'display_plugin' => 'default',
          'display_options' => [
            'filters' => [
              'created' => [
                'id' => 'created',
                'table' => 'entity_test',
                'field' => 'created',
                'entity_type' => 'entity_test',
                'entity_field' => 'created',
                'plugin_id' => 'date',
                'operator' => '>=',
                'value' => ['type' => 'date', 'granularity' => 'second'],
              ],
            ],
          ],
        ],
      ],
    ])->save();

    foreach ([
      '2026-10-12T15:23:23Z',
      '@1791818603',
      '2026-10-12T17:23:23+02:00',
    ] as $input) {
      $view = Views::getView('smart_date_filter_test');
      $view->setDisplay('default');
      $view->setHandlerOption('default', 'filter', 'created', 'value', [
        'type' => 'date',
        'granularity' => 'second',
        'value' => $input,
        'min' => '',
        'max' => '',
      ]);
      $view->execute();
      $result = array_map(fn ($row) => $row->_entity->id(), $view->result);
      sort($result);
      $this->assertSame([$ids[0], $ids[1]], $result, $input);
    }
  }

}
