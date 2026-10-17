<?php

declare(strict_types=1);

namespace Drupal\Tests\smart_date_recur\Kernel;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\smart_date_recur\Plugin\Field\FieldFormatter\SmartDateRecurrenceFormatter;
use Drupal\Tests\field\Kernel\FieldKernelTestBase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * Tests exposed-filter parameter resolution in SmartDateRecurrenceFormatter.
 *
 * Covers the configurable `filter_parameter_key` setting, and its fallback
 * to a parameter derived from the field name when that setting is empty.
 *
 * @group smart_date_recur
 */
#[Group('smart_date_recur')]
#[RunTestsInSeparateProcesses]
class SmartDateRecurrenceFormatterFilterTest extends FieldKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'datetime',
    'options',
    'smart_date',
    'smart_date_recur',
  ];

  /**
   * Base timestamp (2026-04-15 00:00:00 UTC) for the first test instance.
   */
  private int $base;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['smart_date']);
    $this->base = (new \DateTime('2026-04-15 00:00:00', new \DateTimeZone('UTC')))->getTimestamp();
  }

  /**
   * Creates a smartdate field on entity_test.
   */
  private function createSmartDateField(string $field_name): FieldConfig {
    $storage = FieldStorageConfig::create([
      'field_name' => $field_name,
      'entity_type' => 'entity_test',
      'type' => 'smartdate',
      'cardinality' => -1,
    ]);
    $storage->save();

    $field = FieldConfig::create([
      'field_storage' => $storage,
      'bundle' => 'entity_test',
      'label' => 'Event date',
    ]);
    $field->save();

    return $field;
  }

  /**
   * Creates an entity with 4 sequential, hour-apart, 30 minute instances.
   */
  private function createTestEntity(string $field_name): EntityTest {
    $values = [];
    for ($i = 0; $i < 4; $i++) {
      $start = $this->base + ($i * 3600);
      $values[] = [
        'value' => $start,
        'end_value' => $start + 1800,
      ];
    }
    $entity = EntityTest::create([
      'name' => $this->randomMachineName(),
      $field_name => $values,
    ]);
    $entity->save();
    return $entity;
  }

  /**
   * Builds a formatter instance configured for a tight display window.
   *
   * Past_display and upcoming_display are both set to 1 so the rendered
   * output pinpoints exactly which instance the min-date cutoff landed
   * before and after.
   */
  private function createFormatter(FieldConfig $field, string $filter_parameter_key): SmartDateRecurrenceFormatter {
    $configuration = [
      'field_definition' => $field,
      'settings' => [
        'past_display' => 1,
        'upcoming_display' => 1,
        'show_next' => FALSE,
        'current_upcoming' => FALSE,
        'force_chronological' => TRUE,
        'filter_parameter_key' => $filter_parameter_key,
      ] + SmartDateRecurrenceFormatter::defaultSettings(),
      'label' => 'above',
      'view_mode' => 'default',
      'third_party_settings' => [],
    ];
    return SmartDateRecurrenceFormatter::create(
      $this->container,
      $configuration,
      'smartdate_recurring',
      ['id' => 'smartdate_recurring']
    );
  }

  /**
   * Pushes a request exposing the given query parameter.
   */
  private function pushRequest(string $key, string $value): void {
    $request = Request::create('/entity_test', 'GET', [$key => $value]);
    $request->setSession(new Session(new MockArraySessionStorage()));
    $this->container->get('request_stack')->push($request);
  }

  /**
   * Asserts the min-date cutoff landed between the 2nd and 3rd instance.
   *
   * With past_display and upcoming_display both set to 1, that's only true
   * if delta 1 (the 2nd instance) is shown as the past instance and delta 2
   * (the 3rd instance) as the upcoming one.
   */
  private function assertCutoffBetweenSecondAndThirdInstance(array $elements): void {
    $output = reset($elements);
    $this->assertArrayHasKey('#past_display', $output);
    $this->assertSame([1], array_keys($output['#past_display']['#items']));
    $this->assertArrayHasKey('#upcoming_display', $output);
    $this->assertSame([2], array_keys($output['#upcoming_display']['#items']));
  }

  /**
   * Tests that a configured filter_parameter_key sets the min-date cutoff.
   *
   * This should hold regardless of the field's machine name.
   */
  public function testConfiguredFilterParameterKeyIsUsed(): void {
    $field = $this->createSmartDateField('field_event_date');
    $entity = $this->createTestEntity('field_event_date');

    $cutoff = $this->base + (int) (1.5 * 3600);
    $this->pushRequest('when', gmdate('Y-m-d\TH:i:s\Z', $cutoff));

    $formatter = $this->createFormatter($field, 'when');
    $elements = $formatter->viewElements($entity->get('field_event_date'), 'en');

    $this->assertCutoffBetweenSecondAndThirdInstance($elements);
  }

  /**
   * Tests that the configured filter_parameter_key takes priority.
   *
   * A request parameter matching the configured filter_parameter_key must
   * take priority even when a field-name-derived parameter is also present,
   * and an unmatched field-name-derived parameter must be ignored.
   */
  public function testConfiguredFilterParameterKeyTakesPriorityOverFieldName(): void {
    $field = $this->createSmartDateField('field_event_date');
    $entity = $this->createTestEntity('field_event_date');

    $cutoff = $this->base + (int) (1.5 * 3600);
    $wrong_cutoff = $this->base + (int) (3.5 * 3600);
    $request = Request::create('/entity_test', 'GET', [
      'when' => gmdate('Y-m-d\TH:i:s\Z', $cutoff),
      'event_date' => gmdate('Y-m-d\TH:i:s\Z', $wrong_cutoff),
    ]);
    $request->setSession(new Session(new MockArraySessionStorage()));
    $this->container->get('request_stack')->push($request);

    $formatter = $this->createFormatter($field, 'when');
    $elements = $formatter->viewElements($entity->get('field_event_date'), 'en');

    $this->assertCutoffBetweenSecondAndThirdInstance($elements);
  }

  /**
   * Tests fallback to a field-name-derived parameter without its prefix.
   *
   * When filter_parameter_key is left empty, the formatter should fall back
   * to a parameter derived by stripping the `field_` prefix from the field
   * name, preserving filtering for fields that follow Drupal's naming
   * convention without requiring explicit configuration.
   */
  public function testEmptyFilterParameterKeyFallsBackToFieldNameWithoutPrefix(): void {
    $field = $this->createSmartDateField('field_event_date');
    $entity = $this->createTestEntity('field_event_date');

    $cutoff = $this->base + (int) (1.5 * 3600);
    $this->pushRequest('event_date', gmdate('Y-m-d\TH:i:s\Z', $cutoff));

    $formatter = $this->createFormatter($field, '');
    $elements = $formatter->viewElements($entity->get('field_event_date'), 'en');

    $this->assertCutoffBetweenSecondAndThirdInstance($elements);
  }

  /**
   * Tests fallback to the full field name when there's no prefix to strip.
   *
   * When filter_parameter_key is empty and the field name has no `field_`
   * prefix to strip, the formatter should fall back to the full field name,
   * matching the previous (pre-feature) filtering behavior.
   */
  public function testEmptyFilterParameterKeyFallsBackToFullFieldName(): void {
    $field = $this->createSmartDateField('eventdate');
    $entity = $this->createTestEntity('eventdate');

    $cutoff = $this->base + (int) (1.5 * 3600);
    $this->pushRequest('eventdate', gmdate('Y-m-d\TH:i:s\Z', $cutoff));

    $formatter = $this->createFormatter($field, '');
    $elements = $formatter->viewElements($entity->get('eventdate'), 'en');

    $this->assertCutoffBetweenSecondAndThirdInstance($elements);
  }

}
