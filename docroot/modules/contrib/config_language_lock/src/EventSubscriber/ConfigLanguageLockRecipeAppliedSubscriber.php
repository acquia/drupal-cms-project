<?php

declare(strict_types=1);

namespace Drupal\config_language_lock\EventSubscriber;

use Drupal\config_language_lock\ConfigLanguageLockConfigManager;
use Drupal\Core\Recipe\RecipeAppliedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Normalizes recipe-imported config language to the configured lock language.
 */
class ConfigLanguageLockRecipeAppliedSubscriber implements EventSubscriberInterface {

  /**
   * Constructs a recipe subscriber.
   */
  public function __construct(
    protected readonly ConfigLanguageLockConfigManager $configManager,
  ) {
  }

  /**
   * Reacts after recipe apply to align recipe-shipped config langcode values.
   *
   * Config entities created by config actions during recipe execution are
   * handled by hook_entity_presave() as well already.
   */
  public function onRecipeApplied(RecipeAppliedEvent $event): void {
    if ($this->configManager->getLockedLangcode() === NULL) {
      return;
    }

    $names = $event->recipe->config->getConfigStorage()->listAll();
    if (empty($names)) {
      return;
    }

    $this->configManager->updateConfigForLockedLanguageSwitch($names);
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      RecipeAppliedEvent::class => 'onRecipeApplied',
    ];
  }

}
