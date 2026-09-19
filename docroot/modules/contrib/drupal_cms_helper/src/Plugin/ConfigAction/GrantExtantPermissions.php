<?php

declare(strict_types=1);

namespace Drupal\drupal_cms_helper\Plugin\ConfigAction;

use Drupal\Core\Config\Action\Attribute\ConfigAction;
use Drupal\Core\Config\Action\ConfigActionPluginInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\user\PermissionHandlerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Grants permissions to a role, ignoring ones that don't exist.
 *
 * This helps recipes integrate with each other in a lightweight, optional way.
 * An example is drupal_cms_admin_ui and drupal_cms_content_type_base -- the
 * former recipe provides a dashboard that is useful to the content_editor role,
 * which provided by the latter recipe. Rather than having these two otherwise
 * unrelated recipes depend on each other, this action allows the dashboard to
 * be available to content editors if and only if the dashboard's permissions
 * are available (which means the dashboard exists).
 *
 * Syntactically, this is identical to the core `grantPermissions` action,
 * except it can only be called with the plural form.
 *
 * @todo Remove when https://www.drupal.org/i/3577548 is released.
 *
 * @internal
 *   This is an internal part of Drupal CMS and may be changed or removed at any
 *   time without warning. External code should not interact with this class or
 *   use this action.
 */
#[ConfigAction(
  id: 'grantPermissionsIfExist',
  admin_label: new TranslatableMarkup('Grant permissions if they exist'),
  entity_types: ['user_role'],
)]
final readonly class GrantExtantPermissions implements ConfigActionPluginInterface, ContainerFactoryPluginInterface {

  public function __construct(
    private ConfigActionPluginInterface $decorated,
    private PermissionHandlerInterface $permissionHandler,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    $decorated = $container->get('plugin.manager.config_action')
      ->createInstance('entity_method:user.role:grantPermissions');

    return new self(
      $decorated,
      $container->get(PermissionHandlerInterface::class),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function apply(string $configName, mixed $value): void {
    $value = is_scalar($value) ? [$value] : $value;
    assert(is_array($value));

    // Ignore unknown permissions.
    $value = array_intersect($value, array_keys($this->permissionHandler->getPermissions()));
    if ($value) {
      $this->decorated->apply($configName, $value);
    }
  }

}
