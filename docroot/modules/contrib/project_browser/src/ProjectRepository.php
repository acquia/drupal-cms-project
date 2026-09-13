<?php

declare(strict_types=1);

namespace Drupal\project_browser;

use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\KeyValueStore\KeyValueStoreInterface;
use Drupal\project_browser\Plugin\ProjectBrowserSourceManager;
use Drupal\project_browser\ProjectBrowser\Project;

/**
 * Provides a permanent, stable store for project data.
 *
 * @internal
 *    This is an internal part of Project Browser and may be changed or removed
 *    at any time. It should not be used by external code.
 */
final class ProjectRepository {

  public function __construct(
    private readonly KeyValueFactoryInterface $keyValueFactory,
    private readonly ProjectBrowserSourceManager $sourceManager,
    private readonly CacheTagsInvalidatorInterface $cacheTagsInvalidator,
  ) {}

  /**
   * Returns a key-value store for a particular source plugin.
   *
   * @param string $source_id
   *   The ID of a source plugin.
   *
   * @return \Drupal\Core\KeyValueStore\KeyValueStoreInterface
   *   A key-value store for the specified source plugin.
   */
  private function getStorage(string $source_id): KeyValueStoreInterface {
    return $this->keyValueFactory->get("project_browser:$source_id");
  }

  /**
   * Looks up a previously stored project by its ID.
   *
   * @param string $id
   *   The fully qualified project ID, in the form `SOURCE_ID/LOCAL_ID`.
   *
   * @return \Drupal\project_browser\ProjectBrowser\Project
   *   The project object.
   *
   * @throws \InvalidArgumentException
   *   Thrown if the project is not found in the non-volatile data store.
   */
  public function get(string $id): Project {
    [$source_id, $local_id] = explode('/', $id, 2);
    $local_id = Project::normalizeId($local_id);
    return $this->getStorage($source_id)->get($local_id) ?? throw new \InvalidArgumentException("Project '$id' was not found in non-volatile storage.");
  }

  /**
   * Stores project data.
   *
   * @param string $source_id
   *   The ID of the source plugin that is providing this project.
   * @param \Drupal\project_browser\ProjectBrowser\Project $project
   *   The project data.
   */
  public function store(string $source_id, Project $project): void {
    $id = Project::normalizeId($project->id);
    $this->getStorage($source_id)->set($id, $project);
  }

  /**
   * Clears the key-value store for a particular source.
   *
   * @param string $source_id
   *   The ID of the source for which data should be cleared.
   */
  public function clear(string $source_id): void {
    $this->getStorage($source_id)->deleteAll();
    // Any cache data that was stored for this source must also be cleared,
    // since it might be referencing projects that we no longer have stored.
    $this->cacheTagsInvalidator->invalidateTags(["project_browser:$source_id"]);
  }

  /**
   * Clears data for all sources.
   */
  public function clearAll(): void {
    $sources = array_keys($this->sourceManager->getAllEnabledSources());
    array_walk($sources, $this->clear(...));
  }

}
