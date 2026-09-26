<?php

declare(strict_types=1);

namespace Drupal\cva;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Drupal\cva\Twig\CvaTwigEnvironment;

/**
 * Replaces the Twig environment on older Drupal versions that lack CVA support.
 */
final class CvaServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container): void {
    // Replace the Twig environment service with our enhanced version. Don't do
    // anything in 11.4 and later because it supports CVA natively.
    if ($container->hasDefinition('twig') && version_compare(\Drupal::VERSION, '11.4.0', '<')) {
      $definition = $container->getDefinition('twig');
      $definition->setClass(CvaTwigEnvironment::class);
    }
  }

}
