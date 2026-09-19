<?php

declare(strict_types=1);

namespace Drupal\tagify\Plugin\better_exposed_filters\filter;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\KeyValueStore\KeyValueStoreInterface;
use Drupal\better_exposed_filters\Plugin\better_exposed_filters\filter\FilterWidgetBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tagify autocomplete widget for entity-reference exposed filters.
 *
 * @BetterExposedFiltersFilterWidget(
 *   id = "bef_tagify",
 *   label = @Translation("Tagify"),
 * )
 */
class Tagify extends FilterWidgetBase {

  /**
   * The entity_autocomplete key value store.
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueStoreInterface
   */
  protected KeyValueStoreInterface $keyValue;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $request = $container->get('request_stack')->getCurrentRequest();
    $configFactory = $container->get('config.factory');
    $instance = new static($configuration, $plugin_id, $plugin_definition, $request, $configFactory);
    $instance->keyValue = $container->get('keyvalue')->get('entity_autocomplete');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(mixed $filter = NULL, array $filter_options = []): bool {
    if (is_null($filter)) {
      return FALSE;
    }
    // Only applicable to taxonomy autocomplete (textfield) filters.
    // Dropdown taxonomy filters and other options-based filters are handled
    // by TagifySelect (bef_tagify_select).
    if (is_a($filter, 'Drupal\taxonomy\Plugin\views\filter\TaxonomyIndexTid')) {
      return $filter_options['type'] !== 'select';
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    $config = parent::defaultConfiguration();
    $config['advanced']['match_operator'] = 'CONTAINS';
    $config['advanced']['max_items'] = 10;
    $config['advanced']['placeholder'] = '';

    return $config;
  }

  /**
   * {@inheritdoc}
   */
  public function exposedFormAlter(array &$form, FormStateInterface $form_state): void {
    parent::exposedFormAlter($form, $form_state);
    $this->alterTagifyElement($form);
  }

  /**
   * Rebuilds an entity reference exposed filter as a Tagify element.
   *
   * Separated from exposedFormAlter() so it can be exercised without a fully
   * bootstrapped Views handler and view object.
   *
   * @param array $form
   *   The exposed filter form, altered by reference.
   */
  protected function alterTagifyElement(array &$form): void {
    $field_id = $this->getExposedFilterFieldId();
    if (!isset($form[$field_id])) {
      return;
    }

    // Entity reference field: convert to entity_autocomplete_tagify.
    if (isset($form[$field_id]['#target_type']) && isset($form[$field_id]['#tags'])) {
      $existing = $form[$field_id];
      $is_multiple = !empty($this->handler->options['expose']['multiple']);
      $form[$field_id] = [
        '#type' => 'entity_autocomplete_tagify',
        '#target_type' => $form[$field_id]['#target_type'],
        '#tags' => $form[$field_id]['#tags'],
        // The identifier is the widget container class js/tagify.js counts
        // selected tags in, so the limit-reached affordances (hidden
        // suggestions, the "Tags are limited to" footer) work. Only single
        // value filters need it, as TagifySelect does.
        '#identifier' => $is_multiple ? NULL : $field_id,
        // Views owns this switch. -1 is this element's "unlimited" sentinel;
        // select_tagify uses 0 instead — do not harmonise them.
        '#cardinality' => $is_multiple ? -1 : 1,
        '#selection_handler' => $form[$field_id]['#selection_handler'] ?? 'default',
        '#selection_settings' => $form[$field_id]['#selection_settings'] ?? [],
        '#match_operator' => $this->configuration['advanced']['match_operator'],
        '#max_items' => (int) $this->configuration['advanced']['max_items'],
        '#placeholder' => $this->configuration['advanced']['placeholder'],
        '#attributes' => [
          'class' => [$field_id],
        ],
        '#element_validate' => [[$this, 'elementValidate']],
      ];
      // The rebuild owns only the Tagify keys, so carry over what Views and
      // FilterWidgetBase already set (label, Collapsible '#group', Hide label,
      // Sort options). '#placeholder' is excluded: this widget owns it.
      foreach ([
        '#title',
        '#title_display',
        '#description',
        '#group',
        '#weight',
        '#required',
        '#pre_process',
      ] as $property) {
        if (isset($existing[$property])) {
          $form[$field_id][$property] = $existing[$property];
        }
      }
      return;
    }

    // Fail gracefully if not an entity reference element.
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {

    $form = parent::buildConfigurationForm($form, $form_state);

    $form['advanced']['match_operator'] = [
      '#type' => 'radios',
      '#title' => $this->t('Autocomplete matching'),
      '#default_value' => $this->configuration['advanced']['match_operator'],
      '#options' => $this->getMatchOperatorOptions(),
      '#description' => $this->t('Select the method used to collect autocomplete suggestions. Applies to entity reference fields only. Note that <em>Contains</em> can cause performance issues on sites with thousands of entities.'),
    ];

    $form['advanced']['max_items'] = [
      '#type' => 'number',
      '#title' => $this->t('Number of results'),
      '#default_value' => $this->configuration['advanced']['max_items'],
      '#min' => 0,
      '#description' => $this->t('The number of suggestions that will be listed. Use <em>0</em> to remove the limit.'),
    ];

    $form['advanced']['placeholder'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Placeholder'),
      '#description' => $this->t('Text to be shown in the Tagify field until a value is selected.'),
      '#default_value' => $this->configuration['advanced']['placeholder'],
    ];

    // Unset default placeholder text option.
    unset($form['advanced']['placeholder_text']);

    return $form;
  }

  /**
   * Validates and processes the autocomplete element values.
   *
   * @param array $element
   *   The form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   *
   * @throws \JsonException
   */
  public static function elementValidate(array $element, FormStateInterface $form_state): void {
    $value = $form_state->getValue($element['#parents']);

    // valueCallback() runs before validation and converts the flat array of
    // entity IDs (from clean URL params like field[]=30) into a Tagify JSON
    // string. By the time this validator executes $value is always a JSON
    // string or NULL — never an array.
    if ($value && ($items = json_decode($value, TRUE, 512, JSON_THROW_ON_ERROR))) {
      $formatted_items = self::formattedItems($items);
      // Caps raw-JSON input only (?field[]=13&[]=1 is already capped by
      // valueCallback() from the same '#cardinality'). Truncate rather than
      // error: an exposed filter is a navigational control, so failing hard on
      // a bookmarked URL is worse than honouring the first value.
      if ((int) ($element['#cardinality'] ?? -1) === 1) {
        $formatted_items = array_slice($formatted_items, 0, 1);
      }
      if (!empty($formatted_items)) {
        $form_state->setValue($element['#parents'], $formatted_items);
      }
    }
  }

  /**
   * Formats filter items.
   *
   * @param array $items
   *   The filter items.
   *
   * @return array
   *   The formatted filter items.
   */
  protected static function formattedItems(array $items): array {
    $formatted_items = [];
    foreach ($items as $item) {
      if (!isset($item['entity_id']) || !is_numeric($item['entity_id'])) {
        continue;
      }
      $formatted_items[] = ['target_id' => (int) $item['entity_id']];
    }

    return $formatted_items;
  }

  /**
   * Returns the options for the match operator.
   *
   * @return array
   *   List of options.
   */
  protected function getMatchOperatorOptions(): array {
    return [
      'STARTS_WITH' => $this->t('Starts with'),
      'CONTAINS' => $this->t('Contains'),
    ];
  }

}
