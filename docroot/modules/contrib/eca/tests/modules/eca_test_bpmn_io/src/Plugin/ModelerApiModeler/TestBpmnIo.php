<?php

namespace Drupal\eca_test_bpmn_io\Plugin\ModelerApiModeler;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\modeler_api\Attribute\Modeler;
use Drupal\modeler_api\Plugin\ModelerApiModeler\ModelerBase;
use Drupal\modeler_api\Plugin\ModelerApiModelOwner\ModelOwnerInterface;

/**
 * Stands in for the BPMN.iO modeler in tests.
 *
 * Deliberately registered under the plugin ID "bpmn_io", because that is the
 * modeler the migration of ECA 2 models asks for by name: every ECA 2 model was
 * drawn in BPMN. Using a stand-in keeps the migration testable without making
 * the bpmn_io project a development dependency of ECA.
 *
 * The metadata getters return fixed values that differ from both the defaults
 * of the base class and anything stored in the fixtures, so that a test can
 * tell a value that traveled from the parsed diagram apart from one that was
 * left over or defaulted.
 *
 * @see eca_post_update_migrate_to_v3()
 */
#[Modeler(
  id: "bpmn_io",
  label: new TranslatableMarkup("BPMN.iO modeler stand-in"),
  description: new TranslatableMarkup("Test-only stand-in for the BPMN.iO modeler.")
)]
class TestBpmnIo extends ModelerBase {

  /**
   * The raw model data as it was handed to ::parseData().
   *
   * @var string
   */
  protected string $data = '';

  /**
   * {@inheritdoc}
   */
  public function parseData(ModelOwnerInterface $owner, string $data): void {
    $this->data = $data;
  }

  /**
   * {@inheritdoc}
   */
  public function getRawData(): string {
    return $this->data;
  }

  /**
   * {@inheritdoc}
   */
  public function getLabel(): string {
    return 'Label from the diagram';
  }

  /**
   * {@inheritdoc}
   */
  public function getChangelog(): string {
    return 'Changelog from the diagram';
  }

  /**
   * {@inheritdoc}
   */
  public function getDocumentation(): string {
    return 'Documentation from the diagram';
  }

  /**
   * {@inheritdoc}
   */
  public function getTags(): array {
    return ['tag-from-the-diagram'];
  }

  /**
   * {@inheritdoc}
   */
  public function getVersion(): string {
    return '0.9.7';
  }

}
