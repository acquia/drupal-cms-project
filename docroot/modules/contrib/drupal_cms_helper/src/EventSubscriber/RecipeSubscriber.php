<?php

declare(strict_types=1);

namespace Drupal\drupal_cms_helper\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Installer\InstallerKernel;
use Drupal\Core\Recipe\RecipeAppliedEvent;
use Drupal\path_alias\AliasManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 *   This is an internal part of Drupal CMS and may be changed or removed at any
 *   time without warning. External code should not interact with this class.
 */
final readonly class RecipeSubscriber implements EventSubscriberInterface {

  public function __construct(
    private ConfigFactoryInterface $configFactory,
    private AliasManagerInterface $aliasManager,
    private EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      RecipeAppliedEvent::class => 'onApply',
    ];
  }

  public function onApply(RecipeAppliedEvent $event): void {
    // @todo Remove when https://www.drupal.org/i/1503146 is released.
    $config = $this->configFactory->getEditable('system.site');

    $front_saved_path = $config->get('page.front');
    $front_system_path = $this->aliasManager->getPathByAlias($front_saved_path);
    if ($front_system_path !== $front_saved_path) {
      $config->set('page.front', $front_system_path)->save();
    }

    // When installing Drupal using a monolithic site template at the command
    // line, user 1 may be statically cached with stale field definitions, which
    // can cause errors when the account is modified during the installation by
    // \Drupal\Core\Installer\Form\SiteConfigureForm. To prevent that, clear the
    // static cache for user 1.
    // @todo Remove when https://www.drupal.org/i/3578151 is released.
    if ($event->recipe->type === 'Site' && PHP_SAPI === 'cli' && InstallerKernel::installationAttempted()) {
      $this->entityTypeManager->getStorage('user')->resetCache([1]);
    }
  }

}
