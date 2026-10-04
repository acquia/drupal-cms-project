<?php

declare(strict_types=1);

namespace Drupal\custom_field_linkit\Plugin\CustomField\FieldWidget;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\custom_field\Attribute\CustomFieldWidget;
use Drupal\custom_field\Plugin\CustomField\FieldType\LinkTypeInterface;
use Drupal\custom_field\Plugin\CustomField\FieldWidget\LinkWidget;
use Drupal\custom_field\Plugin\CustomFieldTypeInterface;
use Drupal\file\FileInterface;
use Drupal\linkit\Utility\LinkitHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'linkit' widget.
 */
#[CustomFieldWidget(
  id: 'linkit',
  label: new TranslatableMarkup('Linkit'),
  category: new TranslatableMarkup('Url'),
  field_types: [
    'link',
  ],
)]
class LinkitWidget extends LinkWidget {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The file url generator service.
   *
   * @var \Drupal\Core\File\FileUrlGeneratorInterface
   */
  protected FileUrlGeneratorInterface $fileUrlGenerator;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->fileUrlGenerator = $container->get('file_url_generator');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'linkit_profile' => 'default',
      'linkit_auto_link_text' => FALSE,
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function widgetSettingsForm(FormStateInterface $form_state, CustomFieldTypeInterface $field): array {
    $element = parent::widgetSettingsForm($form_state, $field);
    $settings = $this->getSettings() + static::defaultSettings();
    $profile_storage = $this->entityTypeManager->getStorage('linkit_profile');

    $options = array_map(function ($linkit_profile) {
      return $linkit_profile->label();
    }, $profile_storage->loadMultiple());

    $element['linkit_profile'] = [
      '#type' => 'select',
      '#title' => $this->t('Linkit profile'),
      '#options' => $options,
      '#default_value' => $settings['linkit_profile'],
    ];
    $element['linkit_auto_link_text'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Automatically populate link text from entity label'),
      '#default_value' => $settings['linkit_auto_link_text'],
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  public function widget(FieldItemListInterface $items, int $delta, array $element, array &$form, FormStateInterface $form_state, CustomFieldTypeInterface $field): array {
    $element = parent::widget($items, $delta, $element, $form, $form_state, $field);
    assert($field instanceof LinkTypeInterface);
    $settings = $this->getSettings() + static::defaultSettings();
    /** @var \Drupal\Core\Field\FieldItemInterface $item */
    $item = $items[$delta];
    $uri = $item->{$field->getName()} ?? NULL;

    // Try to fetch entity information from the URI.
    $default_allowed = !$item->isEmpty() && !empty($uri) && ($this->currentUser->hasPermission('link to any page') || $field->getUrl($item)->access());
    $entity = $default_allowed && $uri ? LinkitHelper::getEntityFromUri($uri) : NULL;

    // Display entity URL consistently across all entity types.
    if ($entity instanceof FileInterface) {
      // File entities are anomalies, so we handle them differently.
      $element['uri']['#default_value'] = $this->fileUrlGenerator->generateString($entity->getFileUri());
    }
    elseif ($entity instanceof EntityInterface) {
      $uri_parts = parse_url((string) $uri);
      $uri_options = [];
      // Extract query parameters and fragment and merge them into $uri_options.
      if (isset($uri_parts['fragment']) && $uri_parts['fragment'] !== '') {
        $uri_options += ['fragment' => $uri_parts['fragment']];
      }
      if (!empty($uri_parts['query'])) {
        $uri_query = [];
        parse_str((string) $uri_parts['query'], $uri_query);
        $uri_options['query'] = $uri_query;
      }
      $element['uri']['#default_value'] = $entity->toUrl()->setOptions($uri_options)->toString();
    }

    $element['uri']['#type'] = 'linkit';
    $element['uri']['#description'] = $this->t('Start typing to find content or paste a URL and click on the suggestion below.');

    // If we're only showing the uri field, theme the description accordingly.
    if (!empty($element['#description']) && $element['#type'] !== 'fieldset') {
      // If we have the description of the type of field together with
      // the user provided description, we want to make a distinction
      // between "core help text" and "user entered help text". To make
      // this distinction more clear, we put them in an unordered list.
      $element['uri']['#description'] = [
        '#theme' => 'item_list',
        '#items' => [
          // Assume the user-specified description has the most relevance,
          // so place it first.
          $element['#description'],
          $element['uri']['#description'],
        ],
      ];
    }
    $element['uri']['#autocomplete_route_name'] = 'linkit.autocomplete';
    $element['uri']['#autocomplete_route_parameters'] = [
      'linkit_profile_id' => $settings['linkit_profile'],
    ];

    // Add a class to the title field.
    $element['title']['#attributes']['class'][] = 'linkit-widget-title';
    if ($settings['linkit_auto_link_text']) {
      $element['title']['#attributes']['data-linkit-widget-title-autofill-enabled'] = TRUE;
    }

    // Add linkit specific attributes.
    $element['attributes']['href'] = [
      '#type' => 'hidden',
      '#default_value' => $default_allowed ? $uri : '',
    ];
    $element['attributes']['data-entity-type'] = [
      '#type' => 'hidden',
      '#default_value' => $entity ? $entity->getEntityTypeId() : '',
    ];
    $element['attributes']['data-entity-uuid'] = [
      '#type' => 'hidden',
      '#default_value' => $entity ? $entity->uuid() : '',
    ];
    $element['attributes']['data-entity-substitution'] = [
      '#type' => 'hidden',
      '#default_value' => $entity ? ($entity->getEntityTypeId() === 'file' ? 'file' : 'canonical') : '',
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValue(mixed $value, $column): ?array {
    if (empty($value['uri'])) {
      return NULL;
    }

    $value['uri'] = LinkitHelper::uriFromUserInput($value['uri']);
    $value += ['options' => []];
    if (isset($value['options']['attributes'])) {
      $attributes = $value['options']['attributes'];
      $value['options']['attributes'] = array_filter($attributes, function ($attribute) {
        return $attribute !== "";
      });
      // Convert a class string to an array so that it can be merged reliably.
      if (isset($value['options']['attributes']['class']) && is_string($value['options']['attributes']['class'])) {
        $value['options']['attributes']['class'] = explode(' ', $value['options']['attributes']['class']);
      }
    }

    // Merge the linkit attributes.
    $value['options'] += $value['attributes'];

    return $value;
  }

}
