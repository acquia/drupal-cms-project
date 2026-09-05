<?php

namespace Drupal\Tests\smart_date\Unit;

use PHPUnit\Framework\Attributes\Group;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\smart_date\Plugin\Field\FieldWidget\SmartDateWidgetBase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Unit tests for SmartDateWidgetBase static helper methods.
 *
 * Covers:
 *   - validateDate()          (protected static — accessed via reflection)
 *   - remapDatetime()         (public static)
 *   - validateStartEnd()      (public static)
 *   - formMultipleElements()  (protected — accessed via reflection)
 *
 * @coversDefaultClass \Drupal\smart_date\Plugin\Field\FieldWidget\SmartDateWidgetBase
 * @group smart_date
 */
#[Group('smart_date')]
class SmartDateWidgetBaseTest extends UnitTestCase {

  /**
   * Reflected validateDate method (protected static).
   *
   * @var \ReflectionMethod
   */
  protected \ReflectionMethod $validateDate;

  /**
   * Reflected formMultipleElements method (protected).
   *
   * @var \ReflectionMethod
   */
  protected \ReflectionMethod $formMultipleElements;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // DrupalDateTime::__construct() calls \Drupal::languageManager() unless
    // 'langcode' is supplied in $settings.  remapDatetime() does not pass
    // langcode, so we need a minimal container here.
    $language = $this->createMock(LanguageInterface::class);
    $language->method('getId')->willReturn('en');

    $language_manager = $this->createMock(LanguageManagerInterface::class);
    $language_manager->method('getCurrentLanguage')->willReturn($language);

    $container = new ContainerBuilder();
    $container->set('language_manager', $language_manager);
    \Drupal::setContainer($container);

    $this->validateDate = (new \ReflectionClass(SmartDateWidgetBase::class))
      ->getMethod('validateDate');

