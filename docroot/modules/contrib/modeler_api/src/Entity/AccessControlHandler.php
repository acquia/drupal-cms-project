<?php

namespace Drupal\modeler_api\Entity;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\modeler_api\Api;
use Drupal\modeler_api\ModelerApiPermissions;

/**
 * Defines the access control handler for model owner config entities.
 */
class AccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account): AccessResultInterface {
    if (!($entity instanceof ConfigEntityInterface)) {
      return AccessResult::forbidden('Entity is not a config entity.');
    }
    $owner = Api::get()->findOwner($entity);
    if (!$owner) {
      return AccessResult::forbidden('Owner plugin for the model not found.');
    }
    $modeler = $owner->getModeler($entity);
    if ($modeler === NULL || $modeler->getPluginId() === 'fallback') {
      $modeler = Api::get()->getModeler();
    }

    switch ($operation) {
      case 'view':
      case 'view label':
        if ($modeler === NULL) {
          return AccessResult::forbidden('No modeler available.');
        }
        return AccessResult::allowedIfHasPermission($account, ModelerApiPermissions::getPermissionKey('view', $owner->getPluginId(), $modeler->getPluginId()));

      case 'delete':
        return AccessResult::allowedIfHasPermission($account, ModelerApiPermissions::getPermissionKey('delete', $owner->getPluginId()));

      case 'update':
        if ($modeler === NULL) {
          return AccessResult::allowedIfHasPermission($account, ModelerApiPermissions::getPermissionKey('edit', $owner->getPluginId(), '_form'));
        }
        if ($owner->isEditable($entity)) {
          return AccessResult::allowedIf(
            $account->hasPermission(ModelerApiPermissions::getPermissionKey('edit', $owner->getPluginId(), $modeler->getPluginId())) ||
            $account->hasPermission(ModelerApiPermissions::getPermissionKey('edit', $owner->getPluginId(), '_form'))
          );
        }
        return AccessResult::forbidden();

    }
    return parent::checkAccess($entity, $operation, $account);
  }

}
