<?php

declare(strict_types=1);

namespace Drupal\config_language_lock_test_recipe_direct_config\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Recipe\Recipe;
use Drupal\Core\Recipe\RecipeRunner;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Provides test-only endpoints for direct-config recipe execution.
 */
class RecipeDirectConfigTestController extends ControllerBase {

  public function __construct(
    protected ModuleExtensionList $moduleExtensionList,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('extension.list.module'),
    );
  }

  /**
   * Applies the no-validation-error direct config test recipe.
   */
  public function applyValidLangcodeDirectConfigRecipe(): Response {
    $module_path = $this->moduleExtensionList->getPath('config_language_lock_test_recipe_direct_config');
    $recipe_path = DRUPAL_ROOT . '/' . $module_path . '/recipes/lock_langcode_valid_direct_config';

    $recipe = Recipe::createFromDirectory($recipe_path);
    RecipeRunner::processRecipe($recipe);

    return new Response('ok');
  }

  /**
   * Applies the empty-langcode validation error test recipe.
   */
  public function applyEmptyLangcodeRecipe(): Response {
    $module_path = $this->moduleExtensionList->getPath('config_language_lock_test_recipe_direct_config');
    $recipe_path = DRUPAL_ROOT . '/' . $module_path . '/recipes/lock_langcode_empty_langcode';

    $recipe = Recipe::createFromDirectory($recipe_path);
    RecipeRunner::processRecipe($recipe);

    return new Response('ok');
  }

  /**
   * Applies the unknown-langcode validation error test recipe.
   */
  public function applyUnknownLangcodeRecipe(): Response {
    $module_path = $this->moduleExtensionList->getPath('config_language_lock_test_recipe_direct_config');
    $recipe_path = DRUPAL_ROOT . '/' . $module_path . '/recipes/lock_langcode_unknown_langcode';

    $recipe = Recipe::createFromDirectory($recipe_path);
    RecipeRunner::processRecipe($recipe);

    return new Response('ok');
  }

}
