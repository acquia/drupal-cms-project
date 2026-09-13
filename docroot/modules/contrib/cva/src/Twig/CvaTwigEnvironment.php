<?php

declare(strict_types=1);

namespace Drupal\cva\Twig;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Template\TwigEnvironment as CoreTwigEnvironment;
use Twig\Extension\SandboxExtension;
use Twig\Loader\LoaderInterface;

final class CvaTwigEnvironment extends CoreTwigEnvironment {

  /**
   * {@inheritdoc}
   */
  public function __construct($root, CacheBackendInterface $cache, $twig_extension_hash, StateInterface $state, LoaderInterface $loader, array $options = []) {
    // Call parent constructor which sets up everything including the default sandbox.
    parent::__construct($root, $cache, $twig_extension_hash, $state, $loader, $options);

    // Modify the existing sandbox extension's policy to use our enhanced one.
    $sandbox = $this->getExtension(SandboxExtension::class);
    if ($sandbox) {
      // Use reflection to replace the policy in the existing sandbox extension.
      $reflection = new \ReflectionClass($sandbox);
      $policyProperty = $reflection->getProperty('policy');
      $policyProperty->setValue($sandbox, new CvaSandboxPolicy());
    }
  }

}

