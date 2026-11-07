<?php

namespace Drupal\eca_ui\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Hook\Order\Order;
use Drupal\Core\Session\AccountInterface;

/**
 * Implements render hooks for the ECA UI module.
 */
class RenderHooks {

  /**
   * Constructs a new RenderHooks object.
   *
   * @param \Drupal\Core\Session\AccountInterface $currentUser
   *   The current user.
   */
  public function __construct(
    protected AccountInterface $currentUser,
  ) {}

  /**
   * Implements hook_page_attachments().
   */
  #[Hook('page_attachments', order: Order::Last)]
  public function pageAttachmentsAlter(array &$attachments): void {
    if ($this->currentUser->hasPermission('modeler api edit eca')
      || $this->currentUser->hasPermission('modeler api view eca')) {
      $attachments['#attached']['library'][] = 'eca_ui/inspector';
    }
  }

}
