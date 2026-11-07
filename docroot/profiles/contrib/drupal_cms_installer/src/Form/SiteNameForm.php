<?php

declare(strict_types=1);

namespace Drupal\drupal_cms_installer\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Defines a form to set the site name.
 *
 * @internal
 *   Everything in the Drupal CMS installer is internal and may be changed or
 *   removed at any time without warning. External code should not interact
 *   with this class.
 */
final class SiteNameForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'installer_site_name_form';
  }

  /**
   * {@inheritdoc}
   *
   * @phpstan-param array<string, mixed> $form
   * @phpstan-param array<string, mixed> $install_state
   *
   * @phpstan-return array<mixed>
   */
  public function buildForm(array $form, FormStateInterface $form_state, array $install_state = []): array {
    $form['site_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Site name'),
      '#required' => TRUE,
      '#default_value' => $install_state['forms']['install_configure_form']['site_name'] ?? $this->t('My Drupal CMS site'),
    ];
    $form['actions'] = [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Next'),
        '#button_type' => 'primary',
      ],
    ];
    $form['#title'] = $this->t('Give your site a name');

    return $form;
  }

  /**
   * {@inheritdoc}
   *
   * @phpstan-param array<string, mixed> $form
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $GLOBALS['install_state']['parameters']['name'] = $form_state->getValue('site_name');
  }

}
