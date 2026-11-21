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
use Drupal\custom_field\Plugin\CustomField\FieldWidget\UrlWidget;
use Drupal\custom_field\Plugin\CustomFieldTypeInterface;
use Drupal\file\FileInterface;
use Drupal\linkit\Utility\LinkitHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'linkit_url' widget.
 */
#[CustomFieldWidget(
  id: 'linkit_url',
  label: new TranslatableMarkup('Linkit'),
  category: new TranslatableMarkup('Url'),
  field_types: [
    'uri',
  ],
)]
class LinkitUrlWidget extends UrlWidget {

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
    if (!empty($element['#description'])) {
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

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValue(mixed $value, $column): ?string {
    if (empty($value['uri'])) {
      return NULL;
    }

    return LinkitHelper::uriFromUserInput($value['uri']);
  }

}
