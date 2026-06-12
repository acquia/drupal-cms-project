<?php

namespace Drupal\eca\Drush\Commands;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drush\Attributes\Command;
use Drush\Attributes\Usage;
use Drush\Commands\DrushCommands;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * ECA Drush command file.
 */
final class EcaCommands extends DrushCommands {

  /**
   * ECA config entity storage manager.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected EntityStorageInterface $configStorage;

  /**
   * Constructs an EcaCommands object.
   */
  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct();
    $this->configStorage = $entityTypeManager->getStorage('eca');
  }

  /**
   * Return an instance of these Drush commands.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The container.
   *
   * @return \Drupal\eca\Drush\Commands\EcaCommands
   *   The instance of Drush commands.
   */
  public static function create(ContainerInterface $container): EcaCommands {
    return new EcaCommands(
      $container->get('entity_type.manager'),
    );
  }

  /**
   * Rebuild the state of subscribed events.
   */
  #[Command(name: 'eca:subscriber:rebuild', aliases: [])]
  #[Usage(name: 'eca:subscriber:rebuild', description: 'Rebuild the state of subscribed events.')]
  public function rebuildSubscribedEvents(): void {
    /** @var \Drupal\eca\Entity\EcaStorage $storage */
    $storage = $this->configStorage;
    $storage->rebuildSubscribedEvents();
  }

}
