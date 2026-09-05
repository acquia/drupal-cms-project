<?php

namespace Drupal\modeler_api\Plugin\ModelerApiModeler;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\modeler_api\Attribute\Modeler;
use Drupal\modeler_api\Plugin\ModelerApiModelOwner\ModelOwnerInterface;

/**
 * Fallback plugin implementation of the Modeler.
 *
 * Modeler plugin that the plugin manager uses if the actual modeler plugin
 * for a model is not installed.
 */
#[Modeler(
  id: "fallback",
  label: new TranslatableMarkup("Fallback"),
  description: new TranslatableMarkup("Modeler plugin for the plugin manager to never fail.")
)]
class Fallback extends ModelerBase {

  /**
   * The raw model data.
   *
   * @var string
   */
  protected string $data;

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

}
