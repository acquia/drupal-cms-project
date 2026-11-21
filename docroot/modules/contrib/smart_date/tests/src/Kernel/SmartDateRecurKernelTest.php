<?php

namespace Drupal\Tests\smart_date\Kernel;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityFormInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\smart_date\Plugin\Field\FieldWidget\SmartDateDefaultWidget;
use Drupal\smart_date_recur\Entity\SmartDateRule;
use Drupal\Tests\field\Kernel\FieldKernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests Smart Date recurring behavior.
 */
#[Group('smart_date')]
#[RunTestsInSeparateProcesses]
class SmartDateRecurKernelTest extends FieldKernelTestBase {

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
   * The field name used by the tests.
   *
   * @var string
   */
  protected string $fieldName = 'field_smart_date';

  /**
   * The field config used by the tests.
   *
   * @var \Drupal\field\Entity\FieldConfig
   */
  protected FieldConfig $field;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('smart_date_rule');
    $this->installEntitySchema('smart_date_override');

    $this->config('system.date')
      ->set('timezone.default', 'UTC')
      ->save();

    $field_storage = FieldStorageConfig::create([
      'field_name' => $this->fieldName,
      'type' => 'smartdate',
      'entity_type' => 'entity_test',
      'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
    ]);
    $field_storage->save();

