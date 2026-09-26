<?php

declare(strict_types=1);

namespace Drupal\config_language_lock_test_config_action\Controller;

use Drupal\Core\Config\Action\ConfigActionManager;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Recipe\Recipe;
use Drupal\Core\Recipe\RecipeRunner;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Provides test-only endpoints for Config Action API execution.
 */
class ConfigActionTestController extends ControllerBase {

  public function __construct(
    protected ConfigActionManager $configActionManager,
    protected ModuleExtensionList $moduleExtensionList,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('plugin.manager.config_action'),
      $container->get('extension.list.module'),
    );
  }

  /**
   * Creates a lock_test_always_valid entity via Config Action API.
   */
  public function createAlwaysValidEntity(string $machine_name): Response {
    $this->configActionManager->applyAction(
      'createIfNotExists',
      'config_language_lock_test_types.always_valid.' . $machine_name,
      ['label' => 'Config action ' . $machine_name, 'id' => $machine_name],
    );

    return new Response('ok');
  }

  /**
   * Applies the no-validation-error config action test recipe.
   */
  public function applyValidLangcodeActionRecipe(): Response {
    $module_path = $this->moduleExtensionList->getPath('config_language_lock_test_config_action');
    $recipe_path = DRUPAL_ROOT . '/' . $module_path . '/recipes/lock_langcode_valid_action_import';

    $recipe = Recipe::createFromDirectory($recipe_path);
    RecipeRunner::processRecipe($recipe);

    return new Response('ok');
  }

  /**
   * Applies the empty-langcode config action validation error test recipe.
   */
  public function applyEmptyLangcodeActionRecipe(): Response {
    $module_path = $this->moduleExtensionList->getPath('config_language_lock_test_config_action');
    $recipe_path = DRUPAL_ROOT . '/' . $module_path . '/recipes/lock_action_empty_langcode';

    $recipe = Recipe::createFromDirectory($recipe_path);
    RecipeRunner::processRecipe($recipe);

    return new Response('ok');
  }

  /**
   * Applies the unknown-langcode config action validation error test recipe.
   */
  public function applyUnknownLangcodeActionRecipe(): Response {
    $module_path = $this->moduleExtensionList->getPath('config_language_lock_test_config_action');
    $recipe_path = DRUPAL_ROOT . '/' . $module_path . '/recipes/lock_action_unknown_langcode';

    $recipe = Recipe::createFromDirectory($recipe_path);
    RecipeRunner::processRecipe($recipe);

    return new Response('ok');
  }

}
