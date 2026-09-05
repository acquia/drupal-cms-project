<?php

declare(strict_types=1);

namespace Drupal\Tests\modeler_api\Unit;

use Drupal\Core\Config\ManagedStorage;
use Drupal\Core\Config\StorageManagerInterface;
use Drupal\Core\Extension\Dependency;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\modeler_api\Api;
use Drupal\modeler_api\ExportRecipe;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the composer metadata generated for an exported recipe.
 */
#[CoversClass(ExportRecipe::class)]
#[Group('modeler_api')]
class ExportRecipeComposerTest extends UnitTestCase {

  /**
   * Tests that a model without dependencies produces no require section.
   */
  public function testNoDependenciesProduceNoRequire(): void {
    $composer = $this->exportRecipe([])
      ->testGetComposer('my_recipe', 'drupal', 'A description');

    $this->assertSame('drupal/my_recipe', $composer['name']);
    $this->assertSame('drupal-recipe', $composer['type']);
    $this->assertSame('A description', $composer['description']);
    $this->assertSame('GPL-2.0-or-later', $composer['license']);
    $this->assertArrayNotHasKey('require', $composer);
  }

  /**
   * Tests that core-only dependencies produce no require section.
   *
   * Core modules ship with Drupal itself, so there is nothing for composer to
   * resolve and the recipe must not pin a core version on the site's behalf.
   */
  public function testCoreOnlyDependenciesProduceNoRequire(): void {
    $composer = $this->exportRecipe([
      'node' => 'core/modules/node',
      'user' => 'core/modules/user',
    ])->testGetComposer('my_recipe', 'drupal', 'A description', ['node', 'user']);

    $this->assertArrayNotHasKey('require', $composer);
  }

  /**
   * Tests that require lists contrib dependencies and no core constraint.
   */
  public function testRequireHoldsOnlyContribDependencies(): void {
    $composer = $this->exportRecipe([
      'eca' => 'modules/contrib/eca',
      'modeler_api' => 'modules/contrib/modeler_api',
      'node' => 'core/modules/node',
    ])->testGetComposer('my_recipe', 'acme', 'A description', [
      'eca',
      'modeler_api',
      'node',
    ]);

    $this->assertSame([
      'drupal/eca' => '*',
      'drupal/modeler_api' => '*',
    ], $composer['require']);
    $this->assertArrayNotHasKey('drupal/core', $composer['require']);
  }

  /**
   * Tests that a submodule dependency is reduced to its parent project.
   */
  public function testSubmoduleDependencyResolvesToParentProject(): void {
    $composer = $this->exportRecipe(
      [
        'eca' => 'modules/contrib/eca',
        'eca_content' => 'modules/contrib/eca/modules/eca_content',
      ],
      ['eca_content' => ['eca' => Dependency::createFromString('eca')]],
    )->testGetComposer('my_recipe', 'drupal', 'A description', ['eca_content']);

    $this->assertSame(['drupal/eca' => '*'], $composer['require']);
  }

  /**
   * Builds the export service with a module extension list test double.
   *
   * @param array<string, string> $paths
   *   Module paths, keyed by module name.
   * @param array<string, array<string, \Drupal\Core\Extension\Dependency>> $requires
   *   Module dependencies, keyed by module name.
   *
   * @return \Drupal\Tests\modeler_api\Unit\ComposerTestExportRecipe
   *   The export service exposing its composer metadata builder.
   */
  private function exportRecipe(array $paths, array $requires = []): ComposerTestExportRecipe {
    $moduleExtensionList = $this->createMock(ModuleExtensionList::class);
    $moduleExtensionList->method('getPath')
      ->willReturnCallback(static fn (string $module): string => $paths[$module] ?? '');
    $list = [];
    foreach ($requires as $module => $dependencies) {
      // Extension populates "requires" as a dynamic property, so a stand-in
      // declaring it explicitly models what getComposer() actually consumes.
      $list[$module] = new class($dependencies) {

        /**
         * Constructs the extension stand-in.
         *
         * @param array<string, \Drupal\Core\Extension\Dependency> $requires
         *   The module dependencies, keyed by module name.
         */
        public function __construct(
          public readonly array $requires,
        ) {}

      };
    }
    $moduleExtensionList->method('getList')->willReturn($list);

    return new ComposerTestExportRecipe(
      new ManagedStorage($this->createMock(StorageManagerInterface::class)),
      $this->createMock(FileSystemInterface::class),
      $moduleExtensionList,
      $this->createMock(MessengerInterface::class),
      $this->createMock(Api::class),
    );
  }

}

/**
 * Test double exposing the generated composer metadata.
 */
class ComposerTestExportRecipe extends ExportRecipe {

  /**
   * Exposes the composer metadata builder.
   *
   * @param string $id
   *   The recipe ID.
   * @param string $namespace
   *   The namespace.
   * @param string $description
   *   The recipe description.
   * @param array $modules
   *   The list of required module names.
   *
   * @return array<string, array<string,string>|string>
   *   The content of the composer.json file as an array.
   */
  public function testGetComposer(string $id, string $namespace, string $description, array $modules = []): array {
    return $this->getComposer($id, $namespace, $description, $modules);
  }

}
