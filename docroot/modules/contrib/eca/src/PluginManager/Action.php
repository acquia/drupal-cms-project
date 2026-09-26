<?php

namespace Drupal\eca\PluginManager;

use Drupal\Core\Action\ActionManager;
use Drupal\eca\Plugin\Action\ActionInterface;

/**
 * Decorates the action manager to make ECA actions only available in ECA.
 */
class Action extends ActionManager {

  /**
   * The action manager that is being decorated by this class.
   *
   * @var \Drupal\Core\Action\ActionManager
   */
  protected ActionManager $decoratedManager;

  /**
   * Get the service instance of this class.
   *
   * @return \Drupal\eca\PluginManager\Action
   *   The service instance.
   */
  public static function get(): Action {
    return \Drupal::service('plugin.manager.eca.action');
  }

  /**
   * Get the action manager that is being decorated by this class.
   *
   * @return \Drupal\Core\Action\ActionManager
   *   The manager being decorated.
   */
  public function getDecoratedActionManager(): ActionManager {
    return $this->decoratedManager;
  }

  /**
   * Set the action manager that is being decorated by this class.
   *
   * @param \Drupal\Core\Action\ActionManager $manager
   *   The manager being decorated.
   */
  public function setDecoratedActionManager(ActionManager $manager): void {
    $this->decoratedManager = $manager;
  }

  /**
   * {@inheritdoc}
   */
  public function getDefinitions() {
    $this->definitions = $this->filterEcaDefinitions($this->decoratedManager->getDefinitions());
    return $this->definitions;
  }

  /**
   * {@inheritdoc}
   */
  public function getDefinition($plugin_id, $exception_on_invalid = TRUE) {
    return $this->decoratedManager->getDefinition($plugin_id, $exception_on_invalid);
  }

  /**
   * {@inheritdoc}
   */
  public function hasDefinition($plugin_id) {
    return $this->decoratedManager->hasDefinition($plugin_id);
  }

  /**
   * {@inheritdoc}
   */
  public function clearCachedDefinitions(): void {
    $this->decoratedManager->clearCachedDefinitions();
    parent::clearCachedDefinitions();
  }

  /**
   * {@inheritdoc}
   */
  public function useCaches($use_caches = FALSE): void {
    $this->decoratedManager->useCaches($use_caches);
    parent::useCaches($use_caches);
  }

  /**
   * {@inheritdoc}
   */
  public function processDefinition(&$definition, $plugin_id): void {
    $this->decoratedManager->processDefinition($definition, $plugin_id);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    return $this->decoratedManager->getCacheContexts();
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    return $this->decoratedManager->getCacheTags();
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    return $this->decoratedManager->getCacheMaxAge();
  }

  /**
   * {@inheritdoc}
   */
  public function createInstance($plugin_id, array $configuration = []) {
    return $this->decoratedManager->createInstance($plugin_id, $configuration);
  }

  /**
   * {@inheritdoc}
   */
  public function getInstance(array $options) {
    return $this->decoratedManager->getInstance($options);
  }

  /**
   * Removes ECA action definition.
   *
   * @param array $definitions
   *   The definitions to filter.
   */
  protected function filterEcaDefinitions(array $definitions): array {
    return array_filter($definitions, static function ($definition) {
      if ($class = ($definition['class'] ?? NULL)) {
        if (is_a($class, ActionInterface::class, TRUE)) {
          return $class::externallyAvailable();
        }
      }
      return TRUE;
    });
  }

}
