<?php

declare(strict_types=1);

namespace Drupal\drupal_cms_helper\Drush\Commands;

use Composer\Console\Input\InputOption;
use Drupal\Core\DefaultContent\Existing;
use Drupal\Core\DefaultContent\Finder;
use Drupal\Core\DefaultContent\Importer;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drush\Commands\AutowireTrait;
use Drush\Style\DrushStyle;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @api
 *   The `content:import` command is part of Drupal CMS's developer-facing API
 *   and may be relied upon.
 *
 * @internal
 *   This is an internal part of Drupal CMS and may be changed or removed at any
 *   time without warning. External code should not interact with this class.
 */
#[AsCommand(
  name: 'content:import',
  description: "Imports content from a directory.",
  aliases: ['contim', 'cti'],
  hidden: TRUE,
)]
final class ImportContentCommand extends Command {

  use AutowireTrait;
  use StringTranslationTrait;

  public function __construct(private readonly Importer $importer) {
    parent::__construct();
  }

  #[\Override]
  protected function configure(): void {
    $this->addArgument(
      'dir',
      InputArgument::REQUIRED,
      'The path of the content directory, either absolute or relative to the Drupal root.',
    );
    $this->addOption(
      'skip-existing',
      's',
      InputOption::VALUE_NONE,
      'Skip content that already exists (based on UUID).',
    );
  }

  #[\Override]
  protected function execute(InputInterface $input, OutputInterface $output): int {
    $directory = $input->getArgument('dir');
    $io = new DrushStyle($input, $output);
    $finder = new Finder($directory);

    if (empty($finder->data)) {
      $message = (string) $this->t('No content found in @directory.', [
        '@directory' => $directory,
      ]);
      $io->warning($message);
    }
    $this->importer->importContent(
      $finder,
      $input->getOption('skip-existing') ? Existing::Skip : Existing::Error,
    );
    $io->success((string) $this->t('Content import complete.'));

    return self::SUCCESS;
  }

}
