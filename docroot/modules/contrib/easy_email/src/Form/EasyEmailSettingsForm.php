<?php

namespace Drupal\easy_email\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configuration form for Easy Email global settings.
 */
class EasyEmailSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'easy_email_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'easy_email.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $config = $this->config('easy_email.settings');

    $form['#tree'] = TRUE;

    $form['purge'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Automatic email deletion'),
    ];

    $form['purge']['cron'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Delete emails automatically on cron run'),
      '#default_value' => $config->get('purge_on_cron'),
      '#description' => $this->t('Emails will be automatically deleted based on the settings for each template.
        This checkbox determines whether that happens on cron run or whether you will run the included Drush command separately.'),
    ];

    $form['purge']['cron_limit'] = [
      '#type' => 'number',
      '#title' => $this->t('Maximum number of emails to delete per cron run'),
      '#default_value' => $config->get('purge_cron_limit'),
      '#states' => [
        'required' => [
          ':input[name="purge[cron]"]' => ['checked' => TRUE],
        ],
      ]
    ];

    $form['attachments'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Attachments'),
    ];

    $allowed_paths = $config->get('allowed_attachment_paths');
    if (is_array($allowed_paths)) {
      $allowed_paths = implode("\n", $allowed_paths);
    }
    $form['attachments']['allowed_paths'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Allowed attachment paths'),
      '#description' => $this->t('Paths to files that are allowed to be attached to emails. One path per line. Use * as a wildcard. Example: public://*.txt'),
      '#default_value' => $allowed_paths,
    ];

    $allowed_extensions = $config->get('allowed_attachment_extensions');
    if (is_array($allowed_extensions)) {
      $allowed_extensions = implode("\n", $allowed_extensions);
    }
    $form['attachments']['allowed_extensions'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Allowed file extensions'),
      '#description' => $this->t('File extensions that are allowed to be attached. One extension per line (without the dot). Leave empty to allow all extensions except those explicitly blocked. Example: pdf'),
      '#default_value' => $allowed_extensions,
      '#rows' => 5,
    ];

    $blocked_extensions = $config->get('blocked_attachment_extensions');
    if (is_array($blocked_extensions)) {
      $blocked_extensions = implode("\n", $blocked_extensions);
    }
    $form['attachments']['blocked_extensions'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Blocked file extensions'),
      '#description' => $this->t('File extensions that are blocked from being attached. One extension per line (without the dot). These take priority over allowed extensions. Example: exe'),
      '#default_value' => $blocked_extensions,
      '#rows' => 5,
    ];

    $allowed_mime_types = $config->get('allowed_attachment_mime_types');
    if (is_array($allowed_mime_types)) {
      $allowed_mime_types = implode("\n", $allowed_mime_types);
    }
    $form['attachments']['allowed_mime_types'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Allowed MIME types'),
      '#description' => $this->t('MIME types that are allowed to be attached. One MIME type per line. Leave empty to allow all MIME types except those explicitly blocked. Example: application/pdf'),
      '#default_value' => $allowed_mime_types,
      '#rows' => 5,
    ];

    $blocked_mime_types = $config->get('blocked_attachment_mime_types');
    if (is_array($blocked_mime_types)) {
      $blocked_mime_types = implode("\n", $blocked_mime_types);
    }
    $form['attachments']['blocked_mime_types'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Blocked MIME types'),
      '#description' => $this->t('MIME types that are blocked from being attached. One MIME type per line. These take priority over allowed MIME types. Example: application/x-executable'),
      '#default_value' => $blocked_mime_types,
      '#rows' => 5,
    ];

    $form['attachments']['max_size'] = [
      '#type' => 'number',
      '#title' => $this->t('Maximum attachment size'),
      '#description' => $this->t('Maximum file size allowed for attachments. Leave empty for no limit.'),
      '#default_value' => $config->get('max_attachment_size'),
      '#min' => 0,
      '#step' => 0.1,
      '#field_suffix' => 'MB',
    ];

    $form['reports'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Reports'),
    ];
    $form['reports']['email_collection_access'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Display a log of emails under Reports'),
      '#default_value' => $config->get('email_collection_access'),
      '#description' => $this->t('When this is checked, a log of emails sent from the website is available
        to view under Reports. This log only includes saved emails. If you are not saving
        all emails, you may wish to disable this report to avoid confusion.'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('easy_email.settings');
    $config->set('purge_on_cron', $form_state->getValue(['purge', 'cron']));
    $config->set('purge_cron_limit', $form_state->getValue(['purge', 'cron_limit']));

    $allowed_attachment_paths = preg_split('/\r\n|[\r\n]/', $form_state->getValue(['attachments', 'allowed_paths']));
    $config->set('allowed_attachment_paths', $allowed_attachment_paths);

    // Process security settings
    $allowed_extensions = preg_split('/\r\n|[\r\n]/', $form_state->getValue(['attachments', 'allowed_extensions']));
    $allowed_extensions = array_filter(array_map('trim', $allowed_extensions));
    $allowed_extensions = array_map('mb_strtolower', $allowed_extensions);
    $config->set('allowed_attachment_extensions', $allowed_extensions);

    $blocked_extensions = preg_split('/\r\n|[\r\n]/', $form_state->getValue(['attachments', 'blocked_extensions']));
    $blocked_extensions = array_filter(array_map('trim', $blocked_extensions));
    $blocked_extensions = array_map('mb_strtolower', $blocked_extensions);
    $config->set('blocked_attachment_extensions', $blocked_extensions);

    $allowed_mime_types = preg_split('/\r\n|[\r\n]/', $form_state->getValue(['attachments', 'allowed_mime_types']));
    $allowed_mime_types = array_filter(array_map('trim', $allowed_mime_types));
    $allowed_mime_types = array_map('mb_strtolower', $allowed_mime_types);
    $config->set('allowed_attachment_mime_types', $allowed_mime_types);

    $blocked_mime_types = preg_split('/\r\n|[\r\n]/', $form_state->getValue(['attachments', 'blocked_mime_types']));
    $blocked_mime_types = array_filter(array_map('trim', $blocked_mime_types));
    $blocked_mime_types = array_map('mb_strtolower', $blocked_mime_types);
    $config->set('blocked_attachment_mime_types', $blocked_mime_types);

    $max_size = $form_state->getValue(['attachments', 'max_size']);
    $config->set('max_attachment_size', $max_size);

    $config->set('email_collection_access', (bool) $form_state->getValue(['reports', 'email_collection_access']));
    $config->save();
    parent::submitForm($form, $form_state);
  }

}
