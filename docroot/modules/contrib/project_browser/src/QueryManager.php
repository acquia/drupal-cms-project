<?php

namespace Drupal\project_browser;

use Composer\InstalledVersions;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\project_browser\Plugin\ProjectBrowserSourceManager;
use Drupal\project_browser\ProjectBrowser\Project;
use Drupal\project_browser\ProjectBrowser\ProjectsResultsPage;

/**
 * Handles retrieving projects from enabled sources.
 *
 * @internal
 *   This is an internal part of Project Browser and may be changed or removed
 *   at any time. It should not be used by external code.
 */
final class QueryManager {

  public function __construct(
    private readonly ProjectBrowserSourceManager $pluginManager,
    private readonly ProjectRepository $projectRepository,
    private readonly TimeInterface $time,
    private readonly ?CacheBackendInterface $cache = NULL,
    private readonly int $ttl = 86400,
  ) {}

  /**
   * Returns projects that match a particular query, from specified source.
   *
   * @param string $source_id
   *   The ID of the source plugin to query projects from.
   * @param array $query
   *   (optional) The query to pass to the specified source.
   *
   * @return \Drupal\project_browser\ProjectBrowser\ProjectsResultsPage
   *   The result of the query.
   */
  public function getProjects(string $source_id, array $query = []): ProjectsResultsPage {
    // Cache only exact query, down to the page number.
    $cache_key = $this->getQueryCacheKey($query);
    $cached = $this->cache?->get($cache_key);

    // If $results is an array, it's a set of arguments to ProjectsResultsPage,
    // with a list of project IDs that we expect to be in permanent storage.
    if ($cached) {
      $stored_results = $cached->data;
      assert(is_array($stored_results));
      $stored_results['list'] = array_map(
        fn (string $id): Project => $this->projectRepository->get($stored_results['pluginId'] . '/' . $id),
        $stored_results['list'],
      );
      return new ProjectsResultsPage(...$stored_results);
    }

    $results = $this->doQuery($source_id, $query);
    // Check assumptions: the source must actually claim ownership of these
    // results (to prevent bugs in sources that decorate others).
    assert($results->pluginId === $source_id);
    // Keep each project in permanent storage, overwriting existing data.
    foreach ($results->list as $project) {
      $this->projectRepository->store($source_id, $project);
    }

    // If there were no query errors, cache the results as a set of arguments
    // for ProjectsResultsPage::__construct().
    if (empty($results->error)) {
      $stored_results = $results->toArray();
      // Store the list of projects as a set of IDs in permanent storage. This
      // is reversed when loading projects from a cached query result, near
      // the beginning of this method.
      $stored_results['list'] = array_column($stored_results['list'], 'id');
      // Cache these results for the configured time.
      $this->cache?->set(
        $cache_key,
        $stored_results,
        $this->time->getRequestTime() + $this->ttl,
        ["project_browser:$source_id"],
      );
    }
    return $results;
  }

  /**
   * Generates a cache key for a specific query.
   *
   * @param array $query
   *   The query.
   *
   * @return string
   *   A cache key for the given query.
   */
  private function getQueryCacheKey(array $query): string {
    // Include a quick hash of the top-level `composer.lock` file in the hash,
    // so that sources which base their queries on the state of the local site
    // will be refreshed when the local site changes.
    ['install_path' => $project_root] = InstalledVersions::getRootPackage();
    $lock_file = $project_root . DIRECTORY_SEPARATOR . 'composer.lock';
    $lock_file_hash = file_exists($lock_file)
      ? hash_file('xxh64', $lock_file)
      : '';
    return 'query:' . md5(Json::encode($query) . $lock_file_hash);
  }

  /**
   * Queries the specified source.
   *
   * @param string $source_id
   *   The ID of the source plugin to query projects from.
   * @param array $query
   *   (optional) The query to pass to the specified source.
   *
   * @return \Drupal\project_browser\ProjectBrowser\ProjectsResultsPage
   *   The results of the query.
   *
   * @see \Drupal\project_browser\Plugin\ProjectBrowserSourceInterface::getProjects()
   */
  private function doQuery(string $source_id, array $query = []): ProjectsResultsPage {
    $query['categories'] ??= '';

    $enabled_sources = $this->pluginManager->getAllEnabledSources();
    assert(array_key_exists($source_id, $enabled_sources));
    return $enabled_sources[$source_id]->getProjects($query);
  }

}
