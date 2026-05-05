<?php

namespace Drupal\RecipeKit\Installer\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element\Checkboxes;

/**
 * Provides a form to choose optional add-on recipes.
 */
final class RecipesForm extends RecipeSelectionFormBase {

  /**
   * {@inheritdoc}
   */
  public static function toInstallTask(array $install_state): array {
    // Skip this form if the profile doesn't define any optional recipe groups.
    if (empty($install_state['profile_info']['recipes']['optional'])) {
      $install_state['parameters']['add_ons'] = INSTALL_TASK_SKIP;
    }
    return [
      'display_name' => t('Choose add-ons'),
      'type' => 'form',
      'run' => $install_state['parameters']['add_ons'] ?? INSTALL_TASK_RUN_IF_REACHED,
      'function' => self::class,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'installer_recipes_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?array $install_state = NULL): array {
    $form = parent::buildForm($form, $form_state);
    $form['add_ons']['#value_callback'] = self::class . '::valueCallback';
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    global $install_state;
    parent::submitForm($form, $form_state);

    // Indicate that we're done with this form.
    // @see ::toInstallTask()
    $install_state['parameters']['add_ons'] = INSTALL_TASK_SKIP;
  }

  public static function valueCallback(&$element, $input, FormStateInterface $form_state): array {
    // If the input was a pipe-separated string or `*`, transform it -- this is
    // for compatibility with `drush site:install`.
    if (is_string($input)) {
      $selections = $input === '*'
        ? array_keys($element['#options'])
        : array_map('trim', explode('|', $input));

      $input = array_combine($selections, $selections);
    }
    return Checkboxes::valueCallback($element, $input, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  protected function getChoices(): iterable {
    global $install_state;
    $choices = [];

    foreach ($install_state['profile_info']['recipes']['optional'] ?? [] as $key => $value) {
      // For backwards compatibility, each choice can either be a flat array of
      // package names (in which case the key is the human-readable name), or
      // it can be an associative array with `name` and `packages` elements (the
      // best practice).
      if (array_is_list($value)) {
        $value = [
          'name' => $key,
          'packages' => $value,
          'description' => NULL,
        ];
      }
      // Allow the name to be a translatable string, which won't happen unless
      // we pass it through the translation system.
      $value['name'] = t($value['name']);
      $choices[$key] = $value;
    }
    return $choices;
  }

}
