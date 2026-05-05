<?php

namespace Drupal\ai_agents\Artifact;

/**
 * In-memory implementation of ArtifactStorageInterface.
 *
 * Provides a simple in-memory storage for artifacts.
 */
class InMemoryArtifactStorage implements ArtifactStorageInterface {

  /**
   * The stored artifacts.
   *
   * @var array<string, mixed>
   */
  protected array $artifacts = [];

  /**
   * {@inheritdoc}
   */
  public function store(string $tool_id, int $index, mixed $value): void {
    $this->artifacts["$tool_id:$index"] = $value;
  }

  /**
   * {@inheritdoc}
   */
  public function get(string $tool_id, int $index): mixed {
    return $this->artifacts["$tool_id:$index"] ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function has(string $tool_id, int $index): bool {
    return isset($this->artifacts["$tool_id:$index"]);
  }

  /**
   * {@inheritdoc}
   */
  public function all(): array {
    return $this->artifacts;
  }

  /**
   * {@inheritdoc}
   */
  public function getNextIndex(string $tool_id): int {
    $tool_prefix = $tool_id . ':';

    // Extract this tool by filtering keys that start with the tool prefix.
    $indexes = array_keys(array_filter(array_keys($this->artifacts), fn($k) => str_starts_with($k, $tool_prefix)));

    // Iterate over each matching key to find the highest numeric index.
    $max = 0;
    foreach ($indexes as $key) {
      // Split the key by ":", expecting the format "tool_id:index".
      [, $i] = explode(':', $key, 2);
      $max = max($max, (int) $i);
    }
    return $max + 1;
  }

}
