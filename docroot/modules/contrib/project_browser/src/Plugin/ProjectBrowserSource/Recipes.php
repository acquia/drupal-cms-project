<?php

declare(strict_types=1);

namespace Drupal\project_browser\Plugin\ProjectBrowserSource;

use Composer\InstalledVersions;
use Drupal\Component\Serialization\Json;
use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Recipe\Recipe;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\project_browser\Attribute\ProjectBrowserSource;
use Drupal\project_browser\Plugin\ProjectBrowserSourceBase;
use Drupal\project_browser\ProjectBrowser\Filter\TextFilter;
use Drupal\project_browser\ProjectBrowser\Project;
use Drupal\project_browser\ProjectBrowser\ProjectsResultsPage;
use Drupal\project_browser\ProjectType;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Finder\Finder;

/**
 * A source plugin that exposes recipes installed locally.
 *
 * @internal
 *   This is an internal part of Project Browser and may be changed or removed
 *   at any time. It should not be used by external code.
 */
#[ProjectBrowserSource(
  id: 'recipes',
  label: new TranslatableMarkup('Recipes'),
  description: new TranslatableMarkup('Recipes available in the local code base'),
  local_task: [
    'weight' => 2,
  ]
)]
final class Recipes extends ProjectBrowserSourceBase {

  use StringTranslationTrait;

