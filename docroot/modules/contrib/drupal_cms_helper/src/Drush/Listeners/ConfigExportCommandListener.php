<?php

declare(strict_types=1);

namespace Drupal\drupal_cms_helper\Drush\Listeners;

use Drupal\Core\Config\ConfigEvents;
use Drupal\Core\DependencyInjection\ClassResolverInterface;
use Drupal\drupal_cms_helper\GenericConfigurationListener;
use Drush\Commands\AutowireTrait;
use Drush\Event\ConsoleDefinitionsEvent;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\Console\Exception\CommandNotFoundException;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Implements the `--generic` option for the `config:export` command.
 *
 * @api
 *   The `--generic` option is part of Drupal CMS's developer-facing API and
 *   may be relied upon.
 *
 * @internal
 *   This class is an internal part of Drupal CMS and may be changed or removed
 *   at any time without warning. External code should not interact with it.
 */
#[AsEventListener(method: 'onCommandDefinitions')]
#[AsEventListener(method: 'onCommand')]
final readonly class ConfigExportCommandListener {

  use AutowireTrait;

  public function __construct(
    private EventDispatcherInterface $eventDispatcher,
    private ClassResolverInterface $classResolver,
  ) {}

  public function onCommandDefinitions(ConsoleDefinitionsEvent $event): void {
    try {
      $event->getApplication()
        ->get('config:export')
        ->addOption(
          'generic',
          description: 'Remove UUIDs and _core data from exported configuration, for recipe readiness.',
        );
    }
    catch (CommandNotFoundException) {
      // If the command doesn't exist, there's nothing we can do.
    }
  }

  public function onCommand(ConsoleCommandEvent $event) {
    if ($event->getCommand()->getName() === 'config:export' && $event->getInput()->getOption('generic')) {
      $listener = $this->classResolver->getInstanceFromDefinition(GenericConfigurationListener::class);
      assert($listener instanceof GenericConfigurationListener);
      $this->eventDispatcher->addListener(ConfigEvents::STORAGE_TRANSFORM_EXPORT, $listener);
    }
  }

}
