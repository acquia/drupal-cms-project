<?php

declare(strict_types=1);

namespace Drupal\drupal_cms_helper\Plugin\ConfigAction;

use Drupal\canvas\ComponentSource\ComponentSourceManager;
use Drupal\canvas\Entity\ComponentInterface;
use Drupal\canvas\Entity\ComponentTreeEntityInterface;
use Drupal\Core\Config\Action\Attribute\ConfigAction;
use Drupal\Core\Config\Action\ConfigActionPluginInterface;
use Drupal\Core\Config\ConfigManagerInterface;
use Drupal\Core\DependencyInjection\AutowiredInstanceTrait;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Sets a component tree on a Canvas configuration entity.
 *
 * This is a wrapper around ComponentTreeEntityInterface::setComponentTree()
 * which fills in missing component versions. This is most useful for setting up
 * component trees containing component versions that aren't predictable because
 * they target components provided by *another* recipe (or a module/theme it
 * installs).
 *
 * This action should be used sparingly. If you have a component tree that ONLY
 * uses components which are under the recipe's direct control -- e.g., SDCs
 * from a bespoke theme, or code components -- then you probably don't need this
 * action. But if you need components that may have been generated previously
 * (by a base recipe, for instance), then this action can get past the
 * fact that the component's version may not be knowable ahead of time.
 *
 * If you do use this action, you want to be certain that you run it when all
 * components are *stable* -- meaning they have either been created, or brought
 * up to date and reflect the current state of the site. Component versions are
 * deterministic, but depend heavily on how the site is configured and what
 * extensions are installed. So, to be safe, this config action should be used
 * *last*.
 *
 * Due to the complexity of the problem this action works around, it'll be a
 * while before this action is obsolete. Nonetheless, here's a partial list of
 * relevant issues that would need to be addressed first:
 * - https://git.drupalcode.org/project/drupal_cms/-/work_items/3591419
 * - https://git.drupalcode.org/project/canvas/-/work_items/3591898
 * - https://git.drupalcode.org/project/canvas/-/work_items/3566813
 * - https://git.drupalcode.org/project/canvas/-/work_items/3565712
 * - https://git.drupalcode.org/project/canvas/-/work_items/3571366
 * - https://git.drupalcode.org/project/canvas/-/work_items/3591659
 * - https://www.drupal.org/project/drupal/issues/3619279
 * - https://www.drupal.org/project/drupal/issues/3560179
 * - https://www.drupal.org/project/drupal/issues/3613607
 *
 * @api
 *   This is part of Drupal CMS's developer-facing API and may be relied upon.
 */
#[ConfigAction(
  id: 'setComponentTree',
  admin_label: new TranslatableMarkup('Set component tree'),
  entity_types: [
    'content_template',
    'page_region',
    'page_variant',
    'pattern',
  ],
)]
final class SetComponentTree implements ConfigActionPluginInterface, ContainerFactoryPluginInterface {

  use AutowiredInstanceTrait;

  /**
   * Whether components have been regenerated in this request.
   */
  private static bool $generated = FALSE;

  public function __construct(
    private readonly ConfigManagerInterface $configManager,
    private readonly ComponentSourceManager $componentSourceManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function apply(string $configName, mixed $value): void {
    assert(str_starts_with($configName, 'canvas.') && is_array($value));

    // Ensure all components are generated and up-to-date in this request.
    if (self::$generated === FALSE) {
      $this->componentSourceManager->generateComponents();
      self::$generated = TRUE;
    }

    $entity = $this->configManager->loadConfigEntityByName($configName);
    assert($entity instanceof ComponentTreeEntityInterface);

    $component_storage = $this->configManager->getEntityTypeManager()
      ->getStorage('component');
    foreach ($value as $key => $item) {
      assert(is_array($item) && array_key_exists('component_id', $item), "$key has no component ID.");

      if (isset($item['component_version'])) {
        continue;
      }
      $component = $component_storage->load($item['component_id']);
      assert($component instanceof ComponentInterface);
      $value[$key]['component_version'] = $component->getActiveVersion();
    }
    // @phpstan-ignore-next-line
    $entity->setComponentTree($value)->save();
  }

  /**
   * {@inheritdoc}
   *
   * @phpstan-param array<mixed> $configuration
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return self::createInstanceAutowired($container);
  }

}
