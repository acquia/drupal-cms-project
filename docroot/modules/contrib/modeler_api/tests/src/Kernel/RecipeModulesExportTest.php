<?php

declare(strict_types=1);

namespace Drupal\Tests\modeler_api\Kernel;

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\modeler_api\ExportRecipe;
use Drupal\modeler_api\Plugin\ModelerApiModelOwner\ModelOwnerInterface;
use Drupal\modeler_api_test\Entity\TestModel;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that a declared module survives a save and reaches both output files.
 *
 * A module the model declares has to travel the whole way: through config
 * storage, then into the composer requirements and the recipe's install list.
 * The unit tests cover the exporter against a mocked model owner, so this
 * exercises the real accessors, a real save and a real export.
 */
#[Group('modeler_api')]
#[RunTestsInSeparateProcesses]
class RecipeModulesExportTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'modeler_api',
    'modeler_api_test',
  ];

  /**
   * The model owner under test.
   */
  private ModelOwnerInterface $owner;

  /**
   * The export service.
   */
  private ExportRecipe $exportRecipe;

  /**
   * The directory the recipe is exported to.
   */
  private string $destination;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    /** @var \Drupal\modeler_api\Plugin\ModelerApiModelOwner\ModelOwnerInterface $owner */
    $owner = $this->container
      ->get('plugin.manager.modeler_api.model_owner')
      ->createInstance('modeler_api_test');
    $this->owner = $owner;
    /** @var \Drupal\modeler_api\ExportRecipe $exportRecipe */
    $exportRecipe = $this->container->get('modeler_api.export.recipe');
    $this->exportRecipe = $exportRecipe;
    $this->destination = 'public://recipe-' . $this->randomMachineName();
  }

  /**
   * Tests that a declared module joins the computed ones in both files.
   *
   * A model carrying modeler_api third-party settings gains modeler_api as a
   * computed dependency, which gives this a real computed entry to merge with
   * rather than a contrived one.
   */
  public function testDeclaredModuleIsMergedWithComputedDependencies(): void {
    $exported = $this->exportModel(['node']);

    // The computed dependency is still there, so declaring did not substitute.
    $this->assertContains('modeler_api', $exported['install']);
    $this->assertSame('*', $exported['require']['drupal/modeler_api']);

    // A core module ships with Drupal, so it is enabled but never required.
    $this->assertContains('node', $exported['install']);
    $this->assertArrayNotHasKey('drupal/node', $exported['require']);
  }

  /**
   * Tests that a declared module which is also computed appears once.
   */
  public function testDeclaredModuleAlreadyComputedIsNotDuplicated(): void {
    $exported = $this->exportModel(['modeler_api']);

    $this->assertCount(
      1,
      array_keys($exported['install'], 'modeler_api', TRUE),
      'The module appears exactly once in the install list.',
    );
    $this->assertSame('*', $exported['require']['drupal/modeler_api']);
  }

  /**
   * Tests that the declared modules survive the round trip through storage.
   */
  public function testDeclaredModulesSurviveSave(): void {
    $model = $this->saveModel(['eca_tv_demo', 'node']);

    $this->assertSame(['eca_tv_demo', 'node'], $this->owner->getModules($model));
  }

  /**
   * Tests that a module which does not exist is not written to the recipe.
   *
   * The name is kept on the model, because the model is the maintainer's
   * statement of intent, but it cannot reach a recipe that would then fail to
   * apply.
   */
  public function testModuleThatDoesNotExistIsNotExported(): void {
    $exported = $this->exportModel(['module_that_does_not_exist']);

    $this->assertNotContains('module_that_does_not_exist', $exported['install']);
    $this->assertArrayNotHasKey('drupal/module_that_does_not_exist', $exported['require']);
  }

  /**
   * Saves a model declaring the given modules.
   *
   * @param array $modules
   *   The module names the model declares.
   *
   * @return \Drupal\Core\Config\Entity\ConfigEntityInterface
   *   The model, reloaded from storage.
   */
  private function saveModel(array $modules): ConfigEntityInterface {
    $storage = $this->container
      ->get('entity_type.manager')
      ->getStorage('modeler_api_test_model');
    $model = $storage->load('modules') ?? TestModel::create([
      'id' => 'modules',
      'label' => 'Modules',
    ]);
    $this->assertInstanceOf(ConfigEntityInterface::class, $model);
    $this->owner->setModules($model, $modules);
    $model->save();

    $reloaded = $storage->loadUnchanged('modules');
    $this->assertInstanceOf(ConfigEntityInterface::class, $reloaded);
    return $reloaded;
  }

  /**
   * Saves a model declaring the given modules and exports it to a recipe.
   *
   * @param array $modules
   *   The module names the model declares.
   *
   * @return array{install: array, require: array}
   *   The install list of the recipe and the require map of the package.
   */
  private function exportModel(array $modules): array {
    $model = $this->saveModel($modules);
    $this->exportRecipe->doExport(
      $this->owner,
      $model,
      'Modules test recipe',
      ExportRecipe::DEFAULT_NAMESPACE,
      $this->destination,
    );

    $recipe = Yaml::decode((string) file_get_contents($this->destination . '/recipe.yml'));
    $composer = json_decode((string) file_get_contents($this->destination . '/composer.json'), TRUE, 512, JSON_THROW_ON_ERROR);
    return [
      'install' => $recipe['install'] ?? [],
      'require' => $composer['require'] ?? [],
    ];
  }

}
