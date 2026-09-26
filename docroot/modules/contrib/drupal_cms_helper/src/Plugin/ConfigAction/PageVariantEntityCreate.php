<?php

namespace Drupal\drupal_cms_helper\Plugin\ConfigAction;

use Drupal\Core\Config\Action\ConfigActionPluginInterface;
use Drupal\Core\Config\Action\Plugin\ConfigAction\EntityCreate;
use Drupal\Core\Config\ConfigManagerInterface;
use Drupal\Core\DependencyInjection\AutowiredInstanceTrait;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Decorates the `create` and `createIfNotExists` actions for page variants.
 *
 * @see \Drupal\drupal_cms_helper\Plugin\ConfigAction\SetComponentTree for an
 *   explaination of what this does. The additional step of decorating the
 *   entity creation actions is necessary for Canvas configuration that cannot
 *   be created with an empty component tree.
 *
 * @internal
 *   This is an internal part of Drupal CMS and may be changed or removed at any
 *   time without warning. External code should not interact with this class.
 */
final class PageVariantEntityCreate implements ConfigActionPluginInterface, ContainerFactoryPluginInterface {

  use AutowiredInstanceTrait;

  private function __construct(
    private readonly ConfigActionPluginInterface $entityCreateAction,
    private readonly SetComponentTree $setComponentTreeAction,
    private readonly ConfigManagerInterface $configManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function apply(string $configName, mixed $value): void {
    assert(is_array($value));

    // Page variants cannot be created with an empty component tree, so they
    // need special handling.
    if ($this->configManager->getEntityTypeIdByName($configName) === 'page_variant') {
      $this->setComponentTreeAction->setComponentVersions($value['component_tree']);
    }
    $this->entityCreateAction->apply($configName, $value);
  }

  /**
   * {@inheritdoc}
   *
   * @phpstan-param array<mixed> $configuration
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return self::createInstanceAutowired(
      $container,
      EntityCreate::create($container, $configuration, $plugin_id, $plugin_definition),
      SetComponentTree::create($container, [], 'setComponentTree', NULL),
    );
  }

}
