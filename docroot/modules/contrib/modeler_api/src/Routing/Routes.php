<?php

namespace Drupal\modeler_api\Routing;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\modeler_api\ModelerApiPermissions;
use Drupal\modeler_api\Plugin\ModelerPluginManager;
use Drupal\modeler_api\Plugin\ModelOwnerPluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Defines modeler api routes for all model owners and modelers.
 *
 * @internal
 *   This class is not meant to be used as a public API. It is subject for name
 *   change or may be removed completely, also on minor version updates.
 */
final class Routes implements ContainerInjectionInterface {

  /**
   * Constructs the Modeler API route provider.
   */
  public function __construct(
    protected ModelOwnerPluginManager $modelOwnerPluginManager,
    protected ModelerPluginManager $modelerPluginManager,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): Routes {
    return new Routes(
      $container->get('plugin.manager.modeler_api.model_owner'),
      $container->get('plugin.manager.modeler_api.modeler'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * Provides the module's route collection.
   *
   * @return \Symfony\Component\Routing\RouteCollection
   *   The module's route collection.
   */
  public function routes(): RouteCollection {
    $routes = new RouteCollection();
    $modelers = $this->modelerPluginManager->getAllInstances(TRUE);
    $hasModelers = count($modelers) > 1;

    /** @var \Drupal\modeler_api\Plugin\ModelerApiModelOwner\ModelOwnerInterface[] $owners */
    $owners = [];
    foreach ($this->modelOwnerPluginManager->getAllInstances(TRUE) as $ownerId => $owner) {
      $type = $owner->configEntityTypeId();
      $editFormExists = FALSE;
      $options = [
        'parameters' => [
          $type => ['type' => 'entity:' . $type],
          'model' => ['type' => $type, 'provider' => 'modeler_api'],
        ],
      ];

      $routes->add('entity.' . $type . '.save', new Route(
        '/admin/modeler_api/' . $type . '/{modeler_id}/save',
        [
          '_controller' => 'Drupal\modeler_api\Controller\ModelerApi::save',
          'model_owner_id' => $owner->getPluginId(),
        ],
        [
          '_permission' => ModelerApiPermissions::getPermissionKey('edit', $ownerId),
          '_csrf_request_header_token' => 'TRUE',
        ],
      ));
      $routes->add('entity.' . $type . '.config', new Route(
        '/admin/modeler_api/' . $type . '/{modeler_id}/config',
        [
          '_controller' => 'Drupal\modeler_api\Controller\ModelerApi::configForm',
          'model_owner_id' => $owner->getPluginId(),
        ],
        [
          '_permission' => ModelerApiPermissions::getPermissionKey('edit', $ownerId) . '+' . ModelerApiPermissions::getPermissionKey('view', $ownerId),
        ],
      ));
      if ($owner->supportsReplayData()) {
        $routes->add('entity.' . $type . '.replay', new Route(
          '/admin/modeler_api/' . $type . '/replay',
          [
            '_controller' => 'Drupal\modeler_api\Controller\ModelerApi::loadReplayData',
            'model_owner_id' => $owner->getPluginId(),
          ],
          [
            '_permission' => ModelerApiPermissions::getPermissionKey('edit', $ownerId) . '+' . ModelerApiPermissions::getPermissionKey('view', $ownerId),
          ],
        ));
      }
      if ($owner->supportsTesting()) {
        $routes->add('entity.' . $type . '.test', new Route(
          '/admin/modeler_api/' . $type . '/test',
          [
            '_controller' => 'Drupal\modeler_api\Controller\ModelerApi::testModel',
            'model_owner_id' => $owner->getPluginId(),
          ],
          [
            '_permission' => ModelerApiPermissions::getPermissionKey('edit', $ownerId) . '+' . ModelerApiPermissions::getPermissionKey('view', $ownerId),
          ],
        ));
      }

      $basePath = $owner->configEntityBasePath();
      if ($basePath === NULL) {
        continue;
      }
      $owners[$ownerId] = $owner;

      $routes->add('entity.' . $type . '.collection', new Route(
        '/' . $basePath,
        ['_entity_list' => $type, '_title' => $owner->description()],
        ['_permission' => ModelerApiPermissions::getPermissionKey('collection', $ownerId)],
      ));
      if ($settingsForm = $owner->settingsForm()) {
        $routes->add('entity.' . $type . '.settings', new Route(
          '/' . $basePath . '/settings',
          ['_form' => $settingsForm, '_title' => 'Settings'],
          ['_permission' => ModelerApiPermissions::getPermissionKey('administer', $ownerId)],
        ));
      }

      $entityType = $this->entityTypeManager->getDefinition($type);
      if ($entityType->getHandlerClass('form', 'add')) {
        $routes->add('entity.' . $type . '.add_form', new Route(
          '/' . $basePath . '/add/form',
          [
            '_entity_form' => $type . '.add',
          ],
          ['_permission' => ModelerApiPermissions::getPermissionKey('edit', $ownerId)],
        ));
      }
      if ($entityType->getHandlerClass('form', 'edit')) {
        $editFormExists = TRUE;
        $routes->add('entity.' . $type . '.edit_form', new Route(
          '/' . $basePath . '/{' . $type . '}/edit/form',
          [
            '_entity_form' => $type . '.edit',
          ],
          ['_permission' => ModelerApiPermissions::getPermissionKey('edit', $ownerId)],
          $options,
        ));
      }
      if ($entityType->getHandlerClass('form', 'delete')) {
        $routes->add('entity.' . $type . '.delete_form', new Route(
          '/' . $basePath . '/{' . $type . '}/delete',
          [
            '_entity_form' => $type . '.delete',
            '_title' => 'Delete',
            'model' => '',
          ],
          ['_permission' => ModelerApiPermissions::getPermissionKey('delete', $ownerId)],
          $options,
        ));
      }
      // We do require te edit_form route for config translation, but we can't
      // have it twice. Therefore, use either of the ids depending upon the
      // edit form id already being used or not.
      $routeId = $editFormExists ? 'edit' : 'edit_form';
      $routes->add('entity.' . $type . '.' . $routeId, new Route(
        '/' . $basePath . '/{' . $type . '}/edit',
        [
          '_controller' => 'Drupal\modeler_api\Controller\ModelerApi::edit',
          'model' => '',
        ],
        ['_permission' => ModelerApiPermissions::getPermissionKey('edit', $ownerId)],
        $options,
      ));
      if (!$hasModelers) {
        continue;
      }
      $routes->add('entity.' . $type . '.canonical', new Route(
        '/' . $basePath . '/{' . $type . '}',
        [
          '_controller' => 'Drupal\modeler_api\Controller\ModelerApi::view',
          'model' => '',
        ],
        ['_permission' => ModelerApiPermissions::getPermissionKey('view', $ownerId)],
        $options,
      ));
      $routes->add('entity.' . $type . '.add', new Route(
        '/' . $basePath . '/add',
        [
          '_controller' => 'Drupal\modeler_api\Controller\ModelerApi::add',
          'ownerId' => $owner->getPluginId(),
        ],
        ['_permission' => ModelerApiPermissions::getPermissionKey('edit', $ownerId)],
      ));
      if ($owner->supportsStatus()) {
        $routes->add('entity.' . $type . '.enable', new Route(
          '/' . $basePath . '/{' . $type . '}/enable',
          [
            '_controller' => 'Drupal\modeler_api\Controller\ModelerApi::enable',
            'model' => '',
          ],
          [
            '_permission' => ModelerApiPermissions::getPermissionKey('edit', $ownerId),
            '_csrf_token' => 'TRUE',
          ],
          $options,
        ));
        $routes->add('entity.' . $type . '.disable', new Route(
          '/' . $basePath . '/{' . $type . '}/disable',
          [
            '_controller' => 'Drupal\modeler_api\Controller\ModelerApi::disable',
            'model' => '',
          ],
          [
            '_permission' => ModelerApiPermissions::getPermissionKey('edit', $ownerId),
            '_csrf_token' => 'TRUE',
          ],
          $options,
        ));
      }
      $routes->add('entity.' . $type . '.clone', new Route(
        '/' . $basePath . '/{' . $type . '}/clone',
        [
          '_form' => 'Drupal\modeler_api\Form\CloneForm',
          '_title' => 'Clone',
          'model' => '',
        ],
        [
          '_permission' => ModelerApiPermissions::getPermissionKey('edit', $ownerId),
        ],
        $options,
      ));
      $routes->add('entity.' . $type . '.import', new Route(
        '/' . $basePath . '/import',
        [
          '_form' => 'Drupal\modeler_api\Form\Import',
          '_title' => 'Import',
          'ownerId' => $ownerId,
        ],
        ['_permission' => ModelerApiPermissions::getPermissionKey('edit', $ownerId)],
      ));
      $routes->add('entity.' . $type . '.export', new Route(
        '/' . $basePath . '/{' . $type . '}/export',
        [
          '_controller' => 'Drupal\modeler_api\Controller\ModelerApi::export',
          'model' => '',
        ],
        ['_permission' => ModelerApiPermissions::getPermissionKey('edit', $ownerId)],
        $options,
      ));
      $routes->add('entity.' . $type . '.export_recipe', new Route(
        '/' . $basePath . '/{' . $type . '}/recipe',
        [
          '_form' => 'Drupal\modeler_api\Form\ExportRecipe',
          '_title' => 'Export as recipe',
          'model' => '',
        ],
        ['_permission' => ModelerApiPermissions::getPermissionKey('edit', $ownerId)],
        $options,
      ));
    }

    foreach ($modelers as $modelerId => $modeler) {
      if ($modelerId === 'fallback') {
        continue;
      }
      foreach ($owners as $ownerId => $owner) {
        $basePath = $owner->configEntityBasePath();
        $type = $owner->configEntityTypeId();
        $routes->add('modeler_api.add.' . $ownerId . '.' . $modelerId, new Route(
          '/' . $basePath . '/add/' . $modelerId,
          [
            '_controller' => 'Drupal\modeler_api\Controller\ModelerApi::add',
            'ownerId' => $ownerId,
            'modelerId' => $modelerId,
          ],
          ['_permission' => ModelerApiPermissions::getPermissionKey('edit', $ownerId, $modelerId)],
        ));
        $routes->add('entity.' . $type . '.edit_with.' . $modelerId, new Route(
          '/' . $basePath . '/{' . $type . '}/edit_with/' . $modelerId,
          [
            '_controller' => 'Drupal\modeler_api\Controller\ModelerApi::edit',
            'model' => '',
            'modelerId' => $modelerId,
          ],
          ['_permission' => ModelerApiPermissions::getPermissionKey('edit', $ownerId, $modelerId)],
          [
            'parameters' => [
              $type => ['type' => 'entity:' . $type],
              'model' => ['type' => $type, 'provider' => 'modeler_api'],
            ],
          ],
        ));
        $routes->add('entity.' . $type . '.view_with.' . $modelerId, new Route(
          '/' . $basePath . '/{' . $type . '}/view_with/' . $modelerId,
          [
            '_controller' => 'Drupal\modeler_api\Controller\ModelerApi::view',
            'model' => '',
            'modelerId' => $modelerId,
          ],
          ['_permission' => ModelerApiPermissions::getPermissionKey('view', $ownerId, $modelerId)],
          [
            'parameters' => [
              $type => ['type' => 'entity:' . $type],
              'model' => ['type' => $type, 'provider' => 'modeler_api'],
            ],
          ],
        ));
      }
    }

    return $routes;
  }

}
