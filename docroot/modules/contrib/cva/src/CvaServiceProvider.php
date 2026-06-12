<?php

declare(strict_types=1);

namespace Drupal\cva;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Drupal\cva\Twig\CvaTwigEnvironment;
use Symfony\Component\DependencyInjection\Reference;

final class CvaServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container): void {
    // Replace the Twig environment service with our enhanced version.
    if ($container->hasDefinition('twig')) {
      $definition = $container->getDefinition('twig');
      $definition->setClass(CvaTwigEnvironment::class);
    }
  }

}

