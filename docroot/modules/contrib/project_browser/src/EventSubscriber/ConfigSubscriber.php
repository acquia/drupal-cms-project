<?php

declare(strict_types=1);

namespace Drupal\project_browser\EventSubscriber;

use Drupal\Component\Plugin\Discovery\CachedDiscoveryInterface;
use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\Config\ConfigCrudEvent;
use Drupal\Core\Config\ConfigEvents;
use Drupal\project_browser\Plugin\ProjectBrowserSourceManager;
use Drupal\project_browser\ProjectRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Reacts when sources are enabled or disabled in configuration.
 *
 * @internal
 *    This is an internal part of Project Browser and may be changed or removed
 *    at any time. It should not be used by external code.
 */
final class ConfigSubscriber implements EventSubscriberInterface {

  public function __construct(
    private readonly BlockManagerInterface $blockManager,
    private readonly ProjectBrowserSourceManager $sourceManager,
    private readonly ProjectRepository $projectRepository,
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
   * Reacts when config is saved.
   *
   * @param \Drupal\Core\Config\ConfigCrudEvent $event
   *   The event object.
   */
  public function onConfigSave(ConfigCrudEvent $event): void {
    $config = $event->getConfig();
    if ($config->getName() === 'project_browser.admin_settings' && $event->isChanged('enabled_sources')) {
      // Ensure that the cached source and block plugin definitions stay in sync
      // with the enabled sources.
      $this->sourceManager->clearCachedDefinitions();
      assert($this->blockManager instanceof CachedDiscoveryInterface);
      $this->blockManager->clearCachedDefinitions();

      // Clear stored project data for the sources that have been disabled, and
      // invalidate any cached data associated with them.
      $disabled_sources = array_keys(array_diff_key(
        $config->getOriginal('enabled_sources') ?? [],
        $config->get('enabled_sources'),
      ));
      array_walk($disabled_sources, $this->projectRepository->clear(...));
    }
  }

}
