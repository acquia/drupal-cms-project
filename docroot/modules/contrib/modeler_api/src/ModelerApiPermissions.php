<?php

namespace Drupal\modeler_api;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxy;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\modeler_api\Plugin\ModelerPluginManager;
use Drupal\modeler_api\Plugin\ModelOwnerPluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides dynamic permissions of the Modeler API.
 */
class ModelerApiPermissions implements ContainerInjectionInterface {

  use StringTranslationTrait;

  /**
   * The model owner plugin manager.
   *
   * @var \Drupal\modeler_api\Plugin\ModelOwnerPluginManager
   */
  protected ModelOwnerPluginManager $modelOwnerPluginManager;

  /**
   * The modeler plugin manager.
   *
   * @var \Drupal\modeler_api\Plugin\ModelerPluginManager
   */
  protected ModelerPluginManager $modelerPluginManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    $instance = new self();
    $instance->modelOwnerPluginManager = $container->get('plugin.manager.modeler_api.model_owner');
    $instance->modelerPluginManager = $container->get('plugin.manager.modeler_api.modeler');
    $instance->entityTypeManager = $container->get('entity_type.manager');
    return $instance;
  }

  /**
   * A permissions' callback.
   *
   * @see modeler_api.permissions.yml
   *
   * @return array
   *   An array of permissions for all plugins.
   */
  public function permissions(): array {
    $permissions = [];
    foreach ($this->modelOwnerPluginManager->getAllInstances(TRUE) as $ownerId => $owner) {
      $args = [
        '@owner' => $owner->label(),
      ];
      $permissions[self::getPermissionKey('administer', $ownerId)] = [
        'title' => $this->t('Administer @owner', $args),
        'restrict access' => TRUE,
      ];
      $permissions[self::getPermissionKey('collection', $ownerId)] = [
        'title' => $this->t('View @owner collection', $args),
      ];
      $permissions[self::getPermissionKey('edit', $ownerId)] = [
        'title' => $this->t('Edit @owner', $args),
        'restrict access' => TRUE,
      ];
      $permissions[self::getPermissionKey('delete', $ownerId)] = [
        'title' => $this->t('Delete @owner', $args),
        'restrict access' => TRUE,
      ];
      $permissions[self::getPermissionKey('view', $ownerId)] = [
        'title' => $this->t('View @owner', $args),
      ];
      $permissions[self::getPermissionKey('edit metadata', $ownerId)] = [
        'title' => $this->t('Edit @owner metadata', $args),
      ];
      $permissions[self::getPermissionKey('switch context', $ownerId)] = [
        'title' => $this->t('Switch @owner context', $args),
      ];
      if ($owner->supportsTesting()) {
        $permissions[self::getPermissionKey('test', $ownerId)] = [
          'title' => $this->t('Test @owner', $args),
        ];
      }
      if ($owner->supportsReplayData()) {
        $permissions[self::getPermissionKey('replay', $ownerId)] = [
          'title' => $this->t('Replay @owner', $args),
        ];
      }
      if ($owner->supportsTemplate()) {
        $permissions[self::getPermissionKey('create template', $ownerId)] = [
          'title' => $this->t('Create @owner templates', $args),
        ];
        $permissions[self::getPermissionKey('edit template', $ownerId)] = [
          'title' => $this->t('Edit @owner templates', $args),
        ];
      }
      foreach ($this->modelerPluginManager->getAllInstances(TRUE) as $modelerId => $modeler) {
        if ($modelerId === 'fallback') {
          continue;
        }
        $args['@modeler'] = $modeler->label();
        $permissions[self::getPermissionKey('edit', $ownerId, $modelerId)] = [
          'title' => $this->t('Edit @owner with @modeler', $args),
          'restrict access' => TRUE,
        ];
        $permissions[self::getPermissionKey('view', $ownerId, $modelerId)] = [
          'title' => $this->t('View @owner with @modeler', $args),
        ];
      }
      if ($this->entityTypeManager->getDefinition($owner->configEntityTypeId())->getHandlerClass('form', 'edit')) {
        $args = [
          '@owner' => $owner->label(),
          '@modeler' => $this->t('regular form'),
        ];
        $permissions[self::getPermissionKey('edit', $ownerId, '_form')] = [
          'title' => $this->t('Edit @owner with @modeler', $args),
          'restrict access' => TRUE,
        ];
      }
    }
    return $permissions;
  }

  /**
   * Gets the permissions of an account for a specific modeler.
   *
   * @param \Drupal\Core\Session\AccountProxy $account
   *   The user account.
   * @param string $ownerId
   *   The owner ID.
   *
   * @return array
   *   The permissions.
   */
  public static function userPermissionsForModeler(AccountProxy $account, string $ownerId): array {
    $permissions = [];
    foreach ([
      'edit metadata',
      'switch context',
      'edit template',
      'create template',
      'test',
      'replay',
    ] as $perm) {
      $permissions[$perm] = $account->hasPermission(self::getPermissionKey($perm, $ownerId));
    }
    return $permissions;
  }

  /**
   * Gets the permission key for an operation on models and modelers.
   *
   * @param string $op
   *   The operation.
   * @param string $ownerId
   *   The model owner plugin ID.
   * @param string|null $modelerId
   *   The modeler plugin ID, or NULL if it's a modeler independent permission.
   *
   * @return string
   *   The permission key.
   */
  public static function getPermissionKey(string $op, string $ownerId, ?string $modelerId = NULL): string {
    return $modelerId === NULL ?
      "modeler api $op $ownerId" :
      "modeler api $op $ownerId with $modelerId";
  }

}
