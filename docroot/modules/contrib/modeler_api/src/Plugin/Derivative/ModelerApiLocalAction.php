<?php

namespace Drupal\modeler_api\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\modeler_api\Api;
use Drupal\modeler_api\Plugin\ModelOwnerPluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides local action definitions for all model owners.
 */
class ModelerApiLocalAction extends DeriverBase implements ContainerDeriverInterface {

  use StringTranslationTrait;

  /**
   * Constructs a ModelerApiLocalAction object.
   */
  final public function __construct(
    protected ModelOwnerPluginManager $modelOwnerPluginManager,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected Api $modelerApiService,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id): ModelerApiLocalAction {
    return new static(
      $container->get('plugin.manager.modeler_api.model_owner'),
      $container->get('entity_type.manager'),
      $container->get('modeler_api.service'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition): array {
    $this->derivatives = [];
    foreach ($this->modelOwnerPluginManager->getAllInstances() as $owner) {
      $basePath = $owner->configEntityBasePath();
      if ($basePath === NULL) {
        continue;
      }
      $type = $owner->configEntityTypeId();
      $entityType = $this->entityTypeManager->getDefinition($type);
      $hasAddForm = $entityType->getHandlerClass('form', 'add');
      if ($hasAddForm) {
        $name = 'entity.' . $type . '.add_form';
        if ($this->modelerApiService->getRouteByName($name)) {
          $this->derivatives[$name] = [
            'route_name' => $name,
            'title' => $owner->getPluginDefinition()['uiLabelNewModel'],
            'appears_on' => ['entity.' . $type . '.collection'],
          ];
        }
      }
      $name = 'entity.' . $type . '.add';
      if ($this->modelerApiService->getRouteByName($name)) {
        $this->derivatives[$name] = [
          'route_name' => $name,
          'title' => $hasAddForm ? $owner->getPluginDefinition()['uiLabelNewModelWithModeler'] : $owner->getPluginDefinition()['uiLabelNewModel'],
          'appears_on' => ['entity.' . $type . '.collection'],
        ];
      }
      $name = 'entity.' . $type . '.config_translation_overview';
      if ($this->modelerApiService->getRouteByName($name)) {
        $editRoutes = [];
        foreach ([
          'entity.' . $type . '.edit_form',
          'entity.' . $type . '.edit',
        ] as $editName) {
          if ($this->modelerApiService->getRouteByName($editName)) {
            $editRoutes[] = $editName;
            $this->derivatives[$editName] = [
              'route_name' => $editName,
              'title' => $this->t('Edit'),
              'appears_on' => [$name],
            ];
          }
        }
        $this->derivatives[$name] = [
          'route_name' => $name,
          'title' => $this->t('Translate'),
          'appears_on' => $editRoutes,
        ];
      }
    }
    foreach ($this->derivatives as &$entry) {
      $entry += $base_plugin_definition;
    }
    return $this->derivatives;
  }

}
