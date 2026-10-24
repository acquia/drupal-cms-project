<?php

namespace Drupal\tagify_link\Plugin\Field\FieldWidget;

use Drupal\Component\Utility\Crypt;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Site\Settings;
use Drupal\Core\Url;
use Drupal\link\Plugin\Field\FieldWidget\LinkWidget;
use Drupal\tagify\Element\EntityAutocompleteTagify;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tagify widget for link fields.
 *
 * @FieldWidget(
 *   id = "tagify_link_widget",
 *   label = @Translation("Tagify link"),
 *   field_types = {
 *     "link"
 *   }
 * )
 */
class TagifyLinkWidget extends LinkWidget {

  /**
   * The key/value factory.
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueFactoryInterface
   */
  protected $keyValue;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->keyValue = $container->get('keyvalue');
    $instance->moduleHandler = $container->get('module_handler');
    $instance->entityTypeManager = $container->get('entity_type.manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'match_operator' => 'CONTAINS',
      'match_limit' => 20,
      'suggestions_dropdown' => 1,
      'placeholder' => '',
      'show_entity_id' => 0,
      'show_info_label' => 0,
      'info_label' => '',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $element = parent::settingsForm($form, $form_state);

    $element['match_operator'] = [
      '#type' => 'radios',
      '#title' => $this->t('Autocomplete matching'),
      '#default_value' => $this->getSetting('match_operator'),
      '#options' => [
        'STARTS_WITH' => $this->t('Starts with'),
        'CONTAINS' => $this->t('Contains'),
      ],
      '#description' => $this->t('Select the method used to collect autocomplete suggestions. Note that <em>Contains</em> can cause performance issues on sites with thousands of entities.'),
    ];
    $element['match_limit'] = [
      '#type' => 'number',
      '#title' => $this->t('Number of results'),
      '#default_value' => $this->getSetting('match_limit'),
      '#min' => 0,
      '#description' => $this->t('The number of suggestions that will be listed. Use <em>0</em> to remove the limit.'),
    ];
    $element['suggestions_dropdown'] = [
      '#type' => 'radios',
      '#title' => $this->t('Suggestions dropdown'),
      '#default_value' => $this->getSetting('suggestions_dropdown'),
      '#options' => [
        0 => $this->t('On click'),
        1 => $this->t('When 1 character is typed'),
      ],
      '#description' => $this->t('Select the method used to show suggestions dropdown.'),
    ];
    $element['placeholder'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Placeholder'),
      '#default_value' => $this->getSetting('placeholder'),
      '#description' => $this->t('Text that will be shown inside the field until a value is entered.'),
    ];
    $element['show_entity_id'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Include entity id'),
      '#default_value' => $this->getSetting('show_entity_id'),
      '#description' => $this->t('Include the entity ID within the tag. Only applies to internal links that resolve to an entity.'),
    ];
    $element['show_info_label'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Include info label'),
      '#default_value' => $this->getSetting('show_info_label'),
      '#description' => $this->t('Show an extra tag with information next to the entity label. Only applies to internal links that resolve to an entity.'),
    ];
    $info_label_states = [
      'visible' => [
        sprintf(':input[name="fields[%s][settings_edit_form][settings][show_info_label]"]', $this->fieldDefinition->getName()) => ['checked' => TRUE],
      ],
    ];
    $element['info_label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Info label content'),
      '#default_value' => $this->getSetting('info_label'),
      '#description' => $this->t('The extra information which will be shown within the tag. You can use tokens to make this dynamic.'),
      '#states' => $info_label_states,
    ];

