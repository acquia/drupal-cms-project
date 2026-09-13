<?php

declare(strict_types=1);

namespace Drupal\ui_icons_media\Form;

use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\media_library\Form\AddFormBase;

/**
 * Media library add form.
 */
class IconMediaAddForm extends AddFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return $this->getBaseFormId() . '_icon';
  }

  /**
   * {@inheritdoc}
   */
  protected function buildInputElement(array $form, FormStateInterface $form_state): array {
    $media_type = $this->getMediaType($form_state);
    $field_definition = $media_type->getSource()->getSourceFieldDefinition($media_type);

    $form['container'] = [
      '#type' => 'container',
    ];

    $form['container']['icon'] = [
      '#type' => 'icon_autocomplete',
      '#title' => $this->t('Icon'),
      '#required' => TRUE,
      '#allowed_icon_pack' => $field_definition->getSetting('allowed_icon_pack') ?? [],
      '#return_id' => TRUE,
    ];

    $form['container']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add'),
      '#button_type' => 'primary',
      '#submit' => ['::addButtonSubmit'],
      '#ajax' => [
        'callback' => '::updateFormCallback',
        'wrapper' => 'media-library-wrapper',
        'url' => Url::fromRoute('media_library.ui'),
        'options' => [
          'query' => $this->getMediaLibraryState($form_state)->all() + [
            FormBuilderInterface::AJAX_FORM_REQUEST => TRUE,
          ],
        ],
      ],
    ];

    return $form;
  }

  /**
   * Submit handler for the add button.
   *
   * @param array $form
   *   The form render array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function addButtonSubmit(array $form, FormStateInterface $form_state): void {
    $this->processInputValues([$form_state->getValue('icon')], $form, $form_state);
  }

}
