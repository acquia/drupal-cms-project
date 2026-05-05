<?php

namespace Drupal\eca_render\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;
use Drupal\eca\Plugin\Action\ConfigurableActionBase;
use Drupal\eca_render\Event\EcaRenderAlterLinkEvent;

/**
 * Base class for actions related to altering a link.
 */
abstract class AlterLinkBase extends ConfigurableActionBase {

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    $result = $this->event instanceof EcaRenderAlterLinkEvent ? AccessResult::allowed() : AccessResult::forbidden("The given event is not an alter link event.");
    return $return_as_object ? $result : $result->isAllowed();
  }

}
