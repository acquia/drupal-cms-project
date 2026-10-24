<?php

declare(strict_types=1);

namespace Drupal\trash\Drush\Commands;

use Consolidation\AnnotatedCommand\CommandData;
use Consolidation\AnnotatedCommand\Hooks\HookManager;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\trash\TrashManagerInterface;
use Drush\Attributes as CLI;
use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;

/**
 * Drush hooks for integrating the Trash module with other commands.
 */
final class TrashHooks extends DrushCommands {

  use AutowireTrait;

  /**
   * The current trash context.
   */
  protected ?string $existingTrashContext = NULL;

  public function __construct(
    protected ModuleHandlerInterface $moduleHandler,
    protected TrashManagerInterface $trashManager,
  ) {
    parent::__construct();
  }

  /**
   * Add trash related options to the command.
   */
  #[CLI\Hook(type: HookManager::OPTION_HOOK, target: 'entity:delete')]
  public function hookOptions(Command $command): void {
    if (!$command->getDefinition()->hasOption('skip-trash')) {
      $command->addOption(
        'skip-trash',
        '',
        InputOption::VALUE_NONE,
        'Permanently delete entities instead of moving to the trash. This includes already trashed entities.'
      );
    }
  }

  /**
   * Set trash context to ignore if skip-trash option is used.
   */
  #[CLI\Hook(type: HookManager::PRE_COMMAND_HOOK, target: 'entity:delete')]
  public function hookPreCommand(CommandData $commandData): void {
    if (!$commandData->input()->getOption('skip-trash')) {
      return;
    }

    $this->existingTrashContext = $this->trashManager->getTrashContext();
    $this->trashManager->setTrashContext('ignore');
  }

  /**
   * Restore previous trash context.
   */
  #[CLI\Hook(type: HookManager::POST_COMMAND_HOOK, target: 'entity:delete')]
  public function hookPostCommand(?array $result, CommandData $commandData): void {
    if (!$commandData->input()->getOption('skip-trash')) {
      return;
    }

    $this->trashManager->setTrashContext($this->existingTrashContext);
  }

}
