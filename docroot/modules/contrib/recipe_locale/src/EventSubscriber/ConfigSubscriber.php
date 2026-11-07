<?php

declare(strict_types=1);

namespace Drupal\recipe_locale\EventSubscriber;

use Drupal\Core\Config\ConfigCrudEvent;
use Drupal\Core\Config\ConfigEvents;
use Drupal\Core\Config\ConfigRenameEvent;
use Drupal\recipe_locale\RecipeTracker;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Drops the stored copy of a config object the site deletes or renames.
 */
final class ConfigSubscriber implements EventSubscriberInterface {

  public function __construct(
    private readonly RecipeTracker $tracker,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      ConfigEvents::DELETE => 'onConfigDelete',
      ConfigEvents::RENAME => 'onConfigRename',
    ];
  }

  /**
   * Drops the copy of a deleted config object.
   */
  public function onConfigDelete(ConfigCrudEvent $event): void {
    $this->drop($event->getConfig()->getName());
  }

  /**
   * Drops the copy of a renamed config object, under its old name.
   */
  public function onConfigRename(ConfigRenameEvent $event): void {
    $this->drop($event->getOldName());
  }

  /**
   * Removes the copy of one config object, if there is one.
   */
  private function drop(string $name): void {
    // The module's own objects never have copies, and dropping a copy can
    // delete one of them, which would end up here again.
    if (str_starts_with($name, 'recipe_locale.')) {
      return;
    }
    $this->tracker->removeCopy($name);
  }

}