  public function __construct(
    private readonly FileSystemInterface $fileSystem,
    private readonly CacheBackendInterface $cacheBin,
    private readonly ModuleExtensionList $moduleList,
    private readonly string $appRoot,
    private readonly FileUrlGeneratorInterface $fileUrlGenerator,
    mixed ...$arguments,
  ) {
    parent::__construct(...$arguments);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    assert(is_string($container->getParameter('app.root')));
    return new static(
      $container->get(FileSystemInterface::class),
      $container->get('cache.project_browser'),
      $container->get(ModuleExtensionList::class),
      $container->getParameter('app.root'),
      $container->get('file_url_generator'),
      ...array_slice(func_get_args(), 1),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFilterDefinitions(): array {
    return [
      'search' => new TextFilter('', $this->t('Search')),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getProjects(array $query = []): ProjectsResultsPage {
    $cached = $this->cacheBin->get($this->getPluginId());
    if ($cached) {
      $projects = $cached->data;
    }
    else {
      $projects = [];

      $logo_url = $this->moduleList->getPath('project_browser') . '/images/recipe-logo.svg';
      $logo_url = 'base:' . $this->fileUrlGenerator->generateString($logo_url);

      // Scan any additional directories specified in configuration.
      $finder = $this->getFinder()
        ->in($this->getConfiguration()['additional_directories']);

      /** @var \Symfony\Component\Finder\SplFileInfo $file */
      foreach ($finder as $file) {
        $path = $file->getPath();

        // If the recipe isn't part of Drupal core, get its package name from
        // `composer.json`. This shouldn't be necessary once drupal.org has a
        // proper API endpoint that provides project information for recipes.
        if (str_starts_with($path, $this->appRoot . '/core/recipes/')) {
          $package_name = 'drupal/core';
        }
        else {
          $package = file_get_contents($path . '/composer.json');
          assert(is_string($package));
          $package = Json::decode($package);
          $package_name = $package['name'];

          if (array_key_exists('homepage', $package)) {
            $url = Url::fromUri($package['homepage']);
          }
          else {
            $url = NULL;
          }
        }

        $recipe = Yaml::decode($file->getContents());
        $description = $recipe['description'] ?? NULL;

        $projects[] = new Project(
          logo: Url::fromUri($logo_url),
          isCompatible: TRUE,
          machineName: basename($path),
          // Allow the recipe body and title to be translated.
          // @phpcs:ignore Drupal.Semantics.FunctionT.NotLiteralString
          body: $description ? ['summary' => $this->t($description)] : [],
          // @phpcs:ignore Drupal.Semantics.FunctionT.NotLiteralString
          title: $this->t($recipe['name']),
          packageName: $package_name,
          url: $url ?? NULL,
          type: ProjectType::Recipe,
        );
      }
      // Sort the $projects array by the 'title' property in ascending order.
      usort($projects, function (Project $a, Project $b) {
        return strcasecmp((string) $a->title, (string) $b->title);
      });
      $this->cacheBin->set($this->getPluginId(), $projects);
    }

    // Filter by project machine name.
    if (!empty($query['machine_name'])) {
      $projects = array_filter($projects, fn(Project $project): bool => $project->machineName === $query['machine_name']);
    }

    // Filter by coverage.
    if (!empty($query['security_advisory_coverage'])) {
      $projects = array_filter($projects, fn(Project $project): bool => $project->isCovered ?? FALSE);
    }

    // Filter by search text.
    if (!empty($query['search'])) {
      $projects = array_filter($projects, fn (Project $project): bool => stripos((string) $project->title, $query['search']) !== FALSE);
    }

    $total = count($projects);

    // Filter by sorting criterion.
    if (!empty($query['sort'])) {
      $sort = $query['sort'];
      switch ($sort) {
        case 'a_z':
          usort($projects, fn($x, $y) => $x->title <=> $y->title);
          break;

        case 'z_a':
          usort($projects, fn($x, $y) => $y->title <=> $x->title);
          break;
      }
    }

    if (array_key_exists('page', $query) && !empty($query['limit'])) {
      $projects = array_chunk($projects, $query['limit'])[$query['page']] ?? [];
    }

    ['order' => $order] = $this->getConfiguration();
    SortHelper::sortInDefinedOrder($projects, $order);
    return $this->createResultsPage($projects, $total);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'order' => [],
      'additional_directories' => [],
    ] + parent::defaultConfiguration();
  }

  /**
   * Prepares a Symfony Finder to search for recipes in the file system.
   *
   * @return \Symfony\Component\Finder\Finder
   *   A Symfony Finder object, configured to find locally installed recipes.
   */
  private function getFinder(): Finder {
    $search_in = [$this->appRoot . '/core/recipes'];

    // Search wherever Composer is configured to install recipes. The recipe
    // system requires that all non-core recipes be located next to each other,
    // in the same directory.
    $recipes_dir = static::getRecipesPath();
    if ($recipes_dir) {
      // Handle the most common case, where the recipe name is the last part
      // of the path.
      if (basename($recipes_dir) === '{$name}') {
        $recipes_dir = dirname($recipes_dir);
      }
      $recipes_dir = $this->fileSystem->realpath($recipes_dir);
      assert(is_string($recipes_dir), 'Could not determine where Composer is configured to install recipes.');
      $search_in[] = $recipes_dir;
    }

    return Finder::create()
      ->files()
      ->in($search_in)
      ->depth(1)
      // Without this, recipes that are symlinked into the project (e.g.,
      // path repositories) will be missed.
      ->followLinks()
      // The example recipe exists for documentation purposes only.
      ->notPath('example/')
      ->name('recipe.yml');
  }

  /**
   * Determines where Composer is configured to install recipes.
   *
   * @return string|null
   *   The absolute path where Composer is configured to install recipes, or
   *   NULL if it cannot be determined. The path may contain relative path
   *   references and symlinks will not have been resolved.
   */
  public static function getRecipesPath(): ?string {
    ['install_path' => $project_root] = InstalledVersions::getRootPackage();
    $file = $project_root . DIRECTORY_SEPARATOR . 'composer.json';

    if (file_exists($file)) {
      $data = file_get_contents($file);
      assert(is_string($data));
      $data = Json::decode($data);

      foreach ($data['extra']['installer-paths'] ?? [] as $path => $criteria) {
        if (in_array('type:' . Recipe::COMPOSER_PROJECT_TYPE, $criteria, TRUE)) {
          return $project_root . DIRECTORY_SEPARATOR . $path;
        }
      }
    }
    return NULL;
  }

}
