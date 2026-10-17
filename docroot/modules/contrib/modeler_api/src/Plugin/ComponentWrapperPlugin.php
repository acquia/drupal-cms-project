<?php

namespace Drupal\modeler_api\Plugin;

use Drupal\Core\Plugin\PluginBase;

/**
 * Component wrapper plugin for model owner components that aren't plugins.
 */
class ComponentWrapperPlugin extends PluginBase implements ComponentWrapperPluginInterface {

  /**
   * Creates a new component wrapper plugin instance.
   *
   * @param int $type
   *   The component type.
   * @param string $id
   *   The plugin ID.
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string|null $label
   *   The optional label of the plugin.
   */
  public function __construct(
    protected int $type,
    protected string $id,
    array $configuration = [],
    protected ?string $label = NULL,
  ) {
    parent::__construct($configuration, $id, []);
    if ($label) {
      $this->pluginDefinition['label'] = $label;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getType(): int {
    return $this->type;
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration(): array {
    return $this->configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration): void {
    $this->configuration = $configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [];
  }

}
