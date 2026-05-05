<?php

namespace Drupal\linkit\Plugin\Field\FieldWidget;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\FileInterface;
use Drupal\link\Plugin\Field\FieldWidget\LinkWidget;
use Drupal\linkit\Utility\LinkitHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'linkit' widget.
 *
 * @FieldWidget(
 *   id = "linkit",
 *   label = @Translation("Linkit"),
 *   field_types = {
 *     "link"
 *   }
 * )
 */
class LinkitWidget extends LinkWidget {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The entity repository.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected $entityRepository;

  /**
   * The linkit profile storage service.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $linkitProfileStorage;

  /**
   * The file URL generator.
   *
   * @var \Drupal\Core\File\FileUrlGeneratorInterface
   */
  protected $fileUrlGenerator;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->currentUser = $container->get('current_user');
    $instance->entityRepository = $container->get('entity.repository');
    $instance->linkitProfileStorage = $container->get('entity_type.manager')->getStorage('linkit_profile');
    $instance->fileUrlGenerator = $container->get('file_url_generator');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'linkit_profile' => 'default',
      'linkit_auto_link_text' => FALSE,
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $elements = parent::settingsForm($form, $form_state);

    $options = array_map(function ($linkit_profile) {
      return $linkit_profile->label();
    }, $this->linkitProfileStorage->loadMultiple());

    $elements['linkit_profile'] = [
      '#type' => 'select',
      '#title' => $this->t('Linkit profile'),
      '#options' => $options,
      '#default_value' => $this->getSetting('linkit_profile'),
    ];
    $elements['linkit_auto_link_text'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Automatically populate link text from entity label'),
      '#default_value' => $this->getSetting('linkit_auto_link_text'),
    ];

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = parent::settingsSummary();

    $linkit_profile_id = $this->getSetting('linkit_profile');
    $linkit_profile = $this->linkitProfileStorage->load($linkit_profile_id);

    if ($linkit_profile) {
      $summary[] = $this->t('Linkit profile: @linkit_profile', ['@linkit_profile' => $linkit_profile->label()]);
    }

    $auto_link_text = $this->getSetting('linkit_auto_link_text') ? $this->t('Yes') : $this->t('No');
    $summary[] = $this->t(
      'Automatically populate link text from entity label: @auto_link_text',
      ['@auto_link_text' => $auto_link_text]
    );

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);

    /** @var \Drupal\link\LinkItemInterface $item */
    $item = $items[$delta];
    $uri = $item->uri ?? NULL;

    try {
      // Try to fetch entity information from the URI.
      $default_allowed = !$item->isEmpty() && ($this->currentUser->hasPermission('link to any page') || $item->getUrl()->access());
    }
    catch (\InvalidArgumentException $e) {
      // Make sure we render the form if InvalidArgumentException is thrown.
    }

    if (!empty($item->options['data-entity-type']) && !empty($item->options['data-entity-uuid'])) {
      $entity = $this->entityRepository->loadEntityByUuid($item->options['data-entity-type'], $item->options['data-entity-uuid']);
      // Fix any mismatches between field value and options due to errors saved
      // before https://www.drupal.org/project/linkit/issues/3570672 was fixed.
      if ($entity && $uri && $uriEntity = LinkitHelper::getEntityFromUri($uri)) {
        if ($entity->getEntityTypeId() !== $uriEntity->getEntityTypeId() || $entity->uuid() !== $uriEntity->uuid()) {
          $entity = $uriEntity;
        }
      }
    }
    else {
      $entity = $default_allowed && $uri ? LinkitHelper::getEntityFromUri($uri) : NULL;
    }
    // Display entity URL consistently across all entity types.
    if ($entity instanceof FileInterface) {
      // File entities are anomalies, so we handle them differently.
      $element['uri']['#default_value'] = $this->fileUrlGenerator->generateString($entity->getFileUri());
    }
    elseif ($entity instanceof EntityInterface) {
      $uri_parts = parse_url($uri);
      $uri_options = [];
      // Extract query parameters and fragment and merge them into $uri_options.
      if (isset($uri_parts['fragment']) && $uri_parts['fragment'] !== '') {
        $uri_options += ['fragment' => $uri_parts['fragment']];
      }
      if (!empty($uri_parts['query'])) {
        $uri_query = [];
        parse_str($uri_parts['query'], $uri_query);
        $uri_options['query'] = isset($uri_options['query']) ? $uri_options['query'] + $uri_query : $uri_query;
      }
      $element['uri']['#default_value'] = $entity->toUrl()->setOptions($uri_options)->toString();
    }
    // Change the URI field to use the linkit profile.
    $element['uri']['#type'] = 'linkit';
    $element['uri']['#description'] = $this->t('Start typing to find content or paste a URL and click on the suggestion below.');
    $element['uri']['#autocomplete_route_name'] = 'linkit.autocomplete';
    $element['uri']['#autocomplete_route_parameters'] = [
      'linkit_profile_id' => $this->getSetting('linkit_profile'),
    ];

    // Add class to the URI fields item wrapper.
    $element['uri']['#wrapper_attributes']['class'][] = 'form-item--linkit-widget-uri';

    // Add a class to the title field and its item wrapper.
    $element['title']['#attributes']['class'][] = 'linkit-widget-title';
    $element['title']['#wrapper_attributes']['class'][] = 'form-item--linkit-widget-title';

    if ($this->getSetting('linkit_auto_link_text')) {
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

    // Add custom css for the widget representation:
    $element['#attached']['library'][] = 'linkit/linkit.widget';
    // Add a custom class to the parent container:
    $element['#attributes']['class'][] = 'linkit-widget-container';
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
    foreach ($values as &$value) {
      $value['uri'] = LinkitHelper::uriFromUserInput($value['uri']);
      $value += ['options' => $value['attributes']];
    }
    return $values;
  }

}
