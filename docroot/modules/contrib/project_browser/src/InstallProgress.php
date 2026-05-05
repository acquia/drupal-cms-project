<?php

namespace Drupal\project_browser;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\KeyValueStore\KeyValueStoreInterface;
use Drupal\project_browser\Activator\ActivationStatus;
use Drupal\project_browser\ProjectBrowser\Project;

/**
 * Defines a service to track the progress of installing projects.
 *
 * @internal
 *   This is an internal part of Project Browser and may be changed or removed
 *   at any time. It should not be used by external code.
 */
final class InstallProgress {

  /**
   * The key-value storage.
   */
  private readonly KeyValueStoreInterface $keyValue;

  public function __construct(
    KeyValueFactoryInterface $keyValueFactory,
    private readonly TimeInterface $time,
  ) {
    $this->keyValue = $keyValueFactory->get('project_browser.install_progress');
  }

  /**
   * Returns information on all in-progress installations.
   *
   * @return array<string, string[]>
   *   The array contains:
   *   - Project states: Keyed by project ID, where each entry is an associative
   *     array containing:
   *       - source: The source plugin ID for the project.
   *       - status: The installation status of the project, or NULL if not set.
   *   - A separate `__timestamp` entry: The UNIX timestamp indicating when the
   *     request started (included only if $include_timestamp is TRUE).
   *
   *   Example return value:
   *   [
   *     'project_id1' => [
   *       'status' => 'requiring',
   *     ],
   *     'project_id2' => [
   *       'status' => 'installing',
   *     ],
   *     '__timestamp' => 1732086755,
   *   ]
   */
  public function toArray(): array {
    $data = [];
    foreach (ActivationStatus::cases() as $case) {
      $data[$case->value] = [];
    }

    foreach ($this->keyValue->getAll() as $key => [$project_id, $status]) {
      if ($key == '__timestamp') {
        continue;
      }
      $data[$status][] = $project_id;
    }
    return $data;
  }

  /**
   * Sets project status and initializes a timestamp if not set.
   *
   * @param string $project_id
   *   The fully qualified ID of the project, in the form `SOURCE_ID/LOCAL_ID`.
   * @param \Drupal\project_browser\Activator\ActivationStatus $status
   *   The installation status to set for the project.
   */
  public function setStatus(string $project_id, ActivationStatus $status): void {
    $this->keyValue->setIfNotExists('__timestamp', $this->time->getRequestTime());
    $key = Project::normalizeId($project_id);
    $this->keyValue->set($key, [$project_id, $status->value]);
  }

  /**
   * Clears all current progress information.
   */
  public function clear(): void {
    $this->keyValue->deleteAll();
  }

  /**
   * Retrieves the first updated time of the project states.
   *
   * @return int|null
   *   The timestamp when the project states were first updated, or NULL.
   */
  public function getFirstUpdatedTime(): ?int {
    return $this->keyValue->get('__timestamp');
  }

}
