<?php

declare(strict_types=1);

namespace Drupal\custom_field\Plugin\CustomField\FieldFormatter;

use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Plugin implementation of the 'link' formatter.
 */
#[FieldFormatter(
  id: 'link',
  label: new TranslatableMarkup('Link'),
  field_types: [
    'link',
  ],
)]
class LinkFormatter extends UriLinkFormatter {

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $elements = parent::settingsForm($form, $form_state);
    $visibility_path = $form['#visibility_path'];
    $elements['link_text']['#description'] = $this->t('This field can serve as the fallback link text when title is unavailable.');
    $elements['url_plain']['#states'] = [
      'visible' => [
        ':input[name="' . $visibility_path . '[url_only]"]' => ['checked' => TRUE],
      ],
    ];

    return $elements;
  }

}
