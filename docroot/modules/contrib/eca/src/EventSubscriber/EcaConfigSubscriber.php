<?php

namespace Drupal\eca\EventSubscriber;

use Drupal\Core\Config\ConfigCrudEvent;
use Drupal\Core\Config\ConfigEvents;
use Drupal\Core\Config\ConfigRenameEvent;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Rebuilds the subscribed events index when ECA model config changes.
 *
 * Saving or deleting an ECA model through the entity API already rebuilds the
 * `eca.subscribed` state in EcaStorage. Raw configuration writes, for example
 * through the config factory or `drush config:set`, bypass the entity API and
 * would otherwise leave that index stale.
 *
 * @see \Drupal\eca\Entity\EcaStorage::rebuildSubscribedEvents()
 */
class EcaConfigSubscriber implements EventSubscriberInterface {

  /**
   * The config name prefix of ECA models.
   */
  protected const string CONFIG_PREFIX = 'eca.eca.';

  /**
   * The config keys the subscribed events index is derived from.
   *
   * The index lists the events of enabled models that are no templates,
   * sorted by weight and label; the label is a third-party setting of the
   * Modeler API.
   *
   * @see \Drupal\eca\Entity\EcaStorage::doRebuildSubscribedEvents()
   * @see \Drupal\eca\Entity\EcaStorage::doRebuildTemplates()
   * @see \Drupal\eca\Entity\Eca::label()
   */
  protected const array INDEXED_KEYS = [
    'status',
    'template',
    'weight',
    'events',
    'third_party_settings.modeler_api.label',
  ];

  /**
   * Constructs a new EcaConfigSubscriber object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      ConfigEvents::SAVE => 'onSave',
      ConfigEvents::DELETE => 'onDelete',
      ConfigEvents::RENAME => 'onRename',
    ];
  }

  /**
   * Rebuilds the index when a saved ECA model changed an indexed key.
   *
   * @param \Drupal\Core\Config\ConfigCrudEvent $event
   *   The config event.
   */
  public function onSave(ConfigCrudEvent $event): void {
    $config = $event->getConfig();
    if (!str_starts_with($config->getName(), self::CONFIG_PREFIX)) {
      return;
    }
    // New config has no original data, every key counts as changed.
    if ($config->getOriginal() === []) {
      $this->rebuild();
      return;
    }
    foreach (self::INDEXED_KEYS as $key) {
      if ($event->isChanged($key)) {
        $this->rebuild();
        return;
      }
    }
  }

  /**
   * Rebuilds the index when an ECA model got deleted.
   *
   * @param \Drupal\Core\Config\ConfigCrudEvent $event
   *   The config event.
   */
  public function onDelete(ConfigCrudEvent $event): void {
    if (str_starts_with($event->getConfig()->getName(), self::CONFIG_PREFIX)) {
      $this->rebuild();
    }
  }

  /**
   * Rebuilds the index when an ECA model got renamed.
   *
   * @param \Drupal\Core\Config\ConfigRenameEvent $event
   *   The config event.
   */
  public function onRename(ConfigRenameEvent $event): void {
    if (str_starts_with($event->getOldName(), self::CONFIG_PREFIX) || str_starts_with($event->getConfig()->getName(), self::CONFIG_PREFIX)) {
      $this->rebuild();
    }
  }

  /**
   * Rebuilds the subscribed events index.
   *
   * The rebuild loads all models fresh from config, which also replaces any
   * outdated model in the static entity cache.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function rebuild(): void {
    /** @var \Drupal\eca\Entity\EcaStorage $storage */
    $storage = $this->entityTypeManager->getStorage('eca');
    $storage->rebuildSubscribedEvents();
  }

}
