<?php

namespace Drupal\eca_test_stub_modeler\Plugin\ModelerApiModeler;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\modeler_api\Attribute\Modeler;
use Drupal\modeler_api\Plugin\ModelerApiModeler\ModelerBase;
use Drupal\modeler_api\Plugin\ModelerApiModelOwner\ModelOwnerInterface;

/**
 * A minimal modeler plugin used only by tests.
 *
 * Having a second modeler available (besides the fallback) makes modeler_api
 * register the read-only canonical route, which the ECA inspector widget links
 * view-only users to.
 */
#[Modeler(
  id: "stub_modeler",
  label: new TranslatableMarkup("Stub modeler"),
  description: new TranslatableMarkup("Test-only modeler plugin.")
)]
class StubModeler extends ModelerBase {

  /**
   * The raw model data.
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

}
