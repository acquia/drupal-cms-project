<?php

namespace Drupal\Tests\eca\Kernel\Update;

use Drupal\Core\Utility\UpdateException;
use Drupal\KernelTests\KernelTestBase;
use Drupal\eca\Entity\Eca;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that models coming from ECA 2 are handed over to the Modeler API.
 *
 * @see eca_post_update_migrate_to_v3()
 */
#[Group('eca')]
#[Group('eca_core')]
#[Group('eca_update')]
#[RunTestsInSeparateProcesses]
class MigrateToV3Test extends KernelTestBase {

  use Eca2FixtureTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'eca',
    'eca_form',
    'eca_ui',
    'modeler_api',
    'eca_test_bpmn_io',
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
      'eca.eca.legacy_unlabeled',
      'eca.model.gone_long_ago',
      'eca.model.legacy_diagram',
      'eca.model.legacy_removed_plugin',
      'eca.eca.legacy_removed_plugin',
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
    // Update files are not autoloaded, so pull it in the same way core does
    // before it invokes a post-update function.
    // @see update_invoke_post_update()
    \Drupal::moduleHandler()->loadInclude('eca', 'php', 'eca.post_update');
  }

  /**
   * Tests a model whose diagram was drawn in BPMN.
   */
  public function testModelWithDiagramIsHandedToTheBpmnModeler(): void {
    $this->createEca2Model('legacy_diagram', 'Label from the entity', self::MODEL_DATA);
    // Prime the entity cache with the legacy executable configuration. The raw
    // correction must invalidate it before the ownership handover saves the
    // entity, or that save would write "form_id" back over "form_ids".
    $legacy = Eca::load('legacy_diagram');
    $this->assertInstanceOf(Eca::class, $legacy);
    $this->assertArrayHasKey('form_id', $legacy->get('events')['event_build']['configuration']);

    $this->assertSame(
      'Handed 1 ECA model(s) over to the Modeler API: legacy_diagram.',
      eca_post_update_migrate_to_v3()
    );

    $eca = Eca::load('legacy_diagram');
    $this->assertInstanceOf(Eca::class, $eca);

    // Everything the modeler read out of the diagram has to arrive in the
    // third-party settings of the Modeler API, which owns it from now on. The
    // stand-in modeler returns values that appear nowhere in the fixture, so
    // these can only have come through the modeler.
    $this->assertSame(['modeler_api'], $eca->getThirdPartyProviders());
    $this->assertSame('bpmn_io', $eca->getThirdPartySetting('modeler_api', 'modeler_id'));
    $this->assertSame('Label from the diagram', $eca->getThirdPartySetting('modeler_api', 'label'));
    $this->assertSame('Changelog from the diagram', $eca->getThirdPartySetting('modeler_api', 'changelog'));
    $this->assertSame('Documentation from the diagram', $eca->getThirdPartySetting('modeler_api', 'documentation'));
    $this->assertSame(['tag-from-the-diagram'], $eca->getThirdPartySetting('modeler_api', 'tags'));
    $this->assertSame('0.9.7', $eca->getThirdPartySetting('modeler_api', 'version'));
    // The status is a property of the entity rather than a third-party setting,
    // and the diagram is what decides it.
    $this->assertTrue($eca->status());
    // ECA 3 reads the label back out of the third-party settings.
    $this->assertSame('Label from the diagram', $eca->label());

    // The raw diagram has to survive the hand-over, and the renaming of the
    // form event configuration has to have reached it. Asking the model owner
    // for it covers whichever storage method it picked.
    $data = $this->modelOwner()->getModelData($eca);
    $this->assertStringContainsString('camunda:field name="form_ids"', $data);
    $this->assertStringNotContainsString('camunda:field name="form_id"', $data);

    // The same renaming in the executable model.
    $configuration = $eca->get('events')['event_build']['configuration'];
    $this->assertSame('user_register_form', $configuration['form_ids']);
    $this->assertArrayNotHasKey('form_id', $configuration);

    // The raw model data of ECA 2 has no schema in ECA 3, so it must not be
    // left behind once its content has been carried over.
    $this->assertTrue(\Drupal::configFactory()->get('eca.model.legacy_diagram')->isNew());
    $this->assertSame([], \Drupal::configFactory()->listAll('eca.model.'));

    // Whatever the migration wrote has to be valid ECA 3 configuration. This is
    // what proves the renaming of "form_id" was necessary and complete: the
    // form event only declares "form_ids".
    $this->assertMigratedConfigMatchesSchema('eca.eca.legacy_diagram');
  }

  /**
   * Tests a model that never had a diagram.
   */
  public function testModelWithoutDiagramFallsBackToTheGenericModeler(): void {
    $this->createEca2Model('legacy_plain', 'Label from the entity', NULL);

    $this->assertSame(
      'Handed 1 ECA model(s) over to the Modeler API: legacy_plain.',
      eca_post_update_migrate_to_v3()
    );

    $eca = Eca::load('legacy_plain');
    $this->assertInstanceOf(Eca::class, $eca);

    // With no diagram to read there is nothing for a real modeler to render,
    // so the model is handed to the generic fallback of the Modeler API. The
    // label of the entity is the one property worth carrying over, because ECA
    // 3 no longer keeps it as a property.
    $this->assertSame(['modeler_api'], $eca->getThirdPartyProviders());
    $this->assertSame('fallback', $eca->getThirdPartySetting('modeler_api', 'modeler_id'));
    $this->assertSame('Label from the entity', $eca->getThirdPartySetting('modeler_api', 'label'));
    $this->assertSame('Label from the entity', $eca->label());
    // The remaining properties have nothing to be filled from. Reading them
    // back through the model owner is the contract that matters, because the
    // Modeler API stores no third-party setting at all for an empty value.
    $owner = $this->modelOwner();
    $this->assertSame('', $owner->getChangelog($eca));
    $this->assertSame('', $owner->getDocumentation($eca));
    $this->assertSame([], $owner->getTags($eca));
    $this->assertSame('', $owner->getVersion($eca));
    $this->assertSame('', $owner->getModelData($eca));

    // The executable model is renamed regardless of how it was drawn.
    $configuration = $eca->get('events')['event_build']['configuration'];
    $this->assertSame('user_register_form', $configuration['form_ids']);
    $this->assertArrayNotHasKey('form_id', $configuration);

    $this->assertMigratedConfigMatchesSchema('eca.eca.legacy_plain');
  }

  /**
   * Tests that a model without a label falls back to its own ID.
   */
  public function testModelWithoutLabelFallsBackToItsId(): void {
    $this->createEca2Model('legacy_unlabeled', '', NULL);

    eca_post_update_migrate_to_v3();

    $eca = Eca::load('legacy_unlabeled');
    $this->assertInstanceOf(Eca::class, $eca);
    $this->assertSame('legacy_unlabeled', $eca->getThirdPartySetting('modeler_api', 'label'));
  }

  /**
   * Tests that running the migration again changes nothing.
   *
   * A site that went from ECA 2.1 through ECA 3.0 to ECA 3.1 has already been
   * migrated, but by a post-update function of a different module, so this one
   * is still pending and will run. It must recognize that work as done.
   */
  public function testMigrationIsIdempotent(): void {
    $this->createEca2Model('legacy_diagram', 'Label from the entity', self::MODEL_DATA);
    eca_post_update_migrate_to_v3();
    $migrated = \Drupal::config('eca.eca.legacy_diagram')->getRawData();

    // Model data left behind by the migration of ECA 3.0 can be removed because
    // its model proves that the Modeler API owns it. An object whose model no
    // longer exists has no such proof and must remain available for recovery.
    $this->createEca2ModelData('legacy_diagram', self::MODEL_DATA);
    $this->createEca2ModelData('gone_long_ago', self::MODEL_DATA);

    $this->assertSame(
      'No ECA models needed to be handed over to the Modeler API.',
      eca_post_update_migrate_to_v3()
    );

    $this->assertSame($migrated, \Drupal::config('eca.eca.legacy_diagram')->getRawData());
    $this->assertTrue(\Drupal::config('eca.model.legacy_diagram')->isNew());
    $this->assertSame(
      self::MODEL_DATA,
      \Drupal::config('eca.model.gone_long_ago')->get('modeldata')
    );
  }

  /**
   * Asserts that the given configuration object matches the ECA 3 schema.
   *
   * @param string $name
   *   The name of the configuration object.
   */
  private function assertMigratedConfigMatchesSchema(string $name): void {
    $violations = \Drupal::service('config.typed')->createFromNameAndData(
      $name,
      \Drupal::config($name)->getRawData()
    )->validate();
    $messages = [];
    foreach ($violations as $violation) {
      $messages[] = $violation->getPropertyPath() . ': ' . $violation->getMessage();
    }
    $this->assertSame([], $messages, 'Migrated configuration ' . $name . ' violates the schema.');
  }

  /**
   * Tests that only the form event's form_id field is renamed in a diagram.
   *
   * The diagram-driven rename must not touch a camunda:field named "form_id"
   * that belongs to a non-form element, which a plain str_replace would have
   * silently rewritten.
   */
  public function testDiagramRenameIsScopedToFormEvents(): void {
    $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<bpmn:definitions xmlns:bpmn="http://www.omg.org/spec/BPMN/20100524/MODEL" xmlns:camunda="http://camunda.org/schema/1.0/bpmn" id="Definitions_1">
  <bpmn:process id="eca_scoped" isExecutable="true">
    <bpmn:startEvent id="event_build" name="Build the form">
      <bpmn:extensionElements>
        <camunda:properties>
          <camunda:property name="pluginid" value="form:form_build" />
        </camunda:properties>
        <camunda:field name="form_id">
          <camunda:string>user_register_form</camunda:string>
        </camunda:field>
      </bpmn:extensionElements>
    </bpmn:startEvent>
    <bpmn:serviceTask id="task_send" name="Send notification">
      <bpmn:extensionElements>
        <camunda:properties>
          <camunda:property name="pluginid" value="eca_service:send" />
        </camunda:properties>
        <camunda:field name="form_id">
          <camunda:expression>a coincidental form_id field</camunda:expression>
        </camunda:field>
      </bpmn:extensionElements>
    </bpmn:serviceTask>
  </bpmn:process>
</bpmn:definitions>
XML;

    $result = _eca_post_update_rename_diagram_form_id($xml);

    // The form event field is renamed...
    $this->assertStringContainsString('camunda:field name="form_ids"', $result);
    // ...while the coincidental field on the non-form element keeps the name.
    // (name="form_id" with the closing quote does not match name="form_ids".)
    $this->assertStringContainsString('name="form_id"', $result);
  }

  /**
   * Tests that a legacy model referencing a removed plugin fails retry-safe.
   *
   * The migration wraps $eca->save() (whose preSave() instantiates every
   * migrated plugin). A model whose event references a plugin that no longer
   * exists in ECA 3.1 must therefore surface as a pending, re-runnable
   * UpdateException rather than an uncaught fatal, and its raw diagram must be
   * preserved for recovery.
   */
  public function testModelWithRemovedPluginFailsRetrySafe(): void {
    $this->createEca2Model('legacy_removed_plugin', 'Label from the entity', self::MODEL_DATA);
    // Point the event at a plugin that does not exist, as a removed ECA 2
    // plugin would appear to ECA 3.
    $config = \Drupal::configFactory()->getEditable('eca.eca.legacy_removed_plugin');
    $events = $config->get('events');
    $events['event_build']['plugin'] = 'no_such_plugin';
    $config->set('events', $events)->save();

    try {
      eca_post_update_migrate_to_v3();
      $this->fail('Expected an UpdateException for the removed plugin.');
    }
    catch (UpdateException $e) {
      $this->assertStringContainsString('legacy_removed_plugin', $e->getMessage());
    }

    // The raw diagram survives, because only models proven to be owned by the
    // Modeler API are deleted.
    $this->assertFalse(\Drupal::configFactory()->get('eca.model.legacy_removed_plugin')->isNew());
    // And the executable model is unchanged (still references the removed
    // plugin, and is not owned by the Modeler API).
    $this->assertNotContains('modeler_api', \Drupal::configFactory()->get('eca.eca.legacy_removed_plugin')->get('third_party_settings') ?? []);
  }

}
