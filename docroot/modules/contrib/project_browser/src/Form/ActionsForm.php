<?php

namespace Drupal\project_browser\Form;

use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\project_browser\ProjectRepository;

/**
 * Clear caches for this site.
 *
 * @internal
 *   This is an internal part of Project Browser and may be changed or removed
 *   at any time. It should not be used by external code.
 */
final class ActionsForm extends FormBase {

  use AutowireTrait;

  public function __construct(
    private readonly ProjectRepository $projectRepository,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'project_browser_actions_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['clear_storage'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Clear storage'),
      'description' => [
        '#prefix' => '<div class="form-item__description">',
        '#markup' => $this->t('Project Browser stores results from sources in non-volatile storage. You can clear that storage here to force refreshing data from the source.'),
        '#suffix' => '</div>',
      ],
      'clear' => [
        '#type' => 'submit',
        '#value' => $this->t('Clear storage'),
      ],
    ];
    return $form;
  }

  /**
   * Clears the caches.
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->projectRepository->clearAll();
    $this->messenger()->addStatus($this->t('Storage cleared.'));
  }

}
