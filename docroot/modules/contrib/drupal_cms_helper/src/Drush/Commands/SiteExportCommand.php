<?php

declare(strict_types=1);

namespace Drupal\drupal_cms_helper\Drush\Commands;

use Drupal\drupal_cms_helper\SiteExporter;
use Drush\Commands\AutowireTrait;
use Drush\Style\DrushStyle;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Drush command to export the site as a recipe.
 *
 * @api
 *   The `site:export` command is part of Drupal CMS's developer-facing API and
 *   may be relied upon.
 *
 * @internal
 *   This is an internal part of Drupal CMS and may be changed or removed at any
 *   time without warning. External code should not interact with this class.
 */
#[AsCommand(
  name: 'site:export',
  description: "Exports the site's configuration and content as a recipe.",
  aliases: ['siex', 'six'],
)]
final class SiteExportCommand extends Command {

  use AutowireTrait;

  public function __construct(private readonly SiteExporter $exporter) {
    parent::__construct();
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  protected function configure(): void {
    $this->addOption(
      'destination',
      NULL,
      InputOption::VALUE_REQUIRED,
      'The directory where the site should be exported.',
      $this->exporter->getRecipePath('drupal/site_export'),
    );
    $this->addOption(
      'overwrite',
      NULL,
      InputOption::VALUE_NONE,
      'Whether to overwrite the destination directory if it already exists.',
    );
    $this->addOption(
      'dev',
      NULL,
      InputOption::VALUE_NONE,
      'Export in development mode, enabling theme development settings.',
    );
    $this->addOption(
      'assets',
      NULL,
      InputOption::VALUE_NONE | InputOption::VALUE_NEGATABLE,
      'Copy additional assets into the exported recipe, such as generic tests, CI configuration, example README, etc.',
    );
    $this->addOption(
      'base',
      NULL,
      InputOption::VALUE_REQUIRED,
      'Deprecated. Use --assets instead.',
    );
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  protected function execute(InputInterface $input, OutputInterface $output): int {
    $io = new DrushStyle($input, $output);

    $destination = $input->getOption('destination');
    if (empty($destination)) {
      $io->error('No destination was given, and a default location could not be determined.');
      return self::FAILURE;
    }
    elseif (is_dir($destination) && empty($input->getOption('overwrite'))) {
      $io->error("The destination directory $destination already exists.");
      return self::FAILURE;
    }
    $with_assets = $input->getOption('assets') ?? TRUE;
    $this->exporter->export(
      $destination,
      $with_assets ? dirname(__DIR__, 3) . '/assets/site-export' : NULL,
      $input->getOption('dev'),
    );
    $io->success("Recipe created at $destination");
    return self::SUCCESS;
  }

}
