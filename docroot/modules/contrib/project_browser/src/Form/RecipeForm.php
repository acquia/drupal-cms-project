<?php

declare(strict_types=1);

namespace Drupal\project_browser\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Config\Checkpoint\CheckpointStorageInterface;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Recipe\Recipe;
use Drupal\Core\Recipe\RecipeConfigurator;
use Drupal\Core\Recipe\RecipeInputFormTrait;
use Drupal\Core\Recipe\RecipeRunner;
use Drupal\Core\Render\Element;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\project_browser\RefreshProjectsCommand;

/**
 * Collects input for a recipe, then applies it.
 *
 * @internal
 *   This is an internal part of Project Browser and may be changed or removed
 *   at any time. It should not be used by external code.
 */
final class RecipeForm extends FormBase {

  use AutowireTrait;
  use RecipeInputFormTrait;

  public function __construct(
    private readonly PrivateTempStoreFactory $tempStoreFactory,
    private readonly CheckpointStorageInterface $checkpointStorage,
  ) {}

  /**
   * Returns the recipes being handled by this form.
   *
   * @return \Drupal\Core\Recipe\Recipe[]
   *   The recipes being applied by this form.
   */
  private function getRecipes(): array {
    // Clear the static recipe cache to prevent a bug.
    // @todo Remove this when https://drupal.org/i/3495305 is fixed.
    $reflector = new \ReflectionProperty(RecipeConfigurator::class, 'cache');
    $reflector->setValue(NULL, []);

    // @see \Drupal\project_browser\Activator\RecipeActivator::activate()
    $paths = $this->tempStoreFactory->get('project_browser')
      ->get('recipe_paths');
    return array_map(Recipe::createFromDirectory(...), $paths ?? []);
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    // Only consider recipes which take input.
    $recipes = array_values(array_filter(
      $this->getRecipes(),
      fn (Recipe $recipe): bool => (bool) $recipe->input->getDataDefinitions(),
    ));

    foreach ($recipes as $recipe) {
      $form += $this->buildRecipeInputForm($recipe);
    }
    // If we're dealing with more than one recipe, group the input fields.
    if (count($recipes) > 1) {
      foreach (Element::children($form) as $i => $key) {
        $form[$key] += [
          '#type' => 'details',
          '#title' => $recipes[$i]->name,
          '#open' => TRUE,
        ];
      }
    }

    $form['actions'] = [
      'apply' => [
        '#type' => 'submit',
        '#value' => $this->t('Continue'),
        '#ajax' => [
          'callback' => '::ajaxSubmit',
          'wrapper' => 'drupal-modal',
          'message' => $this->t('Applying recipe...'),
        ],
      ],
      '#type' => 'actions',
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);

    foreach ($this->getRecipes() as $recipe) {
      $this->validateRecipeInput($recipe, $form, $form_state);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $recipes = $this->getRecipes();

    // Create a checkpoint before applying the recipe(s).
    $checkpoint_name = (string) $this->formatPlural(
      count($recipes),
      'Project Browser checkpoint for @name',
      'Project Browser checkpoint for @count recipes',
      ['@name' => $recipes[0]->name],
    );
    $this->checkpointStorage->checkpoint($checkpoint_name);

    foreach ($recipes as $recipe) {
      $this->setRecipeInput($recipe, $form_state);
      RecipeRunner::processRecipe($recipe);
    }
    $this->tempStoreFactory->get('project_browser')->delete('recipe_paths');
  }

  /**
   * Closes the modal dialog after submitting the form via AJAX.
   */
  public function ajaxSubmit(): AjaxResponse {
    return (new AjaxResponse())
      ->addCommand(new RefreshProjectsCommand())
      ->addCommand(new CloseModalDialogCommand());
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'project_browser_apply_recipe_form';
  }

}
