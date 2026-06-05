<?php

namespace Drupal\acquia_disable_package_manager;

use Acquia\DrupalEnvironmentDetector\AcquiaDrupalEnvironmentDetector;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceModifierInterface;
use Drupal\package_manager\PathExcluder\SiteFilesExcluder;
use Drupal\project_browser\Activator\ModuleActivator;
use Drupal\project_browser\Activator\RecipeActivator;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Alters container service definitions.
 */
final readonly class AcquiaDisablePackageManagerServiceProvider implements ServiceModifierInterface {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container): void {
    if (AcquiaDrupalEnvironmentDetector::isAhEnv()) {
      if ($container->hasDefinition(SiteFilesExcluder::class)) {
        $definition = $container->getDefinition(SiteFilesExcluder::class);
        // @see package_manager.services.yml
        $wrappers = $definition->getArgument('$wrappers');
        assert(is_array($wrappers));
        // Don't bother excluding private files; they're outside the project root on
        // Acquia hosting.
        $wrappers = array_diff($wrappers, ['private']);
        $definition->setArgument('$wrappers', $wrappers);
      }

      if ($container->hasDefinition(ModuleActivator::class)) {
        $container->register('acquia.module_activator', AhActivator::class)
          ->setDecoratedService(ModuleActivator::class)
          ->setArguments([
            new Reference('.inner'),
          ])
          ->setPublic(FALSE);
      }

      if ($container->hasDefinition(RecipeActivator::class)) {
        $container->getDefinition(RecipeActivator::class)
          ->clearTag('project_browser.activator');

        $container->register('acquia.recipe_activator', AhActivator::class)
          ->addTag('project_browser.activator')
          ->setArguments([
            new Reference(RecipeActivator::class),
          ])
          ->setPublic(FALSE);
      }
    }
  }

}
