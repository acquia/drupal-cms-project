<?php

namespace Drupal\Tests\smart_date\Unit;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\DataProvider;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\smart_date\Plugin\Field\FieldType\SmartDateFieldItemList;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Unit tests for SmartDateFieldItemList helper methods.
 *
 * @coversDefaultClass \Drupal\smart_date\Plugin\Field\FieldType\SmartDateFieldItemList
 * @group smart_date
 */
#[Group('smart_date')]
class SmartDateFieldItemListTest extends UnitTestCase {

  /**
   * Reflected validateDate method.
   *
   * @var \ReflectionMethod
   */
  protected \ReflectionMethod $validateDate;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // DrupalDateTime::__construct() calls \Drupal::languageManager() unless
    // 'langcode' is in $settings.  processDefaultValue() creates DrupalDateTime
    // without a langcode, so a minimal container is required.
    $language = $this->createMock(LanguageInterface::class);
    $language->method('getId')->willReturn('en');

    $language_manager = $this->createMock(LanguageManagerInterface::class);
    $language_manager->method('getCurrentLanguage')->willReturn($language);

    $container = new ContainerBuilder();
    $container->set('language_manager', $language_manager);
    \Drupal::setContainer($container);

    $this->validateDate = (new \ReflectionClass(SmartDateFieldItemList::class))
      ->getMethod('validateDate');
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
   * @dataProvider validateDateProvider
   */
  #[DataProvider('validateDateProvider')]
  public function testValidateDate(string $value, bool $expected): void {
    $this->assertSame($expected, $this->validateDate->invoke(NULL, $value));
  }

  /**
   * Data provider for testValidateDate().
   */
  public static function validateDateProvider(): array {
    return [
      'valid date' => ['2024-01-15', TRUE],
      'leap day' => ['2024-02-29', TRUE],
      'invalid leap day' => ['2023-02-29', FALSE],
      'invalid month' => ['2024-13-15', FALSE],
      'invalid day' => ['2024-01-32', FALSE],
      'missing padding' => ['2024-1-5', FALSE],
      'embedded date' => ['before 2024-01-15 after', FALSE],
      'datetime value' => ['2024-01-15T10:00:00', FALSE],
    ];
  }

  // ---------------------------------------------------------------------------
  // processDefaultValue
  // ---------------------------------------------------------------------------

  /**
   * Returns entity and definition mocks for processDefaultValue() tests.
   */
  private function defaultValueArgs(): array {
    return [
      $this->createMock(FieldableEntityInterface::class),
      $this->createMock(FieldDefinitionInterface::class),
    ];
  }

  /**
   * @covers ::processDefaultValue
   */
  public function testProcessDefaultValueEmptyTypeReturnedUnchanged(): void {
    [$entity, $definition] = $this->defaultValueArgs();

    $literal = [['default_date_type' => '', 'default_duration' => 60]];
    $result = SmartDateFieldItemList::processDefaultValue($literal, $entity, $definition);

    $this->assertSame($literal, $result, 'Literal should be passed through unchanged when no default_date_type is set.');
  }

  /**
   * A fixed UTC string with 'relative' type produces deterministic timestamps.
   *
   * The 'relative' code path does no special branching — it just feeds
   * default_date into DrupalDateTime — so a fixed absolute string is a valid
   * representative input.
   *
   * @covers ::processDefaultValue
   */
  public function testProcessDefaultValueRelativeTypeComputesTimestamps(): void {
    [$entity, $definition] = $this->defaultValueArgs();

    $duration = 90;
    // Seconds are 00, so the "round up" logic inside processDefaultValue adds
    // nothing and the result is fully predictable.
    $fixed_date = '2025-03-15T10:00:00';
    $literal = [[
      'default_date_type' => 'relative',
      'default_date' => $fixed_date,
      'default_duration' => $duration,
    ],
    ];

    $result = SmartDateFieldItemList::processDefaultValue($literal, $entity, $definition);

    $this->assertArrayHasKey(0, $result);
    $this->assertArrayHasKey('value', $result[0]);
    $this->assertArrayHasKey('end_value', $result[0]);
    $this->assertInstanceOf(DrupalDateTime::class, $result[0]['date']);

    $expected_start = (new \DateTimeImmutable($fixed_date, new \DateTimeZone(DateTimeItemInterface::STORAGE_TIMEZONE)))->getTimestamp();
    $this->assertSame($expected_start, $result[0]['value']);
    $this->assertSame($expected_start + $duration * 60, $result[0]['end_value']);
  }

  /**
   * The 'now' type produces a timestamp on a minute boundary.
   *
   * The method rounds sub-minute seconds up to the next minute.
   *
   * @covers ::processDefaultValue
   */
  public function testProcessDefaultValueNowTypeReturnsMinuteBoundaryWithCorrectDuration(): void {
    [$entity, $definition] = $this->defaultValueArgs();

    $duration = 60;
    $literal = [[
      'default_date_type' => 'now',
      'default_date' => 'now',
      'default_duration' => $duration,
    ],
    ];

    $before = time();
    $result = SmartDateFieldItemList::processDefaultValue($literal, $entity, $definition);
    // Allow up to 60 s for the second-rounding to push past $after.
    $after = time() + 60;

    $this->assertArrayHasKey('value', $result[0]);
    $this->assertArrayHasKey('end_value', $result[0]);

    // Start is at a minute boundary (sub-minute seconds are rounded away).
    $this->assertSame(0, $result[0]['value'] % 60, 'Start timestamp must be on a minute boundary.');

    // Start is at or after the moment we captured $before.
    $this->assertGreaterThanOrEqual($before, $result[0]['value']);
    $this->assertLessThanOrEqual($after, $result[0]['value']);

    // End is exactly start + duration minutes.
    $this->assertSame($result[0]['value'] + $duration * 60, $result[0]['end_value']);
  }

  /**
   * The 'next_hour' type produces a round clock-hour start in the future.
   *
   * End value equals start + duration minutes.
   *
   * @covers ::processDefaultValue
   */
  public function testProcessDefaultValueNextHourTypeRoundsToNextHour(): void {
    [$entity, $definition] = $this->defaultValueArgs();

    $duration = 30;
    $literal = [[
      'default_date_type' => 'next_hour',
      'default_date' => 'now',
      'default_duration' => $duration,
    ],
    ];

    $before = time();
    $result = SmartDateFieldItemList::processDefaultValue($literal, $entity, $definition);

    $this->assertArrayHasKey('value', $result[0]);

    // Result is at a minute boundary (same rounding as 'now', then aligned to
    // the hour by the +1h / -minutes logic).
    $this->assertSame(0, $result[0]['value'] % 60, 'next_hour start must be on a minute boundary.');

    // Result is strictly after the moment we entered the call.
    $this->assertGreaterThan($before, $result[0]['value']);

    // Result is no more than 2 hours into the future.
    $this->assertLessThanOrEqual($before + 7200, $result[0]['value']);

    // End = start + duration.
    $this->assertSame($result[0]['value'] + $duration * 60, $result[0]['end_value']);
  }

}
