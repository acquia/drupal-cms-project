<?php

declare(strict_types=1);

namespace Drupal\drupal_cms_helper\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\drupal_cms_helper\Hook\TelemetryHooks;

/**
 * A simple form that lets the user opt in to anonymous telemetry.
 *
 * @internal
 *   This is an internal part of Drupal CMS and may be changed or removed at any
 *   time without warning. External code should not interact with this class.
 */
final class TelemetryOptInForm extends FormBase {

  public function __construct(
    protected readonly TelemetryHooks $hooks,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'drupal_cms_telemetry_opt_in_form';
  }

  /**
   * {@inheritdoc}
   *
   * @phpstan-param array<mixed> $form
   *
   * @phpstan-return array<mixed>
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $this->hooks->alterSiteInformationForm($form, $form_state);

    $form['actions'] = [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Save'),
      ],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   *
   * @phpstan-param array<mixed> $form
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->hooks->saveTelemetrySetting($form, $form_state);
  }

}
