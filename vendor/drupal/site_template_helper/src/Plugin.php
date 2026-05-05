<?php

declare(strict_types=1);

namespace Drupal\site_template_helper;

use Composer\Composer;
use Composer\DependencyResolver\Operation\InstallOperation;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Installer\PackageEvent;
use Composer\Installer\PackageEvents;
use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\ScriptEvents;
use Composer\Util\Filesystem;
use Composer\Util\Platform;
use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Command\GenerateTheme;
use Drupal\Core\Extension\ExtensionVersion;
use Drupal\Core\Recipe\Recipe;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Provides functionality to set up and scaffold Drupal site templates.
 */
final readonly class Plugin implements PluginInterface, EventSubscriberInterface {

  /**
   * The current Composer instance.
   */
  private Composer $composer;

  /**
   * The I/O handler.
   */
  private IOInterface $io;

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      PackageEvents::POST_PACKAGE_INSTALL => 'onPackageInstall',
      ScriptEvents::POST_UPDATE_CMD => 'onUpdate',
    ];
  }

  /**
   * Reacts when a package is installed.
   *
   * @param \Composer\Installer\PackageEvent $event
   *   The event being handled.
   */
  public function onPackageInstall(PackageEvent $event): void {
    $operation = $event->getOperation();
    assert($operation instanceof InstallOperation);
    $this->generateTheme($operation->getPackage());
  }

  /**
   * Reacts when dependencies are updated.
   *
   * @param \Composer\Installer\PackageEvent $event
   *   The event being handled.
   */
  public function onUpdate(): void {
    // If the root package is a site template, generate a theme for it if
    // needed. This is helpful on GitLab CI, for example.
    $this->generateTheme($this->composer->getPackage());
  }

  /**
   * Generates a starter theme on behalf of a package.
   *
   * This is only done if the package is a recipe with
   * `extra.drupal-site-template.generate-theme.name` defined.
   *
   * @param \Composer\Package\PackageInterface $package
   *   The package for which to generate a theme.
   */
  private function generateTheme(PackageInterface $package): void {
    $extra = $package->getExtra();
    $generate_theme_options = $extra['drupal-site-template']['generate-theme'] ?? NULL;

    // Site templates are always recipes, and we only need to care about ones
    // which want to generate a theme.
    if ($package->getType() !== Recipe::COMPOSER_PROJECT_TYPE || empty($generate_theme_options)) {
      return;
    }
    $theme_name = $generate_theme_options['name'];

    // Use the path of \Drupal to determine the Drupal root, because Drupal's
    // internal commands usually need to be run from there.
    $drupal_root = dirname((new \ReflectionClass('Drupal'))->getFileName(), 3);

    $info_file_path = implode(DIRECTORY_SEPARATOR, [
      $drupal_root,
      'themes',
      $theme_name,
      "$theme_name.info.yml",
    ]);
    // If the theme was already generated, leave it alone.
    if (file_exists($info_file_path)) {
      return;
    }

    $info = $generate_theme_options['info'] ?? [];
    $from = $generate_theme_options['from'] ?? 'starterkit_theme';
    if ($from) {
      // Run the `generate-theme` command in this process space. It's a lot
      // harder and more fiddly, for whatever reason, to run it in its own
      // process (e.g., via Composer's process executor).
      $original_cwd = Platform::getCwd();
      chdir($drupal_root);
      (new CommandTester(new GenerateTheme()))
        ->execute(['machine-name' => $theme_name, '--starterkit' => $from]);
      chdir($original_cwd);

      $info += Yaml::decode(file_get_contents($info_file_path));
    }
    else {
      (new Filesystem())
        ->ensureDirectoryExists(dirname($info_file_path));
    }
    $drupal_version = ExtensionVersion::createFromVersionString(\Drupal::VERSION)
      ->getMajorVersion();
    $info += [
      'type' => 'theme',
      'core_version_requirement' => "^$drupal_version",
      'base theme' => FALSE,
      'version' => '1.0.0',
    ];
    file_put_contents($info_file_path, Yaml::encode($info));

    $this->io->write(
      sprintf("Generated <comment>$theme_name</comment> theme for <comment>%s</comment>.", $package->getName()),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function activate(Composer $composer, IOInterface $io): void {
    $this->composer = $composer;
    $this->io = $io;
  }

  /**
   * {@inheritdoc}
   */
  public function deactivate(Composer $composer, IOInterface $io): void {
    // Nothing to do here.
  }

  /**
   * {@inheritdoc}
   */
  public function uninstall(Composer $composer, IOInterface $io): void {
    // Nothing to do here.
  }

}
