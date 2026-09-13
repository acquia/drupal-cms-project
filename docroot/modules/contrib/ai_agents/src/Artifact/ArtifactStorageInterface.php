<?php

namespace Drupal\ai_agents\Artifact;

/**
 * Defines an interface for storing and retrieving artifact values.
 *
 * This interface abstracts how artifact outputs from tools are stored
 * during an agent execution loop. Implementations may use in-memory,
 * session, file, or persistent storage strategies.
 */
interface ArtifactStorageInterface {

  /**
   * Stores a value as an artifact output for a given tool and index.
   *
   * The combination of $tool_id and $index must be unique per storage
   * instance. This is typically managed by the ArtifactManager.
   *
   * @param string $tool_id
   *   The ID of the tool (function) that produced the artifact.
   * @param int $index
   *   The unique sequential index for this tool's output.
   * @param mixed $value
   *   The actual data to store. Usually an instance of ArtifactInterface.
   */
  public function store(string $tool_id, int $index, mixed $value): void;

  /**
   * Retrieves the stored value for a given tool ID and index.
   *
   * @param string $tool_id
   *   The ID of the tool that created the artifact.
   * @param int $index
   *   The index assigned to this tool output.
   *
   * @return mixed|null
   *   The stored value, or NULL if no such artifact exists.
   */
  public function get(string $tool_id, int $index): mixed;

  /**
   * Checks whether a value exists for the given tool ID and index.
   *
   * @param string $tool_id
   *   The ID of the tool.
   * @param int $index
   *   The index of the artifact.
   *
   * @return bool
   *   TRUE if the artifact exists, FALSE otherwise.
   */
  public function has(string $tool_id, int $index): bool;

  /**
   * Returns all stored artifacts in the current storage scope.
   *
   * @return array
   *   An associative array of artifacts, keyed by "tool_id:index".
   */
  public function all(): array;

  /**
   * Returns the next available index for the given tool.
   *
   * Used to assign a unique index for artifact placeholders.
   */
  public function getNextIndex(string $tool_id): int;

}
