<?php

declare(strict_types=1);

namespace Drupal\Tests\project_browser\Kernel;

use Drupal\project_browser\Plugin\ProjectBrowserSourceInterface;
use Drupal\Component\FileSystem\FileSystem;
use Drupal\Component\Serialization\Json;
use Drupal\KernelTests\KernelTestBase;
use Drupal\project_browser\Plugin\ProjectBrowserSource\Recipes;
use Drupal\project_browser\Plugin\ProjectBrowserSourceManager;
use Drupal\project_browser\ProjectType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Filesystem\Filesystem as SymfonyFilesystem;
use Symfony\Component\Finder\Finder;

/**
 * Tests the source plugin that exposes locally installed recipes.
 *
 * @group project_browser
 */
#[CoversClass(Recipes::class)]
#[Group('project_browser')]
final class RecipesSourceTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'project_browser',
    'project_browser_test',
    'user',
  ];

  /**
   * A reference to the file system.
   *
   * @var \Symfony\Component\Filesystem\Filesystem
   */
  protected $fileSystem;
  /**
   * The directory created to house installed recipes.
   *
   * @var string
   */
  protected $installedRecipesDir;
  /**
   * The directory where the symlinked module is.
   *
   * @var string
   */
  protected $generatedRecipeDir;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installSchema('project_browser_test', [
      'project_browser_projects',
      'project_browser_categories',
    ]);
    $this->installConfig('project_browser_test');
    $this->installConfig('project_browser');
  }

  /**
   * Tests that recipes are discovered by the plugin.
   */
  public function testRecipesAreDiscovered(): void {
    // Generate a fake recipe in the temporary directory.
    $generated_recipe_name = uniqid('recipe-');
    $recipes = [
      $generated_recipe_name => Json::encode([
        'name' => 'drupal/bogus_recipe',
      ]
      ),
    ];
    $source = $this->prepareRecipesDirectory($recipes);

    $expected_recipe_names = [
      $generated_recipe_name,
      // Our test recipes should be discovered too.
      'test_recipe',
      'recipe_with_tasks',
    ];
    $finder = Finder::create()
      ->in($this->getDrupalRoot() . '/core/recipes')
      ->directories()
      ->notName('example')
      ->depth(0);
    foreach ($finder as $core_recipe) {
      $expected_recipe_names[] = $core_recipe->getBasename();
    }

    $projects = $source->getProjects();
    $found_recipes = [];
    foreach ($projects->list as $project) {
      $this->assertNotEmpty($project->title);
      $this->assertSame(ProjectType::Recipe, $project->type);
      $found_recipes[$project->machineName] = $project;
    }
    $found_recipe_names = array_keys($found_recipes);

    // The `example` recipe (from core) should always be hidden.
    $this->assertNotContains('example', $expected_recipe_names);

    sort($expected_recipe_names);
    sort($found_recipe_names);
    $this->assertSame($expected_recipe_names, $found_recipe_names);

    // Ensure the package names are properly resolved.
    $this->assertArrayHasKey('standard', $found_recipes);
    $this->assertSame('drupal/core', $found_recipes['standard']->packageName);
    $this->assertArrayHasKey('test_recipe', $found_recipes);
    $this->assertSame('project-browser-test/test-recipe', $found_recipes['test_recipe']->packageName);

    // The core recipes should have descriptions, which should become the body
    // text of the project.
    // The need for reflection sucks, but there's no way to introspect the body
    // on the backend.
    $body = (new \ReflectionProperty($found_recipes['standard'], 'body'))
      ->getValue($found_recipes['standard']);
    $this->assertNotEmpty($body);

    // Clean up.
    $this->tearDownRecipesDirectory();
  }

  /**
   * Tests homepage URL handling for recipes with and without homepage field.
   */
  public function testRecipeHomepageUrlHandling(): void {
    // Generate fake recipes - one with homepage, one without.
    $recipes_with_homepage = [
      'recipe_with_homepage' => Json::encode([
        "name" => "drupal/recipe_with_homepage",
        "homepage" => "https://example.com/recipe-with-homepage",
      ]),
      'recipe_without_homepage' => Json::encode([
        "name" => "drupal/recipe_without_homepage",
      ]),
      'another_recipe_with_homepage' => Json::encode([
        "name" => "drupal/another_recipe_with_homepage",
        "homepage" => "https://example.com/another-recipe",
      ]),
    ];

    /** @var \Drupal\project_browser\Plugin\ProjectBrowserSourceInterface $source */
    $source = $this->prepareRecipesDirectory($recipes_with_homepage);

    // Fetch discovered recipes.
    $projects = $source->getProjects();
    $found_recipes = [];
    foreach ($projects->list as $project) {
      $found_recipes[$project->machineName] = $project;
    }

    // Verify recipe with homepage has the correct URL.
    $this->assertArrayHasKey('recipe_with_homepage', $found_recipes);
    $this->assertSame('https://example.com/recipe-with-homepage', $found_recipes['recipe_with_homepage']->url?->toString());

    // Verify recipe without homepage has NULL URL
    // (doesn't inherit from previous recipe).
    $this->assertArrayHasKey('recipe_without_homepage', $found_recipes);
    $this->assertNull($found_recipes['recipe_without_homepage']->url);

    // Verify another recipe with homepage has its own correct URL.
    $this->assertArrayHasKey('another_recipe_with_homepage', $found_recipes);
    $this->assertSame('https://example.com/another-recipe', $found_recipes['another_recipe_with_homepage']->url?->toString());

    // Clean up.
    $this->tearDownRecipesDirectory();
  }

  /**
   * Tests sorting of discovered recipes by case-insensitive name.
   */
  public function testRecipeSortingByRecipeName(): void {
    // Generate fake recipes with varying case names.
    $generated_recipes = [
      'deltaRecipe' => Json::encode(["name" => "drupal/delta_recipe"]),
      'betaRecipe' => Json::encode(["name" => "drupal/beta_recipe"]),
      'AlphaRecipe' => Json::encode(["name" => "drupal/alpha_recipe"]),
      'GammaRecipe' => Json::encode(["name" => "drupal/gamma_recipe"]),
    ];

    $source = $this->prepareRecipesDirectory($generated_recipes);

    // Fetch discovered recipes.
    $projects = $source->getProjects();
    $found_recipes = array_map('strval', array_column($projects->list, 'title'));

    $generated_recipe_titles = array_keys($generated_recipes);
    // Filter the discovered recipe titles to include only those that
    // were generated during the test.
    $found_generated_titles = array_values(array_intersect($found_recipes, $generated_recipe_titles));

    // Sort the expected titles using case-insensitive sorting.
    usort($generated_recipe_titles, 'strcasecmp');

    $this->assertSame($generated_recipe_titles, $found_generated_titles);

    // Clean up.
    $this->tearDownRecipesDirectory();
  }

  /**
   * Gets the recipes source with test-friendly config.
   *
   * @param array $recipes
   *   A list of recipes to create.
   *
   * @return \Drupal\project_browser\Plugin\ProjectBrowserSourceInterface
   *   A Project Browser Source plugin.
   */
  private function prepareRecipesDirectory(Array $recipes): ProjectBrowserSourceInterface {
    $this->installedRecipesDir = uniqid(FileSystem::getOsTemporaryDirectory() . '/');
    $this->fileSystem = new SymfonyFilesystem();
    $this->fileSystem->mkdir($this->installedRecipesDir);

    foreach ($recipes as $recipe_name => $composer_json_content) {
      $recipe_dir = $this->installedRecipesDir . '/' . $recipe_name;
      $this->fileSystem->mkdir($recipe_dir);
      file_put_contents($recipe_dir . '/composer.json', $composer_json_content);
      file_put_contents($recipe_dir . '/recipe.yml', "name: $recipe_name");
    }

    // Symlink the fake recipe into the place where the source plugin will
    // search, to prove that the plugin follows symlinks.
    $generated_recipe_name = uniqid('generated-');
    $this->generatedRecipeDir = FileSystem::getOsTemporaryDirectory() . '/' . $generated_recipe_name;
    $this->fileSystem->symlink($this->generatedRecipeDir, $this->installedRecipesDir . '/' . $generated_recipe_name);

    $source = $this->container->get(ProjectBrowserSourceManager::class)->createInstance('recipes', [
      'additional_directories' => [
        __DIR__ . '/../../fixtures',
        $this->installedRecipesDir,
      ],
    ]);

    return $source;
  }

  /**
   * Tears down the recipes directories.
   */
  private function tearDownRecipesDirectory(): void {
    $this->fileSystem->remove([
      $this->installedRecipesDir,
      $this->generatedRecipeDir,
    ]);
  }

}
