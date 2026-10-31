<?php

declare(strict_types=1);

namespace Drupal\Tests\modeler_api\Kernel;

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\modeler_api\ExportRecipe;
use Drupal\modeler_api_test\Entity\TestModel;
use Drupal\node\Entity\NodeType;
use Drupal\user\Entity\Role;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that a role in a model's closure does not leak the site's permissions.
 *
 * A role declares config and module dependencies on whatever its permissions
 * target, and those dependencies come from the exporting site rather than
 * from the model. The unit tests stub the dependency walk, so this drives a
 * real role with a bundle permission through real config storage to confirm
 * that neither the permissions nor what they depend on reach the recipe.
 */
#[Group('modeler_api')]
#[RunTestsInSeparateProcesses]
class RecipeRoleExportTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'text',
    'filter',
    'node',
    'modeler_api',
    'modeler_api_test',
  ];

  /**
   * The directory the recipe is exported to.
   */
  private string $destination;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->destination = 'public://recipe-' . $this->randomMachineName();

    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();
    $role = Role::create(['id' => 'editor', 'label' => 'Editor']);
    $role->grantPermission('create article content')->save();
    // The test rests on the role carrying dependencies of its own; a role
    // without any would pass for the wrong reason.
    $dependencies = Role::load('editor')->getDependencies();
    $this->assertContains('node.type.article', $dependencies['config']);
    $this->assertContains('node', $dependencies['module']);
  }

  /**
   * Tests that the role is ensured while its permissions and targets stay out.
   */
  public function testRoleDependenciesDoNotReachTheRecipe(): void {
    $model = TestModel::create(['id' => 'roles', 'label' => 'Roles']);
    $model->set('dependencies', ['enforced' => ['config' => ['user.role.editor']]]);
    $model->save();
    $model = $this->container->get('entity_type.manager')
      ->getStorage('modeler_api_test_model')
      ->loadUnchanged('roles');
    $this->assertInstanceOf(ConfigEntityInterface::class, $model);
    $this->assertContains('user.role.editor', $model->getDependencies()['config']);

    /** @var \Drupal\modeler_api\Plugin\ModelerApiModelOwner\ModelOwnerInterface $owner */
    $owner = $this->container
      ->get('plugin.manager.modeler_api.model_owner')
      ->createInstance('modeler_api_test');
    $this->container->get('modeler_api.export.recipe')->doExport(
      $owner,
      $model,
      'Roles test recipe',
      ExportRecipe::DEFAULT_NAMESPACE,
      $this->destination,
    );

    $recipe = Yaml::decode((string) file_get_contents($this->destination . '/recipe.yml'));
    $this->assertSame([
      'ensure_exists' => ['label' => 'Editor'],
    ], $recipe['config']['actions']['user.role.editor']);
    $this->assertNotContains('node', $recipe['install'] ?? []);

    $configFiles = array_map('basename', glob($this->destination . '/config/*.yml') ?: []);
    $this->assertNotContains('node.type.article.yml', $configFiles);
    $this->assertNotContains('user.role.editor.yml', $configFiles);
  }

}
