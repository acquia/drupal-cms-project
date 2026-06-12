<?php

declare(strict_types=1);

namespace Drupal\Tests\drupal_cms_helper\Traits;

use Drupal\Core\Config\FileStorage;
use Drupal\Core\Config\StorageComparer;
use Drupal\FunctionalTests\Core\Recipe\RecipeTestTrait as CoreRecipeTestTrait;
use Drupal\Tests\BrowserTestBase;
use Drush\TestTraits\DrushTestTrait;
use PHPUnit\Framework\Assert;

/**
 * Provides helper methods for testing Drupal CMS recipes.
 *
 * @internal
 *   This is an internal part of Drupal CMS and may be changed or removed at
 *   any time without warning. External code should not use this trait.
 */
trait RecipeTestTrait {

  use CoreRecipeTestTrait;
  use DrushTestTrait;

  /**
   * Asserts that the recipe's shipped config is consistent on export.
   *
   * @param string $dir
   *   The recipe directory.
   * @param list<string> $will_not_match
   *   (optional) Names of config which is expected to be different on export.
   */
  protected function assertConfigIsConsistent(string $dir, array $will_not_match = []): void {
    $dir .= '/config';
    Assert::assertDirectoryExists($dir);
    $shipped = new FileStorage($dir);

    assert($this instanceof BrowserTestBase);
    $destination = $this->publicFilesDirectory . '/export';
    $this->drush('config:export', options: [
      'destination' => $destination,
      'generic' => TRUE,
      'yes' => TRUE,
    ]);
    $exported = new FileStorage($destination);

    $changed = (new StorageComparer($shipped, $exported))
      ->createChangelist()
      ->getChangelist('update');

    sort($will_not_match);
    sort($changed);
    Assert::assertSame($will_not_match, $changed);
  }

}
