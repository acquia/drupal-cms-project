<?php

namespace Drupal\drupal_cms_helper;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Extension\Extension;

/**
 * Figures out which project and Composer package an extension belongs to.
 *
 * @internal
 *   This is an internal part of Drupal CMS and may be changed or removed at any
 *   time without warning. External code should not interact with this class.
 */
final class ProjectResolver {

  public function __construct(
    private readonly string $appRoot,
  ) {}

  /**
   * Returns the drupal.org project an extension belongs to, if any.
   *
   * @param \Drupal\Core\Extension\Extension $extension
   *   The extension to check.
   * @param bool $reset
   *   (optional) TRUE to clear the static cache. Defaults to FALSE.
   *
   * @return string|null
   *   The project name, or NULL if it could not be determined.
   */
  public function getProject(Extension $extension, bool $reset = FALSE): ?string {
    static $cache = [];
    if ($reset) {
      $cache = [];
    }
    $name = $extension->getName();
    if (array_key_exists($name, $cache)) {
      return $cache[$name];
    }
    if (isset($extension->info['project'])) {
      return $cache[$name] = $extension->info['project'];
    }
    $package = $this->getPackageName($extension);
    if ($package && str_starts_with($package, 'drupal/')) {
      return $cache[$name] = substr($package, 7);
    }
    return $cache[$name] = NULL;
  }

  /**
   * Reads the package name from a directory's `composer.json` file.
   *
   * @param string $directory
   *   The directory to check.
   *
   * @return string|null
   *   The Composer package name, or NULL if the directory has no
   *   `composer.json` file, or the file doesn't name a package.
   */
  public static function readPackageName(string $directory): ?string {
    $file = $directory . DIRECTORY_SEPARATOR . 'composer.json';
    if (file_exists($file)) {
      $data = Json::decode((string) file_get_contents($file));
      if (isset($data['name'])) {
        return $data['name'];
      }
    }
    return NULL;
  }

  /**
   * Returns the Composer package that contains an extension, if any.
   *
   * Walks up from the extension's directory until it finds a `composer.json`
   * file, and returns the package name from it.
   *
   * @param \Drupal\Core\Extension\Extension $extension
   *   The extension to check.
   * @param bool $reset
   *   (optional) TRUE to clear the static cache. Defaults to FALSE.
   *
   * @return string|null
   *   The Composer package name, or NULL if no package was found.
   */
  public function getPackageName(Extension $extension, bool $reset = FALSE): ?string {
    static $cache = [];
    if ($reset) {
      $cache = [];
    }
    $name = $extension->getName();
    if (array_key_exists($extension->getName(), $cache)) {
      return $cache[$name];
    }
    $directory = $this->appRoot . DIRECTORY_SEPARATOR . $extension->getPath();

    while (str_starts_with($directory, $this->appRoot)) {
      $package_name = self::readPackageName($directory);
      if ($package_name) {
        return $cache[$name] = $package_name;
      }
      $directory = dirname($directory);
    }
    return $cache[$name] = NULL;
  }

}
