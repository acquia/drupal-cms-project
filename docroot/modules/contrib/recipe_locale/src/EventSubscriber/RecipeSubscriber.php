<?php

declare(strict_types=1);

namespace Drupal\recipe_locale\EventSubscriber;

use Drupal\Core\Recipe\RecipeAppliedEvent;
use Drupal\recipe_locale\RecipeTracker;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Records recipes when they are applied.
 */
final class RecipeSubscriber implements EventSubscriberInterface {

  public function __construct(
    private readonly RecipeTracker $tracker,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      RecipeAppliedEvent::class => 'onRecipeApplied',
    ];
  }

  /**
   * Records a recipe when it has been applied.
   */
  public function onRecipeApplied(RecipeAppliedEvent $event): void {
    $this->tracker->record($event->recipe);
  }

}
