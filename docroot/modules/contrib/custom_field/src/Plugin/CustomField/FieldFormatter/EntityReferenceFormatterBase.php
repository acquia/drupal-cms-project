<?php

declare(strict_types=1);

namespace Drupal\custom_field\Plugin\CustomField\FieldFormatter;

use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\custom_field\Plugin\CustomFieldFormatterBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Parent plugin for decimal and integer formatters.
 */
class EntityReferenceFormatterBase extends CustomFieldFormatterBase {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The entity repository.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected EntityRepositoryInterface $entityRepository;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->entityRepository = $container->get('entity.repository');

    return $instance;
  }

  /**
   * Checks access to the given entity.
   *
   * By default, entity 'view' access is checked. However, a subclass can choose
   * to exclude certain items from entity access checking by immediately
   * granting access.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to check.
   *
   * @return bool|\Drupal\Core\Access\AccessResultInterface
   *   A cacheable access result.
   */
  protected function checkAccess(EntityInterface $entity): bool|AccessResultInterface {
    return $entity->access('view', NULL, TRUE);
  }

}
