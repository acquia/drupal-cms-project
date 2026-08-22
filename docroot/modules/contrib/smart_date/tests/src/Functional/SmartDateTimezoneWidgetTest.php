<?php

namespace Drupal\Tests\smart_date\Functional;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\NodeInterface;

/**
 * Functional browser tests for smartdate_timezone field widget.
 *
 * @group smart_date
 */
#[Group('smart_date')]
#[RunTestsInSeparateProcesses]
class SmartDateTimezoneWidgetTest extends SmartDateTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create a test content type.
    $this->drupalCreateContentType(['type' => 'smart_date_content']);

    $field_storage = FieldStorageConfig::create([
      'field_name' => 'field_date',
      'entity_type' => 'node',
      'type' => 'smartdate',
    ]);
    $field_storage->save();

    $field = FieldConfig::create([
      'field_name' => 'field_date',
      'entity_type' => 'node',
      'bundle' => 'smart_date_content',
      'label' => 'Date',
    ]);
    $field->setDefaultValue([
      'default_duration' => 60,
      'default_duration_increments' => "30\r\n60|1 hour\r\n90\r\n120|2 hours\r\ncustom",
      'default_date_type' => '',
      'default_date' => '',
      'min' => '',
      'max' => '',
    ]);
    $field->save();

    // Assign widget settings for the default form mode.
    $settings = [
      'type' => 'smartdate_timezone',
      'weight' => 122,
      'region' => 'content',
      'settings' => [
        'modal' => FALSE,
        'default_tz' => '',
        'custom_tz' => '',
        'allowed_timezones' => [],
        'default_duration' => 60,
        'default_duration_increments' => "30\r\n60|1 hour\r\n90\r\n120|2 hours\r\ncustom",
        'show_extra' => FALSE,
        'hide_date' => TRUE,
        'separator' => 'to',
        'add_abbreviations' => '',
      ],
      'third_party_settings' => [],
    ];

    /** @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface $display_repository */
    $display_repository = \Drupal::service('entity_display.repository');
    $display_repository->getFormDisplay('node', 'smart_date_content')
      ->setComponent('field_date', $settings)
      ->save();
  }

  /**
   * Tests smartdate_timezone widget default values.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   */
  public function testSmartdateTimezoneWidgetDefaults() {
    $this->drupalGet('/node/add/smart_date_content');
    $web_assert = $this->assertSession();
    // Assert an expected timezone is available to select.
    $web_assert->optionExists('field_date[0][timezone]', 'America/New_York');
  }

  /**
   * Tests smartdate_timezone widget with allowed_timezones value.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   */
  public function testSmartdateTimezoneWidgetAllowedTimezones() {
    // Change field's entity display to restrict allowed timezones.
    $allowed_timezones = [
      'America/Anchorage',
      'America/Chicago',
      'America/Denver',
      'America/Los_Angeles',
      'America/New_York',
      'America/Phoenix',
      'Asia/Manila',
      'Pacific/Guam',
      'Pacific/Honolulu',
      'Pacific/Saipan',
    ];

    /** @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface $display_repository */
    $display_repository = \Drupal::service('entity_display.repository');
    $display = $display_repository->getFormDisplay('node', 'smart_date_content');
    $component = $display->getComponent('field_date');
    $component['settings']['allowed_timezones'] = $allowed_timezones;
    $display->setComponent('field_date', $component);
    $display->save();

    $this->drupalGet('/node/add/smart_date_content');
    $web_assert = $this->assertSession();
    // Assert an expected timezone is available to select.
    $web_assert->optionExists('field_date[0][timezone]', 'America/New_York');
    // Assert a timezone outside the allowed list is not available.
    $web_assert->optionNotExists('field_date[0][timezone]', 'Europe/London');
  }

  /**
   * Tests that submitted timezone widget values are stored correctly.
   */
  public function testSmartdateTimezoneWidgetStoresSubmittedValues() {
    $this->drupalLogin(
      $this->createUser([
        'create smart_date_content content',
      ])
    );

    $this->drupalGet('/node/add/smart_date_content');
    $title = $this->randomMachineName(20);
    $edit = [
      'title[0][value]' => $title,
      'field_date[0][time_wrapper][value][date]' => '2024-01-15',
      'field_date[0][time_wrapper][value][time]' => '10:00:00',
      'field_date[0][time_wrapper][end_value][date]' => '2024-01-15',
      'field_date[0][time_wrapper][end_value][time]' => '11:00:00',
      'field_date[0][duration]' => '60',
      'field_date[0][timezone]' => 'America/New_York',
    ];

    $this->submitForm($edit, 'Save');

    $node = $this->loadNodeByTitle($title);
    $this->assertInstanceOf(NodeInterface::class, $node);

    $item = $node->get('field_date')->first();
    $this->assertSame(strtotime('2024-01-15T10:00:00-05:00'), (int) $item->value);
    $this->assertSame(strtotime('2024-01-15T11:00:00-05:00'), (int) $item->end_value);
    $this->assertSame(60, (int) $item->duration);
    $this->assertSame('America/New_York', $item->timezone);
  }

  /**
   * Loads a node by title.
   *
   * @param string $title
   *   The node title.
   *
   * @return \Drupal\node\NodeInterface|null
   *   The loaded node, or NULL.
   */
  protected function loadNodeByTitle(string $title): ?NodeInterface {
    $storage = \Drupal::entityTypeManager()->getStorage('node');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('title', $title)
      ->execute();

    if (!$ids) {
      return NULL;
    }

    return $storage->load(reset($ids));
  }

}
