<?php

declare(strict_types=1);

namespace Drupal\drupal_cms_installer\Hook;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Render\Element\Password;

/**
 * Contains form-related hook implementations.
 *
 * @internal
 *   Everything in the Drupal CMS installer is internal and may be changed or
 *   removed at any time without warning. External code should not interact
 *   with this class.
 */
final readonly class FormHooks {

  /**
   * Implements hook_form_FORM_ID_alter().
   */
  #[Hook('form_install_configure_form_alter')]
  public function installConfigureFormAlter(array &$form, FormStateInterface $form_state): void {
    global $install_state;

    $form['site_information']['#type'] = 'container';

    // If we collected the site name in a previous step, make it a hidden,
    // immutable value.
    if (array_key_exists('name', $install_state['parameters'])) {
      $form['site_information']['site_name'] = [
        '#type' => 'value',
        '#value' => $install_state['parameters']['name'],
      ];
    }

    // Use a custom value callback to set the site email. Normally this is a
    // required field, but setting `#access` to FALSE seems to bypass that.
    $form['site_information']['site_mail']['#access'] = FALSE;
    $form['site_information']['site_mail']['#value_callback'] = self::class . '::setSiteMail';

    $form['admin_account']['#type'] = 'container';
    // `admin` is a sensible name for user 1.
    $form['admin_account']['account']['name'] = [
      '#type' => 'value',
      '#value' => 'admin',
    ];
    $form['admin_account']['account']['mail'] = [
      '#type' => 'email',
      '#title' => t('Email'),
      '#required' => TRUE,
      '#default_value' => $install_state['forms']['install_configure_form']['account']['mail'] ?? '',
      '#weight' => 10,
    ];
    $form['admin_account']['account']['pass'] = [
      '#type' => 'password',
      '#title' => t('Password'),
      '#required' => TRUE,
      '#default_value' => $install_state['forms']['install_configure_form']['account']['pass']['pass1'] ?? '',
      '#weight' => 20,
      '#value_callback' => self::class . '::passwordValue',
    ];

    // Hide the timezone selection. Core automatically uses client-side
    // JavaScript to detect it, but we don't need to expose that to the user.
    // But the JavaScript expects the form elements to look a certain way, so
    // hiding the fields visually is the correct approach here.
    // @see core/misc/timezone.js
    $form['regional_settings']['#attributes']['class'][] = 'visually-hidden';
    // Don't allow the timezone selection to be tab-focused.
    $form['regional_settings']['date_default_timezone']['#attributes']['tabindex'] = -1;

    // We always install Automatic Updates, so we no need to expose the update
    // notification settings.
    $form['update_notifications']['#access'] = FALSE;
  }

  /**
   * Computes the value for the password field.
   *
   * @param array $element
   *   The element whose value is being set.
   * @param mixed $input
   *   The user input.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   *
   * @return mixed
   *   The value to set for the element.
   */
  public static function passwordValue(array &$element, mixed $input, FormStateInterface $form_state): mixed {
    // Work around the fact that Drush and `drupal install`, which submit this
    // form programmatically, assume the password is a password_confirm element.
    if (is_array($input) && $form_state->isProgrammed()) {
      $input = $input['pass1'];
    }
    return Password::valueCallback($element, $input, $form_state);
  }

  /**
   * Sets the site-wide email address to the administrator's.
   *
   * @param array $element
   *   The element whose value is being set.
   * @param mixed $input
   *   The user input.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   *
   * @return string
   *   The site-wide email address to use.
   */
  public static function setSiteMail(array &$element, mixed $input, FormStateInterface $form_state): string {
    // We can't use $form_state->getValues() because we're a value callback,
    // and therefore still in the middle of populating $form_state's values!
    $user_input = $form_state->getUserInput();
    return $user_input['account']['mail'] ?? strval($input);
  }

}
