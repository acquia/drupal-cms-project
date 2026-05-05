<?php

namespace Drupal\eca_ui\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Hook\Order\Order;
use Drupal\Core\State\StateInterface;

/**
 * Implements render hooks for the ECA UI module.
 */
class RenderHooks {

  /**
   * Constructs a new RenderHooks object.
   */
  public function __construct(
    protected StateInterface $state,
  ) {}

  /**
   * Implements hook_page_attachments().
   */
  #[Hook('page_attachments', order: Order::Last)]
  public function pageAttachmentsAlter(array &$attachments): void {
    if ($this->state->get('_eca_internal_debug_mode', FALSE) ?? FALSE) {
      $attachments['#attached']['library'][] = 'eca_ui/debug';
    }
  }

}
