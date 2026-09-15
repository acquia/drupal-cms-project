<?php

declare(strict_types=1);

namespace Drupal\custom_field\Plugin\CustomField\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\custom_field\Plugin\CustomField\FieldType\LinkTypeInterface;
use Drupal\custom_field\Plugin\CustomFieldTypeInterface;
use Drupal\custom_field\Plugin\CustomFieldWidgetBase;
use Drupal\custom_field\Trait\UriTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationListInterface;

/**
 * Base plugin class for url and link custom field widgets.
 */
class UrlWidgetBase extends CustomFieldWidgetBase {

  use UriTrait;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected AccountProxyInterface $currentUser;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->currentUser = $container->get('current_user');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function widget(FieldItemListInterface $items, int $delta, array $element, array &$form, FormStateInterface $form_state, CustomFieldTypeInterface $field): array {
    $element = parent::widget($items, $delta, $element, $form, $form_state, $field);
    assert($field instanceof LinkTypeInterface);
    $item = $items[$delta];
    $field_settings = $field->getFieldSettings();
    $display_uri = NULL;
    $subfield = $field->getName();

    if (!empty($item->{$subfield})) {
      // When adding multi-values, this can come back as an array initially for
      // link widget for some reason.
      $value = $item->{$subfield};
      if (is_array($value)) {
        $value = $value['uri'];
      }
      try {
        // The current field value could have been entered by a different user.
        // However, if it is inaccessible to the current user, do not display it
        // to them.
        if ($this->currentUser->hasPermission('link to any page') || $field->getUrl($item)->access()) {
          $display_uri = static::getUriAsDisplayableString($value);
        }
      }
      catch (\InvalidArgumentException $e) {
        // If $item->uri is invalid, show value as is, so the user can see what
        // to edit.
        // @todo Add logging here in https://www.drupal.org/project/drupal/issues/3348020
        $display_uri = $value;
      }
    }
    $element['uri'] = [
      '#type' => 'url',
      '#title' => $element['#title'],
      '#description_display' => $element['#description_display'],
      '#default_value' => $display_uri,
      '#element_validate' => [[static::class, 'validateUriElement']],
      '#maxlength' => 2048,
      '#link_type' => $field_settings['link_type'],
      '#required' => $element['#required'],
    ];

    // If the field is configured to support internal links, it cannot use the
    // 'url' form element, and we have to do the validation ourselves.
    if ($this->supportsInternalLinks($field_settings)) {
      $element['uri']['#type'] = 'entity_autocomplete';
      // @todo The user should be able to select an entity type. Will be fixed
      //   in https://www.drupal.org/node/2423093.
      $element['uri']['#target_type'] = 'node';
      // Disable autocompletion when the first character is '/', '#' or '?'.
      // cspell:ignore blacklist
      $element['uri']['#attributes']['data-autocomplete-first-character-blacklist'] = '/#?';

      // The link widget is doing its own processing in
      // static::getUriAsDisplayableString().
      $element['uri']['#process_default_value'] = FALSE;
    }

    // If the field is configured to allow only internal links, add a useful
    // element prefix and description.
    if (!$this->supportsExternalLinks($field_settings)) {
      $default_prefix = Url::fromRoute('<front>', [], ['absolute' => TRUE])->toString();
      $link_prefix = $field_settings['field_prefix'] == 'custom' ? $field_settings['field_prefix_custom'] : $default_prefix;
      if (!empty($link_prefix)) {
        $element['uri']['#field_prefix'] = rtrim($link_prefix, '/');
      }
      $element['uri']['#description'] = $this->t('This must be an internal path such as %add-node. You can also start typing the title of a piece of content to select it. Enter %front to link to the front page. Enter %nolink to display link text only. Enter %button to display keyboard-accessible link text only.', [
        '%add-node' => '/node/add',
        '%front' => '<front>',
        '%nolink' => '<nolink>',
        '%button' => '<button>',
      ]);
    }

    elseif ($this->supportsExternalLinks($field_settings)) {
      // If the field is configured to allow both internal and external links,
      // show a useful description.
      if ($this->supportsInternalLinks($field_settings)) {
        $element['uri']['#description'] = $this->t('Start typing the title of a piece of content to select it. You can also enter an internal path such as %add-node or an external URL such as %url. Enter %front to link to the front page. Enter %nolink to display link text only. Enter %button to display keyboard-accessible link text only.', [
          '%front' => '<front>',
          '%add-node' => '/node/add',
          '%url' => 'http://example.com',
          '%nolink' => '<nolink>',
          '%button' => '<button>',
        ]);
      }
      // If the field is configured to allow only external links, show a useful
      // description.
      else {
        $element['uri']['#description'] = $this->t('This must be an external URL such as %url.', ['%url' => 'http://example.com']);
      }
    }

    return $element;
  }

  /**
   * Indicates enabled support for link to routes.
   *
   * @param array<string, mixed> $settings
   *   An array of field settings.
   *
   * @return bool
   *   Returns TRUE if the Url field is configured to support links to
   *   routes, otherwise FALSE.
   */
  protected function supportsInternalLinks(array $settings): bool {
    $link_type = $settings['link_type'];
    return (bool) ($link_type & LinkTypeInterface::LINK_INTERNAL);
  }

  /**
   * Indicates enabled support for link to external URLs.
   *
   * @param array<string, mixed> $settings
   *   An array of field settings.
   *
   * @return bool
   *   Returns TRUE if the LinkItem field is configured to support links to
   *   external URLs, otherwise FALSE.
   */
  protected function supportsExternalLinks(array $settings): bool {
    $link_type = $settings['link_type'];
    return (bool) ($link_type & LinkTypeInterface::LINK_EXTERNAL);
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
      && !in_array($element['#value'][0], ['/', '?', '#'], TRUE)
      && !str_starts_with($element['#value'], '<front>')
    ) {
      $form_state->setError($element, new TranslatableMarkup('Manually entered paths should start with one of the following characters: / ? #'));
    }
  }

  /**
   * {@inheritdoc}
   *
   * Override the '%uri' message parameter, to ensure that 'internal:' URIs
   * show a validation error message that doesn't mention that scheme.
   */
  public function flagErrors(FieldItemListInterface $items, ConstraintViolationListInterface $violations, array $form, FormStateInterface $form_state): void {
    /** @var \Symfony\Component\Validator\ConstraintViolationInterface $violation */
    foreach ($violations as $offset => $violation) {
      $parameters = $violation->getParameters();
      if (isset($parameters['@uri'])) {
        $parameters['@uri'] = static::getUriAsDisplayableString($parameters['@uri']);
        $violations->set($offset, new ConstraintViolation(
          // phpcs:ignore Drupal.Semantics.FunctionT.NotLiteralString
          $this->t($violation->getMessageTemplate(), $parameters),
          $violation->getMessageTemplate(),
          $parameters,
          $violation->getRoot(),
          $violation->getPropertyPath(),
          $violation->getInvalidValue(),
          $violation->getPlural(),
          $violation->getCode()
        ));
      }
    }
    parent::flagErrors($items, $violations, $form, $form_state);
  }

}
