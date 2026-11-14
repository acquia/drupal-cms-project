<?php

declare(strict_types=1);

namespace Drupal\cva\Twig;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Template\TwigEnvironment as CoreTwigEnvironment;
use Twig\Extension\SandboxExtension;
use Twig\Loader\LoaderInterface;

/**
 * A Twig environment that adds the CVA class to the sandbox allowed list.
 */
final class CvaTwigEnvironment extends CoreTwigEnvironment {

  /**
   * {@inheritdoc}
   */
  public function __construct(
    $root,
    CacheBackendInterface $cache,
    $twig_extension_hash,
    StateInterface $state,
    LoaderInterface $loader,
    array $options = [],
  ) {
    parent::__construct($root, $cache, $twig_extension_hash, $state, $loader, $options);

    // Replace the sandbox policy with one that allows CVA method calls.
    // @see \Twig\Extension\SandboxExtension::setSecurityPolicy()
    $sandbox = $this->getExtension(SandboxExtension::class);
    if ($sandbox instanceof SandboxExtension) {
      $sandbox->setSecurityPolicy(new CvaSandboxPolicy());
    }
  }

}
