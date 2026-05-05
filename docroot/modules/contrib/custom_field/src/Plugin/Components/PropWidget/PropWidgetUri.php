<?php

declare(strict_types=1);

namespace Drupal\custom_field\Plugin\Components\PropWidget;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\custom_field\Attribute\PropWidget;
use Drupal\custom_field\Plugin\PropWidgetBase;
use Drupal\custom_field\Trait\UriTrait;

/**
 * Plugin implementation of the 'uri' widget.
 */
#[PropWidget(
  id: 'uri',
  prop_type: 'uri',
  label: new TranslatableMarkup('Uri'),
)]
class PropWidgetUri extends PropWidgetBase {

  use UriTrait;

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'format' => 'uri',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function widget(array &$form, FormStateInterface $form_state, $value = [], $required = FALSE): array {
    $element = parent::widget($form, $form_state, $value, $required);
    $settings = $this->getSettings() + static::defaultSettings();
    $format = $settings['format'];
    $element['value']['#type'] = 'url';
    $display_uri = NULL;
    $value = $value['value'] ?? NULL;
    if (!empty($value)) {
      $display_uri = static::getUriAsDisplayableString($value);
    }
    $element['value']['#maxlength'] = 2048;
    $description = $this->t('This must be an external URL such as %url.', ['%url' => 'http://example.com']);
    $element['value']['#element_validate'] = [[static::class, 'validateUriElement']];
    if ($format === 'uri-reference') {
      $element['value']['#type'] = 'entity_autocomplete';
      $element['value']['#target_type'] = 'node';
      // cspell:ignore blacklist
      $element['value']['#attributes']['data-autocomplete-first-character-blacklist'] = '/#?';
      $element['value']['#process_default_value'] = FALSE;
      $description = $this->t('Start typing the title of a piece of content to select it. You can also enter an internal path such as %add-node or an external URL such as %url. Enter %front to link to the front page. Enter %nolink to display link text only. Enter %button to display keyboard-accessible link text only.', [
        '%front' => '<front>',
        '%add-node' => '/node/add',
        '%url' => 'http://example.com',
        '%nolink' => '<nolink>',
        '%button' => '<button>',
      ]);
    }
    if (!empty($settings['description'])) {
      // Output description as a list.
      $description = [
        '#theme' => 'item_list',
        '#items' => [
          $settings['description'],
          $description,
        ],
      ];
    }
    $element['value']['#description'] = $description;
    $element['value']['#default_value'] = $display_uri;

    return $element;
  }

  /**
   * Form element validation handler for the 'uri' element.
   *
   * Disallows saving inaccessible or untrusted URLs.
   *
   * @param array<string, mixed> $element
   *   The form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array<string, mixed> $form
   *   The form.
   */
  public static function validateUriElement(array $element, FormStateInterface $form_state, array $form): void {
    $uri = static::getUserEnteredStringAsUri($element['#value']);
    $form_state->setValueForElement($element, $uri);

    // If getUserEnteredStringAsUri() mapped the entered value to an 'internal:'
    // URI , ensure the raw value begins with '/', '?' or '#'.
    // @todo '<front>' is valid input for BC reasons, may be removed by
    //   https://www.drupal.org/node/2421941
    if (parse_url($uri, PHP_URL_SCHEME) === 'internal'
      && !\in_array($element['#value'][0], ['/', '?', '#'], TRUE)
      && !str_starts_with($element['#value'], '<front>')
    ) {
      $form_state->setError($element, new TranslatableMarkup('Manually entered paths should start with one of the following characters: / ? #'));
    }
  }

}
