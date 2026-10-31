<?php

namespace Drupal\drupal_cms_helper;

use Drupal\Core\Config\FileStorage;

/**
 * An immutable config storage that reads from a map of names to file paths.
 *
 * @internal
 *   This is an internal part of Drupal CMS and may be changed or removed at any
 *   time without warning. External code should not interact with this class.
 */
final class PathMapStorage extends FileStorage {

  /**
   * Behold, it is a constructor. As if you couldn't tell.
   *
   * @phpstan-param array<string, string> $map
   */
  public function __construct(
    public readonly array $map = [],
    $collection = parent::DEFAULT_COLLECTION,
  ) {
    parent::__construct('.', $collection);
  }

  /**
   * {@inheritdoc}
   */
  public function exists($name): bool {
    return array_key_exists($name, $this->map);
  }

  /**
   * {@inheritdoc}
   *
   * @phpstan-return string[]
   */
  public function listAll($prefix = ''): array {
    return array_filter(
      array_keys($this->map),
      fn (string $key): bool => str_starts_with($key, $prefix),
    );
  }

  /**
   * {@inheritdoc}
   *
   * @phpstan-param array<mixed> $data
   */
  public function write($name, array $data): never {
    throw new \LogicException('This storage is read-only.');
  }

  /**
   * {@inheritdoc}
   */
  public function rename($name, $new_name): never {
    throw new \LogicException('This storage is read-only.');
  }

  /**
   * {@inheritdoc}
   */
  public function delete($name): never {
    throw new \LogicException('This storage is read-only.');
  }

  /**
   * {@inheritdoc}
   */
  public function deleteAll($prefix = ''): never {
    throw new \LogicException('This storage is read-only.');
  }

  /**
   * {@inheritdoc}
   *
   * @phpstan-param string $name
   */
  public function getFilePath($name): string {
    assert($this->exists($name));
    return $this->map[$name];
  }

  /**
   * {@inheritdoc}
   *
   * @phpstan-return string[]
   */
  public function getAllCollectionNames(): array {
    // Collections aren't relevant to this storage.
    return (array) $this->getCollectionName();
  }

}
