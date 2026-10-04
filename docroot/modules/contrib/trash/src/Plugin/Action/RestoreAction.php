<?php

declare(strict_types=1);

namespace Drupal\trash\Plugin\Action;

use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Action\Plugin\Action\DeleteAction;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\trash\Plugin\Action\Derivative\TrashRestoreActionDeriver;

/**
 * Restores entities from trash.
 */
#[Action(
  id: 'entity:restore_action',
  action_label: new TranslatableMarkup('Restore from trash'),
  deriver: TrashRestoreActionDeriver::class,
)]
class RestoreAction extends DeleteAction {

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, PrivateTempStoreFactory $temp_store_factory, AccountInterface $current_user) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_manager, $temp_store_factory, $current_user);
    $this->tempStore = $temp_store_factory->get('trash_restore_multiple_confirm');
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    return $object->access('restore', $account, $return_as_object);
  }

}
