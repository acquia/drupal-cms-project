<?php

declare(strict_types=1);

namespace Drupal\recipe_locale\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\recipe_locale\RecipeTracker;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Lists the recipes that have translations, next to the status report.
 */
final class RecipeListController extends ControllerBase {

  public function __construct(
    private readonly RecipeTracker $tracker,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static($container->get(RecipeTracker::class));
  }

  /**
   * Builds the list of recorded recipes.
   *
   * @return array<string, mixed>
   *   A render array.
   */
  public function overview(): array {
    $build['help'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->t('Recipes applied to this site are listed here, so their translations can be downloaded and updated like the translations of modules and themes. Removing a recipe from this list deletes the translation files and status stored for it, and the stored copy of the configuration it shipped. The recipe itself stays applied.'),
    ];

    $rows = [];
    foreach ($this->tracker->getAll() as $name => $recipe) {
      $version = $recipe['version'];
      $rows[$name] = [
        'label' => $recipe['label'],
        'name' => $name,
        'package' => $recipe['package'],
        // Without a version there is no download URL, so nothing is fetched.
        'version' => $version === '' ? $this->t('unknown') : $version,
        'operations' => [
          'data' => [
            '#type' => 'operations',
            '#links' => [
              'remove' => [
                'title' => $this->t('Remove'),
                'url' => Url::fromRoute('recipe_locale.remove', ['recipe' => $name]),
              ],
            ],
          ],
        ],
      ];
    }

    $build['table'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Recipe'),
        $this->t('Project'),
        $this->t('Package'),
        $this->t('Version'),
        $this->t('Operations'),
      ],
      '#rows' => $rows,
      '#empty' => $this->t('No recipes have been recorded yet. Recipes applied from now on are added here.'),
    ];
    // The version can come from Composer, which changes outside of config.
    $build['#cache']['max-age'] = 0;
    return $build;
  }

}
