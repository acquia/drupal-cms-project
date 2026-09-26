<?php

declare(strict_types=1);

namespace Drupal\config_language_lock_test_recipe_ext_config\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Recipe\Recipe;
use Drupal\Core\Recipe\RecipeRunner;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Provides test-only endpoints for extension-config recipe execution.
 */
class RecipeExtConfigTestController extends ControllerBase {

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
   * Applies the no-validation-error extension config test recipe.
   */
  public function applyValidLangcodeExtConfigRecipe(): Response {
    $module_path = $this->moduleExtensionList->getPath('config_language_lock_test_recipe_ext_config');
    $recipe_path = DRUPAL_ROOT . '/' . $module_path . '/recipes/lock_langcode_valid_ext_config';

    $recipe = Recipe::createFromDirectory($recipe_path);
    RecipeRunner::processRecipe($recipe);

    return new Response('ok');
  }

  /**
   * Applies the invalid-langcode extension config test recipe.
   */
  public function applyExtInvalidLangcodeRecipe(): Response {
    $module_path = $this->moduleExtensionList->getPath('config_language_lock_test_recipe_ext_config');
    $recipe_path = DRUPAL_ROOT . '/' . $module_path . '/recipes/lock_langcode_ext_invalid';

    $recipe = Recipe::createFromDirectory($recipe_path);
    RecipeRunner::processRecipe($recipe);

    return new Response('ok');
  }

}
