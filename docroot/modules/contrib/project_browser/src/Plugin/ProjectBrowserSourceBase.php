<?php

namespace Drupal\project_browser\Plugin;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\project_browser\ProjectBrowser\ProjectsResultsPage;

/**
 * Defines an abstract base class for a Project Browser source.
 *
 * @see \Drupal\project_browser\Attribute\ProjectBrowserSource
 * @see \Drupal\project_browser\Plugin\ProjectBrowserSourceManager
 * @see plugin_api
 *
 * @todo Use ConfigurablePluginBase when Drupal 11.3 is the oldest supported
 *   version of core.
 *
 * @api
 *   This class is covered by our backwards compatibility promise and can be
 *   safely relied upon.
 */
abstract class ProjectBrowserSourceBase extends PluginBase implements ProjectBrowserSourceInterface, ContainerFactoryPluginInterface {

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->setConfiguration($configuration);
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
  public function setConfiguration(array $configuration): static {
    $this->configuration = NestedArray::mergeDeepArray([$this->defaultConfiguration(), $configuration], TRUE);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getSortOptions(): array {
    return [
      'usage_total' => $this->t('Most popular'),
      'a_z' => $this->t('A-Z'),
      'z_a' => $this->t('Z-A'),
      'created' => $this->t('Newest first'),
      'best_match' => $this->t('Most relevant'),
    ];
  }

  /**
   * Creates a page of results (projects) to send to the client side.
   *
   * @param \Drupal\project_browser\ProjectBrowser\Project[] $results
   *   The projects to list on the page.
   * @param int|null $total_results
   *   (optional) The total number of results. Defaults to the size of $results.
   * @param string|null $error
   *   (optional) Error message to be passed along, if any.
   *
   * @return \Drupal\project_browser\ProjectBrowser\ProjectsResultsPage
   *   A list of projects to send to the client.
   */
  protected function createResultsPage(array $results, ?int $total_results = NULL, ?string $error = NULL): ProjectsResultsPage {
    return new ProjectsResultsPage(
      $total_results ?? count($results),
      array_values($results),
      (string) $this->getPluginDefinition()['label'],
      $this->getPluginId(),
      $error
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginDefinition(): array {
    $definition = parent::getPluginDefinition();
    assert(is_array($definition));
    return $definition;
  }

}
