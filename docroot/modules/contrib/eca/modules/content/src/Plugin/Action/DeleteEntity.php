<?php

namespace Drupal\eca_content\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eca\Attribute\EcaAction;
use Drupal\eca\Plugin\Action\ConfigurableActionBase;

/**
 * Deletes a content entity.
 */
#[Action(
  id: 'eca_delete_entity',
  label: new TranslatableMarkup('Entity: delete'),
  type: 'entity',
)]
#[EcaAction(
  description: new TranslatableMarkup('Deletes an existing content entity.'),
  version_introduced: '1.0.0',
)]
class DeleteEntity extends ConfigurableActionBase {

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    $account = $account ?? $this->currentUser;
    if (!($object instanceof ContentEntityInterface) || $object->isNew()) {
      $access_result = AccessResult::forbidden();
    }
    else {
      $access_result = $object->access('delete', $account, TRUE);
    }
    return $return_as_object ? $access_result : $access_result->isAllowed();
  }

  /**
   * {@inheritdoc}
   */
  public function execute(mixed $entity = NULL): void {
    if (!($entity instanceof ContentEntityInterface) || $entity->isNew()) {
      return;
    }
    $entity->delete();
  }

}
