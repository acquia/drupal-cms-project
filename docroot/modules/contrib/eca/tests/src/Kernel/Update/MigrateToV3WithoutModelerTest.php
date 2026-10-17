<?php

namespace Drupal\Tests\eca\Kernel\Update;

use Drupal\Core\Utility\UpdateException;
use Drupal\KernelTests\KernelTestBase;
use Drupal\eca\Entity\Eca;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the migration of ECA 2 models when their modeler is not installed.
 *
 * A diagram can only be read by the modeler that drew it. If that modeler is
 * missing, the migration has to leave the diagram alone and fail loudly, so
 * that Drupal keeps the update pending and it can be run again once the modeler
 * is available. Migrating regardless would write a model that has lost its
 * diagram, and no later run would ever repair it.
 *
 * @see eca_post_update_migrate_to_v3()
 */
#[Group('eca')]
#[Group('eca_core')]
#[Group('eca_update')]
#[RunTestsInSeparateProcesses]
class MigrateToV3WithoutModelerTest extends KernelTestBase {

  use Eca2FixtureTrait;

  /**
   * {@inheritdoc}
   *
   * Deliberately omits the stand-in for the BPMN.iO modeler, so that no modeler
   * can read the diagram of the fixture.
   *
   * @see \Drupal\eca_test_bpmn_io\Plugin\ModelerApiModeler\TestBpmnIo
   */
  protected static $modules = [
    'system',
    'user',
    'eca',
    'eca_form',
    'eca_ui',
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
      'eca.eca.legacy_plain',
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
   * Tests that an unreadable diagram is kept and the update stays pending.
   */
  public function testUnreadableDiagramKeepsTheModelData(): void {
    // One model that cannot be migrated, and one that can. The readable one
    // must still be migrated, so that repeating the update has less to do.
    $this->createEca2Model('legacy_diagram', 'Label from the entity', self::MODEL_DATA);
    $this->createEca2Model('legacy_plain', 'Label from the entity', NULL);

    try {
      eca_post_update_migrate_to_v3();
      $this->fail('Expected an UpdateException for the unreadable diagram.');
    }
    catch (UpdateException $e) {
      $this->assertStringContainsString('legacy_diagram', $e->getMessage());
      $this->assertStringContainsString('bpmn_io', $e->getMessage());
      $this->assertStringNotContainsString('legacy_plain', $e->getMessage());
    }

    // The diagram is untouched, so repeating the update can still migrate it.
    $rawData = \Drupal::configFactory()->get('eca.model.legacy_diagram');
    $this->assertFalse($rawData->isNew());
    $this->assertSame(self::MODEL_DATA, $rawData->get('modeldata'));

    // Its model has not been touched either, which is what keeps repeating the
    // update from skipping it as already migrated.
    $eca = Eca::load('legacy_diagram');
    $this->assertInstanceOf(Eca::class, $eca);
    $this->assertSame([], $eca->getThirdPartyProviders());

    // The model that needs no modeler was migrated all the same.
    $plain = Eca::load('legacy_plain');
    $this->assertInstanceOf(Eca::class, $plain);
    $this->assertSame('fallback', $plain->getThirdPartySetting('modeler_api', 'modeler_id'));
  }

}
