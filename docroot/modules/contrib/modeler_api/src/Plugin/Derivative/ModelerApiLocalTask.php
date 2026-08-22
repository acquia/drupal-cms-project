<?php

namespace Drupal\modeler_api\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\modeler_api\Api;
use Drupal\modeler_api\Plugin\ModelOwnerPluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides local task definitions for all model owners.
 */
class ModelerApiLocalTask extends DeriverBase implements ContainerDeriverInterface {

  use StringTranslationTrait;

  /**
   * Constructs a ModelerApiLocalTask object.
   */
  final public function __construct(
    protected ModelOwnerPluginManager $modelOwnerPluginManager,
    protected Api $modelerApiService,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id): ModelerApiLocalTask {
    return new static(
      $container->get('plugin.manager.modeler_api.model_owner'),
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
      $this->derivatives['entity.' . $type . '.collection'] = [
        'route_name' => 'entity.' . $type . '.collection',
        'title' => $this->t('Models'),
        'base_route' => 'entity.' . $type . '.collection',
      ];
      $name = 'entity.' . $type . '.import';
      if ($this->modelerApiService->getRouteByName($name)) {
        $this->derivatives[$name] = [
          'route_name' => $name,
          'title' => $this->t('Import'),
          'base_route' => 'entity.' . $type . '.collection',
        ];
      }
      if ($owner->settingsForm()) {
        $this->derivatives['entity.' . $type . '.settings'] = [
          'route_name' => 'entity.' . $type . '.settings',
          'title' => $this->t('Settings'),
          'base_route' => 'entity.' . $type . '.collection',
        ];
      }
    }
    foreach ($this->derivatives as &$entry) {
      $entry += $base_plugin_definition;
    }
    return $this->derivatives;
  }

}
