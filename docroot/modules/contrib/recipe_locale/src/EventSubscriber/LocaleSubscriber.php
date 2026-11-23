<?php

declare(strict_types=1);

namespace Drupal\recipe_locale\EventSubscriber;

use Drupal\Core\Config\ConfigCrudEvent;
use Drupal\Core\Config\ConfigEvents;
use Drupal\recipe_locale\LocaleIntegration;
use Drupal\recipe_locale\RecipeTracker;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Downloads translations for recipes that are new on the list.
 *
 * Only registered when Locale is installed.
 *
 * @see \Drupal\recipe_locale\RecipeLocaleServiceProvider
 */
final class LocaleSubscriber implements EventSubscriberInterface {

  public function __construct(
    private readonly LocaleIntegration $locale,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      ConfigEvents::SAVE => 'onConfigSave',
    ];
  }

  /**
   * Reacts to changes of the recorded list.
   *
   * The list changes when a recipe is applied on this site, and when the
   * config is imported from another site, for example on deployment. Both
   * end up here.
   */
  public function onConfigSave(ConfigCrudEvent $event): void {
    $config = $event->getConfig();
    if ($config->getName() !== RecipeTracker::CONFIG_NAME) {
      return;
    }
    $before = $config->getOriginal('recipes') ?? [];
    $after = $config->get('recipes') ?? [];
    $changed = [];
    foreach ($after as $name => $recipe) {
      if (($before[$name] ?? NULL) !== $recipe) {
        $changed[] = $name;
      }
    }
    if ($changed) {
      $this->locale->update($changed);
    }
  }

}
