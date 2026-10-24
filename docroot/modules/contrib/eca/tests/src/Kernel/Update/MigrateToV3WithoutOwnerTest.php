<?php

namespace Drupal\Tests\eca\Kernel\Update;

use Drupal\Core\Utility\UpdateException;
use Drupal\KernelTests\KernelTestBase;
use Drupal\eca\Entity\Eca;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the migration of ECA 2 models when their model owner is unavailable.
 *
 * The ECA model owner plugin belongs to eca_ui, but the executable ECA model
 * belongs to eca. Correcting its form event configuration must therefore not
 * depend on eca_ui being installed. The ownership handover remains pending
 * until the site operator enables eca_ui and runs database updates again.
 *
 * @see eca_post_update_migrate_to_v3()
 */
#[Group('eca')]
#[Group('eca_core')]
#[Group('eca_update')]
#[RunTestsInSeparateProcesses]
class MigrateToV3WithoutOwnerTest extends KernelTestBase {

  use Eca2FixtureTrait;

  /**
   * {@inheritdoc}
   *
   * Deliberately omits eca_ui, which provides the ECA model owner plugin.
   */
  protected static $modules = [
    'system',
    'user',
    'eca',
    'eca_form',
    'modeler_api',
  ];

  /**
   * {@inheritdoc}
   *
   * Do not redeclare strictConfigSchema: it is untyped in Drupal 11.3 and
   * typed bool in Drupal 12. Exclude only the deliberately invalid ECA 2
   * fixtures, while keeping strict checking enabled for all other config.
   */
  protected function getConfigSchemaExclusions(): array {
    return array_merge(parent::getConfigSchemaExclusions(), [
      'eca.eca.legacy_diagram',
      'eca.model.legacy_diagram',
    ]);
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    Eca::setTesting();
    parent::setUp();
    $this->installConfig(['system', 'user', 'eca']);
    $this->installEntitySchema('user');
    \Drupal::moduleHandler()->loadInclude('eca', 'php', 'eca.post_update');
  }

  /**
   * Tests that the executable model is corrected before ownership handover.
   */
  public function testRenameSurvivesMissingOwnerAndRemainsRetryable(): void {
    $this->createEca2Model('legacy_diagram', 'Label from the entity', self::MODEL_DATA);

    $this->assertMissingOwnerUpdateException();

    // The executable model belongs to ECA core and must keep working even when
    // no UI or modeler is installed.
    $configuration = \Drupal::config('eca.eca.legacy_diagram')
      ->get('events.event_build.configuration');
    $this->assertSame('user_register_form', $configuration['form_ids']);
    $this->assertArrayNotHasKey('form_id', $configuration);

    // Nothing in the ownership half has run. Both the raw diagram and legacy
    // metadata stay intact, so enabling eca_ui and repeating the update can
    // complete the handover without data loss.
    $this->assertSame(
      self::MODEL_DATA,
      \Drupal::config('eca.model.legacy_diagram')->get('modeldata')
    );
    $legacy = \Drupal::config('eca.eca.legacy_diagram');
    $this->assertSame('Label from the entity', $legacy->get('label'));
    $this->assertNull($legacy->get('third_party_settings.modeler_api'));

    // Repeating the correction is safe and reaches the same actionable error.
    $corrected = $legacy->getRawData();
    $this->assertMissingOwnerUpdateException();
    $this->assertSame(
      $corrected,
      \Drupal::config('eca.eca.legacy_diagram')->getRawData()
    );
    $this->assertSame(
      self::MODEL_DATA,
      \Drupal::config('eca.model.legacy_diagram')->get('modeldata')
    );
  }

  /**
   * Asserts that the actionable missing-owner error reaches the caller.
   */
  private function assertMissingOwnerUpdateException(): void {
    try {
      eca_post_update_migrate_to_v3();
      $this->fail('Expected an UpdateException for the missing ECA model owner.');
    }
    catch (UpdateException $e) {
      $this->assertSame(
        'The Modeler API model owner plugin for ECA is not available, so ECA models can not be migrated. Enable the ECA UI module, which provides that plugin, and run the database updates again.',
        $e->getMessage()
      );
    }
  }

}