    $this->field = FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => 'entity_test',
      'label' => 'Smart date',
    ]);
    $this->field
      ->setThirdPartySetting('smart_date_recur', 'allow_recurring', TRUE)
      ->setThirdPartySetting('smart_date_recur', 'month_limit', 2)
      ->save();
  }

  /**
   * Tests recurring rules use the field host entity, not the form entity.
   */
  public function testRecurringRuleUsesFieldHostEntity(): void {
    $values = $this->massageRecurringValues();
    $rule = $this->loadFirstRule($values);

    $this->assertSame('entity_test', $rule->get('entity_type')->getString());
    $this->assertSame('entity_test', $rule->get('bundle')->getString());
    $this->assertSame($this->fieldName, $rule->get('field_name')->getString());
  }

  /**
   * Tests a rule resolves the configured month limit from its field config.
   */
  public function testMonthsLimitResolvesFromRuleFieldConfig(): void {
    $values = $this->massageRecurringValues();
    $rule = $this->loadFirstRule($values);

    $month_limit = \Drupal::service('smart_date_recur.manager')->getMonthsLimit($rule);

    $this->assertSame(2, (int) $month_limit);
  }

  /**
   * Tests unlimited rule instances use the configured month limit by default.
   */
  public function testRuleInstancesUseConfiguredMonthLimitByDefault(): void {
    $timezone = new \DateTimeZone('UTC');
    $start = new DrupalDateTime('yesterday 09:00:00', $timezone);
    $end = (clone $start)->modify('+1 hour');
    $values = $this->massageRecurringValues([
      'value' => $start,
      'end_value' => $end,
      'repeat-end' => '',
      'repeat-end-count' => '',
    ]);
    $rule = $this->loadFirstRule($values);

    $default_instances = $rule->getRuleInstances();
    $extended_instances = $rule->getRuleInstances(strtotime('+10 years'));

    $this->assertNotEmpty($default_instances);
    $this->assertGreaterThan(count($default_instances), count($extended_instances));
    $this->assertLessThanOrEqual(strtotime('+2 months'), max(array_column($default_instances, 'value')));
    $this->assertGreaterThan(strtotime('+2 months'), max(array_column($extended_instances, 'value')));
  }

  /**
   * Tests a rule can find its parent entity through field storage.
   */
  public function testRuleFindsParentEntityFromFieldStorage(): void {
    $values = $this->massageRecurringValues();
    $rule = $this->loadFirstRule($values);
    $field_values = array_map([$this, 'filterFieldValue'], $values);

    $entity = EntityTest::create([
      'name' => $this->randomMachineName(),
      $this->fieldName => $field_values,
    ]);
    $entity->save();

    $this->assertSame((string) $entity->id(), (string) $rule->getParentEntity(TRUE));
    $this->assertSame($entity->id(), $rule->getParentEntity()->id());
  }

  /**
   * Tests the widget counts one row per rule rather than one per instance.
   */
  public function testWidgetCollapsesRecurringInstances(): void {
    $values = $this->massageRecurringValues(['repeat-end-count' => 5]);
    $field_values = array_map([$this, 'filterFieldValue'], $values);
    $this->assertCount(5, $field_values);
    // Add a one-off date after the recurring instances.
    $field_values[] = [
      'value' => strtotime('2026-02-01 09:00:00 UTC'),
      'end_value' => strtotime('2026-02-01 10:00:00 UTC'),
      'duration' => 60,
    ];

    $entity = EntityTest::create([
      'name' => $this->randomMachineName(),
      $this->fieldName => $field_values,
    ]);
    $entity->save();

    $widget = $this->createWidget();
    $items = $entity->get($this->fieldName);
    $form = ['#parents' => []];
    $form_state = new FormState();
    $elements = $widget->form($items, $form, $form_state);

    $this->assertCount(2, $items);
    $this->assertSame($field_values[0]['rrule'], $items[0]->rrule);
    $this->assertEmpty($items[1]->rrule);
    $field_state = SmartDateDefaultWidget::getWidgetState([], $this->fieldName, $form_state);
    $this->assertSame(2, $field_state['items_count']);
    // Every rendered row is a visible one, at contiguous deltas.
    $this->assertSame([0, 1], array_values(array_filter(array_keys($elements['widget']), 'is_int')));
  }

  /**
   * Creates a Smart Date widget for the test field with stubbed rows.
   *
   * Rows are stubbed so the multiple-value handling can be exercised without
   * building a full date widget form tree.
   *
   * @return \Drupal\smart_date\Plugin\Field\FieldWidget\SmartDateDefaultWidget
   *   The widget.
   */
  protected function createWidget(): SmartDateDefaultWidget {
    return new class(
      'smartdate_default',
      ['multiple_values' => FALSE],
      $this->field,
      SmartDateDefaultWidget::defaultSettings(),
      [],
      $this->container->get('entity_type.manager')
    ) extends SmartDateDefaultWidget {

      /**
       * {@inheritdoc}
       */
      protected function formSingleElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
        return $element + ['#markup' => 'item-' . $delta];
      }

    };
  }

  /**
   * Massages a recurring Smart Date value through the field widget.
   *
   * @param array $overrides
   *   Values to override before massaging.
   *
   * @return array
   *   The massaged field values.
   */
  protected function massageRecurringValues(array $overrides = []): array {
    $widget = new SmartDateDefaultWidget(
      'smartdate_default',
      [],
      $this->field,
      [],
      [],
      $this->container->get('entity_type.manager')
    );

    $outer_entity = $this->createStub(EntityInterface::class);
    $outer_entity->method('getEntityTypeId')->willReturn('node');
    $outer_entity->method('bundle')->willReturn('article');

    $form_object = $this->createStub(EntityFormInterface::class);
    $form_object->method('getEntity')->willReturn($outer_entity);

    $form_state = new FormState();
    $form_state->setFormObject($form_object);
    $form_state->setValidationComplete(TRUE);

    $timezone = new \DateTimeZone('UTC');
    $values = [
      [
        'storage' => 'smartdate',
        'value' => new DrupalDateTime('2026-01-05 09:00:00', $timezone),
        'end_value' => new DrupalDateTime('2026-01-05 10:00:00', $timezone),
        'duration' => 60,
        'timezone' => 'UTC',
        'repeat' => 'DAILY',
        'repeat-end' => 'COUNT',
        'repeat-end-count' => 2,
        'repeat-end-date' => '',
        'interval' => 1,
        'repeat-advanced' => [],
      ],
    ];

    $values[0] = $overrides + $values[0];

    return $widget->massageFormValues($values, [], $form_state);
  }

  /**
   * Loads the first recurrence rule referenced by a field value.
   *
   * @param array $values
   *   The field values.
   *
   * @return \Drupal\smart_date_recur\Entity\SmartDateRule
   *   The loaded rule.
   */
  protected function loadFirstRule(array $values): SmartDateRule {
    foreach ($values as $value) {
      if (!empty($value['rrule'])) {
        return SmartDateRule::load($value['rrule']);
      }
    }

    $this->fail('No recurrence rule was generated.');
  }

  /**
   * Filters form-only values before assigning values to an entity field.
   *
   * @param array $value
   *   The field value.
   *
   * @return array
   *   A field storage value.
   */
  protected function filterFieldValue(array $value): array {
    return array_intersect_key($value, array_flip([
      'value',
      'end_value',
      'duration',
      'rrule',
      'rrule_index',
      'timezone',
    ]));
  }

}
