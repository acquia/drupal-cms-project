<?php

namespace Drupal\ai_dashboard\Plugin\AiDocumentation;

use Drupal\Core\Plugin\PluginBase;

/**
 * Class for AI Documentation definition.
 */
class AiDocumentation extends PluginBase implements AiDocumentationInterface {

  /**
   * {@inheritdoc}
   */
  public function getId() {
    return $this->pluginDefinition['id'];
  }

  /**
   * {@inheritdoc}
   */
  public function getLabel() {
    return $this->pluginDefinition['label'];
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->pluginDefinition['description'];
  }

  /**
   * {@inheritdoc}
   */
  public function getUrl() {
    return $this->pluginDefinition['url'];
  }

}
