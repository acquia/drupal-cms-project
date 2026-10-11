<?php

declare(strict_types=1);

namespace Drupal\drupal_cms_installer;

use Composer\InstalledVersions;
use Composer\Util\Platform;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Runs Composer.
 *
 * @internal
 *   Everything in the Drupal CMS installer is internal and may be changed or
 *   removed at any time without warning. External code should not interact
 *   with this class.
 */
final class ComposerExecutor {

  /**
   * Returns the absolute, real path of the project.
   *
   * @return string
   *   The absolute path of the project.
   */
  public static function getProjectRoot(): string {
    $root_package = InstalledVersions::getRootPackage();
    $project_root = realpath($root_package['install_path']);
    assert(is_string($project_root));
    return $project_root;
  }

  /**
   * Runs a callback function with COMPOSER_HOME set, if needed.
   *
   * @param callable $callback
   *   A callback function to run with COMPOSER_HOME set.
   *
   * @return mixed
   *   Whatever the callback returns.
   */
  public static function execute(callable $callback): mixed {
    // This is needed for Composer to work properly. Nothing should actually be
    // written here, since we're doing a strictly read-only operation.
    $home = Platform::getEnv('COMPOSER_HOME');
    if (empty($home)) {
      Platform::putEnv('COMPOSER_HOME', self::getProjectRoot() . DIRECTORY_SEPARATOR . '.composer');
      // Prevent Composer from creating COMPOSER_HOME at all.
      Platform::putEnv('COMPOSER_HTACCESS_PROTECT', '0');
    }
    try {
      $value = $callback();
    }
    finally {
      // If we had to set COMPOSER_HOME, undo that.
      if (empty($home)) {
        Platform::clearEnv('COMPOSER_HOME');
        Platform::clearEnv('COMPOSER_HTACCESS_PROTECT');
      }
    }
    return $value;
  }

  /**
   * Executes a Composer command.
   *
   * @param string ...$arguments
   *   Arguments to pass to Composer. The path of the Composer binary, and the
   *   `--no-interaction` option, are automatically prepended.
   */
  public static function run(string ...$arguments): void {
    self::execute(function () use ($arguments): void {
      array_unshift(
        $arguments,
        // Always run Composer directly through the PHP interpreter.
        (new PhpExecutableFinder())->find(),
        // Use the version of Composer bundled with Drupal CMS.
        InstalledVersions::getInstallPath('composer/composer') . '/bin/composer',
        // There's no way to get user input, so don't ask for any.
        '--no-interaction',
      );
      // Composer can take a while, so give it a nice, generous timeout.
      (new Process($arguments, self::getProjectRoot(), timeout: 300))
        ->mustRun();
    });
  }

}
