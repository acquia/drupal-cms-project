<?php

declare(strict_types=1);

namespace Drupal\drupal_cms_helper\Plugin\ConfigAction;

use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Config\Action\Attribute\ConfigAction;
use Drupal\Core\Config\Action\ConfigActionPluginInterface;
use Drupal\Core\DependencyInjection\AutowiredInstanceTrait;
use Drupal\Core\Menu\MenuLinkManagerInterface;
use Drupal\Core\Menu\StaticMenuLinkOverridesInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Overrides static menu links.
 *
 * Essentially this is a thin wrapper around
 * \Drupal\Core\Menu\StaticMenuLinkOverridesInterface::saveOverride().
 *
 * An example of using this in a recipe:
 *
 * @code
 * core.menu.static_menu_link_overrides:
 *   overrideMenuLinks:
 *     navigation.content.node_type.blog:
 *       enabled: false
 * @endcode
 *
 * @internal
 *  This is an internal part of Drupal CMS and may be changed or removed at any
 *  time without warning. External code should not interact with this class.
 *
 * @todo Remove when https://www.drupal.org/i/3569949 is released.
 */
#[ConfigAction(
  id: 'overrideMenuLinks',
  admin_label: new TranslatableMarkup('Override static menu links'),
)]
final readonly class OverrideMenuLinks implements ConfigActionPluginInterface, ContainerFactoryPluginInterface {

  use AutowiredInstanceTrait;

  public function __construct(
    private StaticMenuLinkOverridesInterface $linkOverrides,
    private MenuLinkManagerInterface $menuLinkManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return self::createInstanceAutowired($container);
  }

  /**
   * {@inheritdoc}
   */
  public function apply(string $configName, mixed $value): void {
    assert(is_array($value));

    // We want to operate on the latest information, since Navigation compiles
    // these links dynamically.
    $this->menuLinkManager->rebuild();

    foreach ($value as $link_id => $override) {
      try {
        $this->linkOverrides->saveOverride($link_id, $override + $this->menuLinkManager->getDefinition($link_id));
      }
      catch (PluginNotFoundException) {
        continue;
      }
    }
  }

}
