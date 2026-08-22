<?php

declare(strict_types=1);

namespace Drupal\project_browser\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\project_browser\Plugin\ProjectBrowserSourceManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Exposes a block plugin for every enabled source.
 *
 * @internal
 *   This is an internal part of Project Browser and may be changed or removed
 *   at any time. It should not be used by external code.
 */
final class BlockDeriver extends DeriverBase implements ContainerDeriverInterface {

  use AutowireTrait {
    create as traitCreate;
  }

  public function __construct(
    private readonly ProjectBrowserSourceManager $sourceManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id): self {
    return self::traitCreate($container);
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition): array {
    foreach ($this->sourceManager->getDefinitions() as $id => $definition) {
      ['label' => $label] = $definition;
      $this->derivatives[$id] = ['admin_label' => $label] + $base_plugin_definition;
    }
    return parent::getDerivativeDefinitions($base_plugin_definition);
  }

}
