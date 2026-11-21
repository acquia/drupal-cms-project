<?php

declare(strict_types=1);

namespace Drupal\google_tag_ajax_test\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\google_tag\Ajax\GoogleTagEventCommand;

/**
 * Form whose Ajax response sends a gtag event before another command.
 */
final class AjaxEventForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'google_tag_ajax_test_event_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['result'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'google-tag-ajax-result'],
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Send event'),
      '#ajax' => [
        'callback' => '::ajaxSubmit',
      ],
    ];
    return $form;
  }

  /**
   * Ajax callback: sends a gtag event, then updates the page.
   */
  public function ajaxSubmit(array &$form, FormStateInterface $form_state): AjaxResponse {
    $response = new AjaxResponse();
    $response->addCommand(new GoogleTagEventCommand('ajax_test', ['value' => 1]));
    $response->addCommand(new HtmlCommand('#google-tag-ajax-result', 'Commands after gtagEvent ran'));
    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
  }

}
