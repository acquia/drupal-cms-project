<?php

declare(strict_types=1);

namespace Drupal\drupal_cms_helper\Drush\Commands;

use Composer\InstalledVersions;
use Consolidation\SiteAlias\SiteAliasManagerAwareTrait;
use Consolidation\SiteProcess\ProcessManagerAwareInterface;
use Consolidation\SiteProcess\ProcessManagerAwareTrait;
use Drush\SiteAlias\ProcessManager;
use Drush\SiteAlias\SiteAliasManagerAwareInterface;
use Drush\Style\DrushStyle;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Finds and removes unneeded dependencies.
 *
 * @api
 * The `pm:clean` command is part of Drupal CMS's developer-facing API and may
 * be relied upon.
 *
 * @internal
 *   This is an internal part of Drupal CMS and may be changed or removed at any
 *   time without warning. External code should not interact with this class.
 */
#[AsCommand(
  name: 'pm:clean',
  description: 'Removes Composer packages for disabled extensions.',
  aliases: ['cl', 'clean', 'cleanup'],
)]
final class CleanCommand extends Command implements ProcessManagerAwareInterface, SiteAliasManagerAwareInterface {

  use ProcessManagerAwareTrait;
  use SiteAliasManagerAwareTrait;

  /**
   * {@inheritdoc}
   */
  #[\Override]
  protected function configure(): void {
    $this->addOption(
      'update',
      NULL,
      InputOption::VALUE_NONE,
      'Physically remove packages after updating `composer.json`.',
    );
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  protected function execute(InputInterface $input, OutputInterface $output): int {
    $process_manager = $this->processManager();
    assert($process_manager instanceof ProcessManager);
    $this_site = $this->siteAliasManager()->getSelf();

    $process = $process_manager->drush(
      $this_site,
      'pm:list',
      options: [
        'format' => 'json',
        'no-core' => TRUE,
        'fields' => 'display_name,project,path,status',
      ]
    )->mustRun();

    $extensions = json_decode($process->getOutput(), TRUE, flags: JSON_THROW_ON_ERROR);

    // Ensure we know what project every extension belongs to.
    foreach ($extensions as $name => &$info) {
      if ($info['project']) {
        continue;
      }
      $info['project'] = array_find_key(
        $extensions,
        // That trailing slash is important! It ensures that a path like
        // `modules/eca/extra/eca_special` will be clearly identified as being
        // part of `modules/eca`, rather than `modules/eca_grab_bag`.
        fn (array $e): bool => str_starts_with($info['path'], $e['path'] . '/'),
      );
      // If that didn't work, fall back to guessing that the extension's name is
      // also the project name.
      $info['project'] ??= $name;
    }

    // Projects that have at least one enabled extension cannot be removed.
    $enabled = [];
    foreach ($extensions as ['status' => $status, 'project' => $project]) {
      if ($status === 'Enabled') {
        $enabled[$project] = TRUE;
      }
    }

    // Find main modules that are disabled AND whose project has no enabled
    // extensions (including sub-modules).
    $disabled = array_filter(
      $extensions,
      function (array $info, string $name) use ($enabled): bool {
        return $info['status'] === 'Disabled' && $name === $info['project'] && empty($enabled[$name]);
      },
      ARRAY_FILTER_USE_BOTH,
    );

    if (empty($disabled)) {
      $output->writeln('No disabled extensions to clean up.');
      return self::SUCCESS;
    }

    (new DrushStyle($input, $output))->writeln([
      'The following extensions will be removed:',
      ...array_column($disabled, 'display_name'),
    ]);

    // Try to use the Composer that is locally installed in the project. If it
    // isn't, then just hope that Composer is globally installed.
    try {
      $composer = InstalledVersions::getInstallPath('composer/composer') . '/bin/composer';
    }
    catch (\OutOfBoundsException) {
      $composer = 'composer';
    }

    // Map each disabled extension to its Composer package name.
    $packages = [];
    foreach ($disabled as $info) {
      $process = $process_manager->process([
        PHP_BINARY,
        $composer,
        'config',
        'name',
        '--working-dir=' . $this_site->root() . '/' . $info['path'],
      ]);
      if ($process->run() === 0) {
        $packages[] = trim($process->getOutput());
      }
    }

    if (empty($packages)) {
      $output->writeln('No Composer packages found for disabled extensions.');
      return self::SUCCESS;
    }

    $update = $input->getOption('update');
    ['install_path' => $project_root] = InstalledVersions::getRootPackage();

    $command = [
      PHP_BINARY,
      $composer,
      'remove',
      ...$packages,
      '--working-dir=' . $project_root,
      '--minimal-changes',
    ];
    if (empty($update)) {
      $command[] = '--no-update';
    }
    $output->writeln('Running: ' . implode(' ', $command));

    if ($update) {
      $process_manager->process($command)->mustRun();
    }
    return self::SUCCESS;
  }

}
