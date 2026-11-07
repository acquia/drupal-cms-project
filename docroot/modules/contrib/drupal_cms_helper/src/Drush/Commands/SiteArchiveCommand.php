<?php

declare(strict_types=1);

namespace Drupal\drupal_cms_helper\Drush\Commands;

use Composer\InstalledVersions;
use Consolidation\SiteAlias\SiteAliasManagerAwareTrait;
use Consolidation\SiteProcess\ProcessManagerAwareInterface;
use Consolidation\SiteProcess\ProcessManagerAwareTrait;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Config\FileStorage;
use Drupal\Core\Database\Connection;
use Drupal\Core\File\FileSystemInterface;
use Drush\Commands\AutowireTrait;
use Drush\SiteAlias\ProcessManager;
use Drush\SiteAlias\SiteAliasManagerAwareInterface;
use Drush\Style\DrushStyle;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Process;

/**
 * Drush command to create a site archive ZIP.
 *
 * @internal
 *   This is an internal part of Drupal CMS and may be changed or removed at any
 *   time without warning. External code should not interact with this class.
 */
#[AsCommand(
  name: 'site:archive',
  description: "Archives the site's configuration, content, and Composer files as a ZIP.",
  aliases: ['siar', 'sia'],
  hidden: TRUE,
)]
final class SiteArchiveCommand extends Command implements ProcessManagerAwareInterface, SiteAliasManagerAwareInterface {

  use AutowireTrait;
  use ProcessManagerAwareTrait;
  use SiteAliasManagerAwareTrait;

  public function __construct(
    private readonly FileSystemInterface $fileSystem,
    private readonly Connection $database,
  ) {
    parent::__construct();
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  protected function configure(): void {
    $this->addArgument(
      'archive',
      InputArgument::OPTIONAL,
      'The path where the zip archive should be created.',
      'drupal.zip',
    );
    $this->addOption(
      'db-type',
      NULL,
      InputOption::VALUE_REQUIRED,
      'The type of database to set in the core.extension configuration file.',
      $this->database->driver(),
    );
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  protected function execute(InputInterface $input, OutputInterface $output): int {
    $io = new DrushStyle($input, $output);

    $working_dir = $this->fileSystem->getTempDirectory() . '/site-archive-' . date('Y-m-d-His');
    $this->fileSystem->prepareDirectory($working_dir, FileSystemInterface::CREATE_DIRECTORY);

    // Export all configuration and content using other Drush commands.
    $process_manager = $this->processManager();
    assert($process_manager instanceof ProcessManager);
    $this_site = $this->siteAliasManager()->getSelf();

    // For testing, allow the project root to be set with an undocumented
    // environment variable. This is totally internal, so don't rely on it.
    $project_root = getenv('PROJECT_ROOT');
    if (empty($project_root)) {
      ['install_path' => $project_root] = InstalledVersions::getRootPackage();
    }
    $project_root = $this->fileSystem->realpath($project_root);
    assert($project_root && is_dir($project_root));

    // If there are any path repositories, they'll probably break the build.
    // Warn, but don't stop.
    $repositories = $this->composer(['config', 'repositories'])
      ->setWorkingDirectory($project_root)
      ->mustRun()
      ->getOutput();
    $repositories = Json::decode($repositories);
    assert(is_array($repositories));

    if (array_any($repositories, fn (array $r): bool => $r['type'] === 'path')) {
      $io->warning('Composer is using at least one path repository. This may break the archived site.');
    }

    // Ensure Composer is in a good state.
    $command = [
      'validate',
      '--strict',
      // This is a built site, so it probably won't be published to Packagist.
      '--no-check-publish',
      // It's very common for real sites to have "loose" dependency constraints.
      '--no-check-all',
      // The Drupal CMS project template includes a version. We don't care.
      '--no-check-version',
    ];
    $this->composer($command)->setWorkingDirectory($project_root)->mustRun();

    $config_dir = $working_dir . '/config';
    $process_manager->drush(
      $this_site,
      'config:export',
      options: ['destination' => $config_dir],
    )->mustRun();

    // Change the database type if needed.
    $old_db_type = $this->database->driver();
    $new_db_type = $input->getOption('db-type');
    if ($old_db_type !== $new_db_type) {
      $storage = new FileStorage($config_dir);
      $data = $storage->read('core.extension');
      assert(is_array($data));

      $data['module'][$new_db_type] = $data['module'][$old_db_type] ?? 0;
      unset($data['module'][$old_db_type]);
      $storage->write('core.extension', $data);
      $output->writeln("Changed database type from $old_db_type to $new_db_type.", OutputInterface::VERBOSITY_VERBOSE);
    }

    $process_manager->drush($this_site, 'content:export:all', [
      $working_dir . '/content',
    ])->mustRun();

    // Copy dependency info (including patch files).
    $finder = Finder::create()
      ->in($project_root)
      ->files()
      ->depth(0)
      ->name(['composer.*', 'patches.*'])
      ->append(
        (new Finder())->in($project_root)->followLinks()->name('*.patch'),
      );

    (new Filesystem())->mirror($project_root, $working_dir, $finder);

    $destination = $input->getArgument('archive');
    ini_set('phar.readonly', '0');
    $archive = new \PharData($destination);
    $archive->buildFromDirectory($working_dir);

    // Clean up the working directory.
    $this->fileSystem->deleteRecursive($working_dir);

    $io->success('Archive created: ' . $destination);
    return self::SUCCESS;
  }

  /**
   * Creates a process for running a Composer command.
   *
   * @param string[] $command
   *   The command array to pass to Composer
   *   (e.g. `['update', '--with-all-dependencies']`).
   *
   * @return \Symfony\Component\Process\Process
   *   A process object, ready to run.
   */
  private function composer(array $command): Process {
    $process_manager = $this->processManager();
    assert($process_manager instanceof ProcessManager);

    return $process_manager->process([
      PHP_BINARY,
      InstalledVersions::getInstallPath('composer/composer') . '/bin/composer',
      ...$command,
    ]);
  }

}
