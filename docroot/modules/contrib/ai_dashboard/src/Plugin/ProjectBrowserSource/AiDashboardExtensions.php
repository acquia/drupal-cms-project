<?php

declare(strict_types=1);

namespace Drupal\ai_dashboard\Plugin\ProjectBrowserSource;

use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\project_browser\Attribute\ProjectBrowserSource;
use Drupal\project_browser\Plugin\ProjectBrowserSourceBase;
use Drupal\project_browser\Plugin\ProjectBrowserSourceInterface;
use Drupal\project_browser\Plugin\ProjectBrowserSourceManager;
use Drupal\project_browser\ProjectBrowser\ProjectsResultsPage;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Lets users explore contrib projects tagged "Artificial Intelligence".
 *
 * Rather than listing the AI modules that already happen to be installed
 * locally, this source wraps another Project Browser source (by default the
 * "Contrib modules" source, which talks to Drupal.org) and forces every
 * request to be scoped to the "Artificial Intelligence (AI)" module
 * category, so the dashboard can offer discovery of the wider AI ecosystem
 * instead of just what is already on the site.
 */
#[ProjectBrowserSource(
  id: 'ai_dashboard_extensions',
  label: new TranslatableMarkup('AI extensions'),
  description: new TranslatableMarkup('Contributed projects tagged with the Artificial Intelligence (AI) category on Drupal.org.'),
  local_task: [],
)]
final class AiDashboardExtensions extends ProjectBrowserSourceBase {

  /**
   * The UUID of the "Artificial Intelligence (AI)" module category term.
   *
   * @see https://www.drupal.org/jsonapi/taxonomy_term/module_categories
   */
  const AI_CATEGORY_UUID = '3fddd228-023a-43d8-acf4-75fa49aa52d6';

  public function __construct(
    protected PluginManagerInterface $sourceManager,
    mixed ...$arguments,
  ) {
    parent::__construct(...$arguments);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new self(
      $container->get(ProjectBrowserSourceManager::class),
      $configuration,
      $plugin_id,
      $plugin_definition,
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      // The plugin ID of the source to wrap and scope to the AI category.
      'source' => 'drupalorg_jsonapi',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function getProjects(array $query = []): ProjectsResultsPage {
    // No matter what was requested, only ever return projects tagged with
    // the "Artificial Intelligence (AI)" category.
    $query['categories'] = self::AI_CATEGORY_UUID;
    $results = $this->getWrappedSource()->getProjects($query);
    // Project Browser requires the results to be owned by this source, not by
    // the wrapped one.
    return $this->createResultsPage(
      $results->list,
      $results->totalResults,
      $results->error,
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFilterDefinitions(): array {
    // The category is fixed by this source, so don't let it be changed.
    $filters = $this->getWrappedSource()->getFilterDefinitions();
    unset($filters['categories']);
    return $filters;
  }

  /**
   * {@inheritdoc}
   */
  public function getSortOptions(): array {
    return $this->getWrappedSource()->getSortOptions();
  }

  /**
   * Returns the source being wrapped by this plugin.
   */
  protected function getWrappedSource(): ProjectBrowserSourceInterface {
    return $this->sourceManager->createInstance($this->getConfiguration()['source']);
  }

}
