<?php

namespace Drupal\eca_content\Plugin\Action;

use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eca\Attribute\EcaAction;
use Drupal\eca\Plugin\Action\ListAddBase;

/**
 * Action to add a specified entity to a list.
 */
#[Action(
  id: 'eca_list_add_entity',
  label: new TranslatableMarkup('List: add entity'),
  type: 'entity',
)]
#[EcaAction(
  description: new TranslatableMarkup('Add a specified entity to a list.'),
  version_introduced: '1.1.0',
)]
class ListAddEntity extends ListAddBase {

  /**
   * {@inheritdoc}
   */
  public function execute(mixed $entity = NULL): void {
    $this->addItem($entity);
  }

}
