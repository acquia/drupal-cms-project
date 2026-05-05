<?php

namespace Drupal\RecipeKit\Installer\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Recipe\Recipe;
use Drupal\Core\Recipe\RecipeFileException;
use Drupal\Core\Render\Element;
use Drupal\RecipeKit\Installer\Hooks;
use Symfony\Component\Finder\Finder;

/**
 * Provides a form to choose a site template.
 */
final class SiteTemplateForm extends RecipeSelectionFormBase {

  /**
   * {@inheritdoc}
   */
  public static function toInstallTask(array $install_state): array {
    return [
      'display_name' => t('Choose site template'),
      'type' => 'form',
      'run' => $install_state['parameters']['template'] ?? INSTALL_TASK_RUN_IF_REACHED,
      'function' => self::class,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'installer_site_template_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getChoices(): iterable {
    $choices = [];
    $dir = Hooks::getRecipePath();

    $finder = Finder::create()
      ->in($dir)
      ->files()
      ->followLinks()
      ->name('recipe.yml');

    foreach ($finder as $file) {
      try {
        $recipe = Recipe::createFromDirectory($file->getPath());
        if ($recipe->type === 'Site') {
          $name = basename($recipe->path);
          $choices[$name] = [
            'name' => $recipe->name,
            // @todo Make this work with recipes that don't have the `drupal`
            // vendor prefix.
            'packages' => ["drupal/$name"],
            'description' => $recipe->description,
            'extra' => $recipe->getExtra('recipe_installer_kit'),
          ];
        }
      }
      catch (RecipeFileException $e) {
        $this->messenger()->addError($e->getMessage());
      }
    }
    return $choices;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?array $install_state = NULL): array {
    $form = parent::buildForm($form, $form_state);
    $form['add_ons']['#type'] = 'radios';
    $form['add_ons']['#after_build'][] = [self::class, 'postBuildAddOns'];
    $form['#title'] = $this->t('Choose a site template');
    return $form;
  }

  /**
   * An #after_build callback for the `add_ons` element.
   *
   * @param array $element
   *   The element.
   *
   * @return array
   *   The modified element.
   */
  public static function postBuildAddOns(array $element): array {
    foreach (Element::children($element) as $key) {
      // Allow a more specific theme hook for more detailed theming.
      $element[$key]['#theme_wrappers'] = ['form_element__site_template'];
    }
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    global $install_state;
    parent::submitForm($form, $form_state);

    // If only one recipe has been chosen, and it's one of the choices from this
    // form, use that recipe's finish URL if it specifies one.
    // @see Hooks::installTasks()
    $packages = $install_state['parameters']['recipes'];
    if (count($packages) === 1) {
      $choice = array_find(
        $this->getChoices(),
        fn (array $choice): bool => $choice['packages'] === $packages,
      );
      if (isset($choice['extra']['finish_url'])) {
        $install_state['parameters']['finish_url'] = $choice['extra']['finish_url'];
      }
    }

    // Indicate that we're done with this form.
    // @see ::toInstallTask()
    $install_state['parameters']['template'] = INSTALL_TASK_SKIP;
  }

}