    $this->formMultipleElements = (new \ReflectionClass(SmartDateWidgetBaseTestDouble::class))
      ->getMethod('formMultipleElements');
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    parent::tearDown();
    \Drupal::setContainer(new ContainerBuilder());
  }

  // ---------------------------------------------------------------------------
  // validateDate
  // ---------------------------------------------------------------------------

  /**
   * @covers ::validateDate
   */
  public function testValidateDateAcceptsYyyyMmDd(): void {
    $this->assertTrue($this->validateDate->invoke(NULL, '2024-01-15'));
    $this->assertTrue($this->validateDate->invoke(NULL, '2000-12-31'));
    $this->assertTrue($this->validateDate->invoke(NULL, '1999-02-28'));
  }

  /**
   * @covers ::validateDate
   */
  public function testValidateDateRejectsOtherFormats(): void {
    $this->assertFalse($this->validateDate->invoke(NULL, '01/15/2024'));
    $this->assertFalse($this->validateDate->invoke(NULL, '15-01-2024'));
    $this->assertFalse($this->validateDate->invoke(NULL, 'January 15, 2024'));
    $this->assertFalse($this->validateDate->invoke(NULL, '20240115'));
    $this->assertFalse($this->validateDate->invoke(NULL, ''));
  }

  // ---------------------------------------------------------------------------
  // remapDatetime
  // ---------------------------------------------------------------------------

  /**
   * @covers ::remapDatetime
   */
  public function testRemapDatetimeEmptyReturnsEmptyString(): void {
    $this->assertSame('', SmartDateWidgetBase::remapDatetime(NULL));
    $this->assertSame('', SmartDateWidgetBase::remapDatetime(''));
  }

  /**
   * @covers ::remapDatetime
   */
  public function testRemapDatetimePreservesDatetimeValue(): void {
    $tz = new \DateTimeZone('UTC');
    // Pass langcode to bypass the languageManager call inside DrupalDateTime.
    $original = new DrupalDateTime('2024-06-15T14:30:00', $tz, ['langcode' => 'en']);

    $result = SmartDateWidgetBase::remapDatetime($original, $tz);

    $this->assertInstanceOf(DrupalDateTime::class, $result);
    $this->assertSame('2024-06-15T14:30:00', $result->format('Y-m-d\TH:i:s'));
  }

  /**
   * @covers ::remapDatetime
   */
  public function testRemapDatetimeWithTimezoneShiftsRepresentation(): void {
    $utc = new \DateTimeZone('UTC');
    $eastern = new \DateTimeZone('America/New_York');
    // Source object is created in UTC.
    $original = new DrupalDateTime('2024-06-15T14:00:00', $utc, ['langcode' => 'en']);

    // Remap into Eastern — wall-clock string must stay the same because
    // remapDatetime() re-parses the formatted string in the new zone,
    // not converting the instant.
    $result = SmartDateWidgetBase::remapDatetime($original, $eastern);

    $this->assertInstanceOf(DrupalDateTime::class, $result);
    // Datetime string is preserved; only the timezone object changes.
    $this->assertSame('2024-06-15T14:00:00', $result->format('Y-m-d\TH:i:s'));
    $this->assertSame('America/New_York', $result->getTimezone()->getName());
  }

  // ---------------------------------------------------------------------------
  // validateStartEnd
  //
  // DrupalDateTime::getTimestamp() is a native PHP method inherited from
  // \DateTime, so PHPUnit cannot stub it on a mock object.  Use real
  // DrupalDateTime instances (passing 'langcode' in $settings so the
  // constructor does not call \Drupal::languageManager()).
  // ---------------------------------------------------------------------------

  /**
   * @covers ::validateStartEnd
   */
  public function testValidateStartEndNoErrorWhenStartBeforeEnd(): void {
    $tz = new \DateTimeZone('UTC');
    // '@' prefix interprets the string as a Unix timestamp — timezone-safe.
    // 10:00
    $start = new DrupalDateTime('@1705316400', $tz, ['langcode' => 'en']);
    // 11:00
    $end = new DrupalDateTime('@1705320000', $tz, ['langcode' => 'en']);

    $form_state = $this->createMock(FormStateInterface::class);
    $form_state->expects($this->never())->method('setError');

    $element = [
      'value'     => ['#value' => ['object' => $start]],
      'end_value' => ['#value' => ['object' => $end]],
      '#title'    => 'Test field',
    ];
    $complete_form = [];

    SmartDateWidgetBase::validateStartEnd($element, $form_state, $complete_form);
  }

  /**
   * @covers ::validateStartEnd
   */
  public function testValidateStartEndSetsErrorWhenStartAfterEnd(): void {
    $tz = new \DateTimeZone('UTC');
    // 11:00 (later)
    $start = new DrupalDateTime('@1705320000', $tz, ['langcode' => 'en']);
    // 10:00 (earlier)
    $end = new DrupalDateTime('@1705316400', $tz, ['langcode' => 'en']);

    $form_state = $this->createMock(FormStateInterface::class);
    $form_state->expects($this->once())->method('setError');

    $element = [
      'value'     => ['#value' => ['object' => $start]],
      'end_value' => ['#value' => ['object' => $end]],
      '#title'    => 'Test field',
    ];
    $complete_form = [];

    SmartDateWidgetBase::validateStartEnd($element, $form_state, $complete_form);
  }

  /**
   * @covers ::validateStartEnd
   */
  public function testValidateStartEndNoErrorWhenTimestampsAreEqual(): void {
    $tz    = new \DateTimeZone('UTC');
    $start = new DrupalDateTime('@1705320000', $tz, ['langcode' => 'en']);
    $end   = new DrupalDateTime('@1705320000', $tz, ['langcode' => 'en']);

    $form_state = $this->createMock(FormStateInterface::class);
    $form_state->expects($this->never())->method('setError');

    $element = [
      'value'     => ['#value' => ['object' => $start]],
      'end_value' => ['#value' => ['object' => $end]],
      '#title'    => 'Test field',
    ];
    $complete_form = [];

    SmartDateWidgetBase::validateStartEnd($element, $form_state, $complete_form);
  }

  /**
   * @covers ::validateStartEnd
   */
  public function testValidateStartEndNoErrorWhenElementsAreNotDrupalDateTime(): void {
    $form_state = $this->createMock(FormStateInterface::class);
    $form_state->expects($this->never())->method('setError');

    // Non-DrupalDateTime values must be silently skipped.
    $element = [
      'value'     => ['#value' => ['object' => NULL]],
      'end_value' => ['#value' => ['object' => NULL]],
      '#title'    => 'Test field',
    ];
    $complete_form = [];

    SmartDateWidgetBase::validateStartEnd($element, $form_state, $complete_form);
  }

  // ---------------------------------------------------------------------------
  // formMultipleElements — show_extra suppression
  //
  // Exercises the logic added in 3495251: when show_extra is FALSE the loop
  // upper bound ($max) is decremented by one so no empty trailing row appears.
  // The $max > 0 guard ensures the very first row is never suppressed.
  // ---------------------------------------------------------------------------

  /**
   * Builds a SmartDateWidgetBaseTestDouble with the given settings.
   */
  private function buildWidget(bool $show_extra, FieldDefinitionInterface $field_def): SmartDateWidgetBaseTestDouble {
    $widget = new SmartDateWidgetBaseTestDouble(
      'smart_date_default',
      [],
      $field_def,
      ['show_extra' => $show_extra],
      []
    );
    $widget->setStringTranslation($this->getStringTranslationStub());
    return $widget;
  }

  /**
   * Builds a FieldDefinitionInterface mock for an unlimited-cardinality field.
   *
   * @param string $type
   *   The field type (e.g. 'smartdate', 'daterange').
   * @param array $literal_default
   *   Value returned by getDefaultValueLiteral().
   */
  private function buildFieldDefinition(string $type, array $literal_default = []): FieldDefinitionInterface {
    $storage_def = $this->createMock(FieldStorageDefinitionInterface::class);
    $storage_def->method('getCardinality')
      ->willReturn(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED);
    $storage_def->method('isMultiple')->willReturn(TRUE);

    $field_def = $this->createMock(FieldDefinitionInterface::class);
    $field_def->method('getName')->willReturn('field_date');
    $field_def->method('getFieldStorageDefinition')->willReturn($storage_def);
    $field_def->method('getLabel')->willReturn('Date');
    $field_def->method('isRequired')->willReturn(FALSE);
    $field_def->method('getType')->willReturn($type);
    $field_def->method('getDefaultValueLiteral')->willReturn($literal_default);

    return $field_def;
  }

  /**
   * Builds a FormStateInterface mock pre-loaded with widget state.
   *
   * The widget state key for parents=[] and field_name='field_date' resolves to
   * $storage['field_storage']['#parents']['#fields']['field_date'].
   */
  private function buildFormState(int $items_count): FormStateInterface {
    $storage = [
      'field_storage' => [
        '#parents' => [
          '#fields' => [
            'field_date' => ['items_count' => $items_count],
          ],
        ],
      ],
    ];

    $form_state = $this->createMock(FormStateInterface::class);
    $form_state->method('isProgrammed')->willReturn(FALSE);
    $form_state->method('getStorage')->willReturn($storage);

    return $form_state;
  }

  /**
   * Builds a FieldItemListInterface mock pre-populated with $n_existing items.
   *
   * @param int $n_existing
   *   offsetExists() returns TRUE for deltas 0 … n_existing-1.
   * @param object $entity
   *   Returned by getEntity().
   */
  private function buildItemsMock(int $n_existing, object $entity): FieldItemListInterface {
    // stdClass is explicitly exempt from PHP 8.2 dynamic-property deprecation,
    // so _weight can be set without declaring a named class.
    $item_stub = (object) ['_weight' => NULL];

    $items = $this->createMock(FieldItemListInterface::class);
    $items->method('offsetExists')
      ->willReturnCallback(fn($delta) => $delta < $n_existing);
    $items->method('offsetGet')->willReturn($item_stub);
    $items->method('getEntity')->willReturn($entity);
    $items->method('appendItem')->willReturn($item_stub);

    return $items;
  }

  /**
   * Returns only the integer-keyed (widget row) entries from an elements array.
   */
  private function elementRows(array $elements): array {
    return array_filter($elements, fn($k) => is_int($k), ARRAY_FILTER_USE_KEY);
  }

  /**
   * @covers ::formMultipleElements
   */
  public function testShowExtraSuppressedWhenFalse(): void {
    // 2 existing items + show_extra=false → exactly 2 rows rendered.
    $field_def = $this->buildFieldDefinition('smartdate', [['default_date_type' => '', 'default_duration' => 60]]);
    $widget    = $this->buildWidget(FALSE, $field_def);
    $entity    = $this->createMock(FieldableEntityInterface::class);
    $items     = $this->buildItemsMock(2, $entity);
    $form      = ['#parents' => []];

    $result = $this->formMultipleElements->invokeArgs($widget, [$items, &$form, $this->buildFormState(2)]);

    $this->assertCount(2, $this->elementRows($result),
      'show_extra=false with 2 items must render exactly 2 rows (no trailing empty row).');
  }

  /**
   * @covers ::formMultipleElements
   */
  public function testShowExtraDisplayedWhenTrue(): void {
    // 2 existing items + show_extra=true → 3 rows rendered (2 items + extra).
    $field_def = $this->buildFieldDefinition('smartdate', [['default_date_type' => '', 'default_duration' => 60]]);
    $widget    = $this->buildWidget(TRUE, $field_def);
    $entity    = $this->createMock(FieldableEntityInterface::class);
    $items     = $this->buildItemsMock(2, $entity);
    $form      = ['#parents' => []];

    $result = $this->formMultipleElements->invokeArgs($widget, [$items, &$form, $this->buildFormState(2)]);

    $this->assertCount(3, $this->elementRows($result),
      'show_extra=true with 2 items must render 3 rows (2 items + 1 trailing empty row).');
  }

  /**
   * @covers ::formMultipleElements
   */
  public function testShowExtraDoesNotSuppressWhenNoItemsExist(): void {
    // 0 existing items + show_extra=false → the $max > 0 guard must keep the
    // sole empty row so the field remains usable.
    $field_def = $this->buildFieldDefinition('smartdate', []);
    $widget    = $this->buildWidget(FALSE, $field_def);
    $entity    = $this->createMock(FieldableEntityInterface::class);
    $items     = $this->buildItemsMock(0, $entity);
    $form      = ['#parents' => []];

    $result = $this->formMultipleElements->invokeArgs($widget, [$items, &$form, $this->buildFormState(0)]);

    $this->assertCount(1, $this->elementRows($result),
      'show_extra=false with 0 items must still render 1 row so new values can be entered.');
  }

  // ---------------------------------------------------------------------------
  // formMultipleElements — default value pre-population on appendItem
  //
  // Exercises the logic added in 3495251: when a new item slot is created via
  // appendItem(), it is pre-populated with the field's computed default value
  // rather than being left empty.
  // ---------------------------------------------------------------------------

  /**
   * @covers ::formMultipleElements
   */
  public function testNewItemSlotReceivesComputedDefaultForSmartDateType(): void {
    // Field has 1 existing item; show_extra=true opens a second slot.  That
    // slot must call appendItem() with timestamps derived from the configured
    // default_date_type.
    $duration = 90;
    // Fixed UTC datetime with seconds=00 so the "round up" path is a no-op
    // and the expected timestamps are fully deterministic.
    $fixed_date = '2025-03-15T10:00:00';
    $literal = [[
      'default_date_type' => 'relative',
      'default_date'      => $fixed_date,
      'default_duration'  => $duration,
    ],
    ];

    $field_def = $this->buildFieldDefinition('smartdate', $literal);
    $widget    = $this->buildWidget(TRUE, $field_def);
    $entity    = $this->createMock(FieldableEntityInterface::class);

    $item_stub = (object) ['_weight' => NULL];

    $items = $this->createMock(FieldItemListInterface::class);
    // Delta 0 already exists; delta 1 is the new slot.
    $items->method('offsetExists')->willReturnCallback(fn($delta) => $delta === 0);
    $items->method('offsetGet')->willReturn($item_stub);
    $items->method('getEntity')->willReturn($entity);

    $appended_value = NULL;
    $items->method('appendItem')
      ->willReturnCallback(function ($value = []) use (&$appended_value, $item_stub) {
        $appended_value = $value;
        return $item_stub;
      });

    $form = ['#parents' => []];
    $this->formMultipleElements->invokeArgs($widget, [$items, &$form, $this->buildFormState(1)]);

    $this->assertIsArray($appended_value, 'appendItem() must be called with an array.');
    $this->assertArrayHasKey('value', $appended_value);
    $this->assertArrayHasKey('end_value', $appended_value);

    $expected_start = (new \DateTimeImmutable(
      $fixed_date,
      new \DateTimeZone(DateTimeItemInterface::STORAGE_TIMEZONE)
    ))->getTimestamp();

    $this->assertSame($expected_start, $appended_value['value'],
      'Start timestamp must match the configured default date.');
    $this->assertSame($expected_start + $duration * 60, $appended_value['end_value'],
      'End timestamp must be start + duration minutes.');
  }

  /**
   * @covers ::formMultipleElements
   */
  public function testNewItemSlotReceivesEmptyArrayWhenNoDefaultDateType(): void {
    // When default_date_type is empty, appendItem() must be called with [].
    $literal = [['default_date_type' => '', 'default_duration' => 60]];

    $field_def = $this->buildFieldDefinition('smartdate', $literal);
    $widget    = $this->buildWidget(TRUE, $field_def);
    $entity    = $this->createMock(FieldableEntityInterface::class);

    $item_stub = (object) ['_weight' => NULL];

    $items = $this->createMock(FieldItemListInterface::class);
    $items->method('offsetExists')->willReturnCallback(fn($delta) => $delta === 0);
    $items->method('offsetGet')->willReturn($item_stub);
    $items->method('getEntity')->willReturn($entity);

    // Sentinel — distinguishes "not called" from "called with []".
    $appended_value = 'not-called';
    $items->method('appendItem')
      ->willReturnCallback(function ($value = []) use (&$appended_value, $item_stub) {
        $appended_value = $value;
        return $item_stub;
      });

    $form = ['#parents' => []];
    $this->formMultipleElements->invokeArgs($widget, [$items, &$form, $this->buildFormState(1)]);

    $this->assertSame([], $appended_value,
      'appendItem() must be called with [] when no default_date_type is configured.');
  }

  /**
   * @covers ::formMultipleElements
   */
  public function testNewItemSlotReceivesEmptyArrayForNonSmartDateFieldType(): void {
    // For non-smartdate field types (e.g. daterange), no default computation is
    // attempted even when default_date_type is set.
    $literal = [[
      'default_date_type' => 'relative',
      'default_date'      => '2025-03-15T10:00:00',
      'default_duration'  => 60,
    ],
    ];

    $field_def = $this->buildFieldDefinition('daterange', $literal);
    $widget    = $this->buildWidget(TRUE, $field_def);
    $entity    = $this->createMock(FieldableEntityInterface::class);

    $item_stub = (object) ['_weight' => NULL];

    $items = $this->createMock(FieldItemListInterface::class);
    $items->method('offsetExists')->willReturnCallback(fn($delta) => $delta === 0);
    $items->method('offsetGet')->willReturn($item_stub);
    $items->method('getEntity')->willReturn($entity);

    $appended_value = 'not-called';
    $items->method('appendItem')
      ->willReturnCallback(function ($value = []) use (&$appended_value, $item_stub) {
        $appended_value = $value;
        return $item_stub;
      });

    $form = ['#parents' => []];
    $this->formMultipleElements->invokeArgs($widget, [$items, &$form, $this->buildFormState(1)]);

    $this->assertSame([], $appended_value,
      'appendItem() must be called with [] for non-smartdate field types.');
  }

}
