<?php

declare(strict_types=1);

namespace Drupal\custom_field\Plugin\DataType;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\TypedData\PrimitiveInterface;

/**
 * Interface for custom_field reference field data.
 */
interface CustomFieldEntityReferenceInterface extends PrimitiveInterface {

  /**
   * Helper function to load an entity.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   The entity object or null.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getEntity(): ?EntityInterface;

  /**
   * Helper function to load an entity by UUID.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   The entity object or null.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getEntityByUuid(string $uuid): ?EntityInterface;

}
