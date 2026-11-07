<?php

namespace Drupal\Tests\eca\Kernel\Update;

use Drupal\modeler_api\Plugin\ModelerApiModelOwner\ModelOwnerInterface;

/**
 * Creates configuration in the shape ECA 2 used to store it.
 *
 * The configuration is written straight through the configuration factory
 * rather than through the entity API, because that is the only way to produce
 * the ECA 2 shape: the "eca_model" config entity type is gone in ECA 3, and the
 * properties ECA 2 kept on "eca.eca.*" are no longer exported, so saving an
 * entity would silently drop exactly the data the migration is about.
 */
trait Eca2FixtureTrait {

  /**
   * A diagram as the BPMN modeler of ECA 2 would have stored it.
   *
   * Reduced to the parts that matter here: a start event carrying the plugin ID
   * of a form event, and its configuration under the "form_id" name that ECA 3
   * replaced with "form_ids".
   */
  protected const string MODEL_DATA = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<bpmn:definitions xmlns:bpmn="http://www.omg.org/spec/BPMN/20100524/MODEL" xmlns:camunda="http://camunda.org/schema/1.0/bpmn" id="Definitions_1">
  <bpmn:process id="eca_legacy" isExecutable="true">
    <bpmn:startEvent id="event_build" name="Build the user registration form">
      <bpmn:extensionElements>
        <camunda:properties>
          <camunda:property name="pluginid" value="form:form_build" />
        </camunda:properties>
        <camunda:field name="form_id">
          <camunda:string>user_register_form</camunda:string>
        </camunda:field>
      </bpmn:extensionElements>
    </bpmn:startEvent>
  </bpmn:process>
</bpmn:definitions>
XML;

  /**
   * Creates an ECA 2 model, optionally together with its raw diagram.
   *
   * @param string $id
   *   The ID of the model.
   * @param string $label
   *   The label of the model, which ECA 2 kept as a property of the entity.
   * @param string|null $modelData
   *   The raw diagram, or NULL for a model that never had one.
   */
  protected function createEca2Model(string $id, string $label, ?string $modelData): void {
    \Drupal::configFactory()->getEditable('eca.eca.' . $id)->setData([
      'uuid' => \Drupal::service('uuid')->generate(),
      'langcode' => 'en',
      'status' => TRUE,
      'dependencies' => [
        'module' => ['eca_form'],
      ],
      'id' => $id,
      // Both of these were properties of the entity in ECA 2 and became
      // third-party settings of the Modeler API in ECA 3.
      'modeller' => 'bpmn_io',
      'label' => $label,
      'version' => '1.0.0',
      'weight' => 0,
      'events' => [
        'event_build' => [
          'plugin' => 'form:form_build',
          'label' => 'Build the user registration form',
          'configuration' => [
            // Renamed to "form_ids" in ECA 3.
            'form_id' => 'user_register_form',
          ],
          'successors' => [],
        ],
      ],
      'conditions' => [],
      'gateways' => [],
      'actions' => [],
    ])->save();

    if ($modelData !== NULL) {
      $this->createEca2ModelData($id, $modelData);
    }
  }

  /**
   * Creates the raw diagram of an ECA 2 model on its own.
   *
   * @param string $id
   *   The ID of the model the diagram belongs to.
   * @param string $modelData
   *   The raw diagram.
   */
  protected function createEca2ModelData(string $id, string $modelData): void {
    \Drupal::configFactory()->getEditable('eca.model.' . $id)->setData([
      'uuid' => \Drupal::service('uuid')->generate(),
      'langcode' => 'en',
      'status' => TRUE,
      'dependencies' => [],
      'id' => $id,
      'modeller' => 'bpmn_io',
      'label' => 'Label from the entity',
      'tags' => ['untagged'],
      'documentation' => '',
      'filename' => $id . '.xml',
      'modeldata' => $modelData,
    ])->save();
  }

  /**
   * Returns the Modeler API model owner plugin for ECA.
   *
   * @return \Drupal\modeler_api\Plugin\ModelerApiModelOwner\ModelOwnerInterface
   *   The model owner plugin.
   */
  protected function modelOwner(): ModelOwnerInterface {
    /** @var \Drupal\modeler_api\Plugin\ModelOwnerPluginManager $manager */
    $manager = \Drupal::service('plugin.manager.modeler_api.model_owner');
    $owner = $manager->createInstance('eca');
    $this->assertInstanceOf(ModelOwnerInterface::class, $owner);
    return $owner;
  }

}
