<?php

declare(strict_types=1);

namespace Drupal\recipe_locale;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Drupal\locale\File\LocaleFileManager;
use Drupal\locale\LocaleFetch;
use Drupal\locale\LocaleProjectRepository;
use Drupal\locale\LocaleSource;
use Drupal\recipe_locale\EventSubscriber\LocaleSubscriber;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Registers the Interface Translation integration when that module is on.
 *
 * Recipes are recorded whether or not Locale is installed. The services that
 * hand them to Locale need Locale's own services, so they only exist once
 * Locale is installed.
 */
final class RecipeLocaleServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container): void {
    $modules = $container->getParameter('container.modules');
    if (!isset($modules['locale'])) {
      return;
    }
    $container->register(LocaleIntegration::class, LocaleIntegration::class)
      ->setPublic(TRUE)
      ->setArguments([
        new Reference(RecipeTracker::class),
        new Reference('config.factory'),
        new Reference(LocaleProjectRepository::class),
        new Reference(LocaleSource::class),
        new Reference(LocaleFileManager::class),
        new Reference(LocaleFetch::class),
        new Reference('locale.config_manager'),
        new Reference('language_manager'),
      ]);
    // Let Locale find the original copy of the config that recipes shipped, so
    // it translates that config like the config modules ship.
    if ($container->hasDefinition('locale.default.config.storage')) {
      $container->getDefinition('locale.default.config.storage')
        ->setClass(RecipeDefaultConfigStorage::class)
        ->addArgument(new Reference(RecipeTracker::class));
    }
    $container->register(LocaleSubscriber::class, LocaleSubscriber::class)
      ->setPublic(TRUE)
      ->setArguments([new Reference(LocaleIntegration::class)])
      ->addTag('event_subscriber');
  }

}
