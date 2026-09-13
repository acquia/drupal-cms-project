<?php

namespace Drupal\ai_seo\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class ReportTypeForm.
 */
class ReportTypeForm extends EntityForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    $report_type = $this->entity;

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $report_type->label(),
      '#description' => $this->t('Label for the Report Type.'),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $report_type->id(),
      '#machine_name' => [
        'exists' => '\Drupal\ai_seo\Entity\ReportType::load',
      ],
      '#disabled' => !$report_type->isNew(),
    ];

    $form['description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#default_value' => $report_type->getDescription(),
      '#description' => $this->t('Description of what this report type analyzes.'),
    ];

    $form['prompt'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Prompt'),
      '#default_value' => $report_type->getPrompt(),
      '#description' => $this->t('The AI prompt used for this report type.'),
      '#required' => TRUE,
      '#rows' => 20,
    ];

    $form['status'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enabled'),
      '#default_value' => $report_type->status(),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $report_type = $this->entity;
    $status = $report_type->save();

    switch ($status) {
      case SAVED_NEW:
        $this->messenger()->addMessage($this->t('Created the %label Report Type.', [
          '%label' => $report_type->label(),
        ]));
        break;

      default:
        $this->messenger()->addMessage($this->t('Saved the %label Report Type.', [
          '%label' => $report_type->label(),
        ]));
    }
    $form_state->setRedirectUrl($report_type->toUrl('collection'));
  }

}