    if ($this->moduleHandler->moduleExists('token')) {
      $element['info_label_tokens'] = [
        '#type' => 'item',
        '#theme' => 'token_tree_link',
        '#token_types' => ['node'],
        '#show_restricted' => TRUE,
        '#global_types' => FALSE,
        '#recursion_limit' => 3,
        '#states' => $info_label_states,
      ];
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    $summary = parent::settingsSummary();
    $operators = [
      'STARTS_WITH' => $this->t('Starts with'),
      'CONTAINS' => $this->t('Contains'),
    ];
    $summary[] = $this->t('Autocomplete matching: @match_operator', ['@match_operator' => $operators[$this->getSetting('match_operator')]]);
    $size = $this->getSetting('match_limit') ?: $this->t('unlimited');
    $summary[] = $this->t('Autocomplete suggestion list size: @size', ['@size' => $size]);
    $placeholder = $this->getSetting('placeholder');
    $summary[] = $placeholder ? $this->t('Placeholder: @placeholder', ['@placeholder' => $placeholder]) : $this->t('No placeholder');
    $summary[] = $this->getSetting('show_entity_id') ? $this->t('Include the entity ID within the tag') : $this->t('Remove the entity ID from the tag');
    if ($this->getSetting('show_info_label')) {
      $summary[] = $this->t('Include a label with extra information inside the tag');
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state): array {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);

    $uri = &$element['uri'];
    $uri['#attributes']['class'][] = 'tagify-link-widget';
    $uri['#attributes']['data-match-limit'] = $this->getSetting('match_limit');
    $uri['#attributes']['data-placeholder'] = $this->getSetting('placeholder');
    $uri['#attributes']['data-suggestions-dropdown'] = (int) $this->getSetting('suggestions_dropdown');
    $uri['#attributes']['data-show-entity-id'] = (int) $this->getSetting('show_entity_id');
    $uri['#attached']['library'][] = 'tagify_link/tagify_link';

    // Tag styling lives in the theme-specific tagify/gin and tagify/claro
    // libraries, not the unstyled base.
    if (_tagify_is_gin_theme_active()) {
      $uri['#attached']['library'][] = 'tagify/gin';
    }
    if (_tagify_is_claro_theme_active()) {
      $uri['#attached']['library'][] = 'tagify/claro';
    }

    // The uri element is only an entity_autocomplete when the field allows
    // internal links. Entity id / info label only make sense in that case.
    if (($uri['#type'] ?? '') === 'entity_autocomplete') {
      $target_type = $uri['#target_type'] ?? 'node';
      $handler = 'default';
      $selection_settings = [
        'match_operator' => $this->getSetting('match_operator'),
        'match_limit' => (int) $this->getSetting('match_limit'),
        'suggestions_dropdown' => (int) $this->getSetting('suggestions_dropdown'),
      ];
      if ($this->getSetting('show_info_label')) {
        $selection_settings['info_label'] = $this->getSetting('info_label');
      }

      // Store the selection settings, keyed by a hash passed in the route.
      $data = serialize($selection_settings) . $target_type . $handler;
      $key = Crypt::hmacBase64($data, Settings::getHashSalt());
      $store = $this->keyValue->get('entity_autocomplete');
      if (!$store->has($key)) {
        $store->set($key, $selection_settings);
      }

      $uri['#attributes']['data-autocomplete-url'] = Url::fromRoute('tagify.entity_autocomplete', [
        'target_type' => $target_type,
        'selection_handler' => $handler,
        'selection_settings_key' => $key,
      ])->toString();

      // The JS rebuilds the stored "entity:<type>/<id>" URI from the tag's
      // entity_id, so it needs to know the target type.
      $uri['#attributes']['data-target-type'] = $target_type;

      // Resolve an existing internal link so its badges render on load.
      $default = $this->buildDefaultValue($items, $delta);
      if ($default !== NULL) {
        $uri['#attributes']['data-default-value'] = $default;
      }
    }

    return $element;
  }

  /**
   * Builds the JSON tag descriptor for an existing internal-entity link.
   *
   * Delegates to EntityAutocompleteTagify::getTagifyDefaultValue() for the
   * access checks, token replacement and info-label HTML filtering.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $items
   *   The field items.
   * @param int $delta
   *   The delta of the item being edited.
   *
   * @return string|null
   *   A JSON-encoded tag descriptor, or NULL when the item is empty, is not an
   *   internal-entity link, or the entity is inaccessible.
   */
  protected function buildDefaultValue(FieldItemListInterface $items, int $delta): ?string {
    $uri = $items[$delta]->uri ?? '';
    // Match "entity:<type>/<id>" (ignore any trailing query/fragment).
    if (!preg_match('/^entity:([a-z_]+)\/(\d+)/', (string) $uri, $matches)) {
      return NULL;
    }
    [, $entity_type, $entity_id] = $matches;
    if (!$this->entityTypeManager->hasDefinition($entity_type)) {
      return NULL;
    }

    $entity = $this->entityTypeManager->getStorage($entity_type)->load($entity_id);
    if ($entity === NULL) {
      return NULL;
    }

    $info_label = $this->getSetting('show_info_label') ? $this->getSetting('info_label') : '';
    $json = EntityAutocompleteTagify::getTagifyDefaultValue([$entity], $info_label);

    return $json === '[]' ? NULL : $json;
  }

}
