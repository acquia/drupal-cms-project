<?php

declare(strict_types=1);

namespace Drupal\custom_field_entity_browser\Plugin\CustomField\FieldWidget;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\custom_field\Attribute\CustomFieldWidget;
use Drupal\custom_field\Plugin\CustomField\FieldWidget\EntityReferenceWidgetBase;
use Drupal\custom_field\Plugin\CustomFieldTypeInterface;
use Drupal\entity_browser\Element\EntityBrowserElement;
use Drupal\entity_browser\FieldWidgetDisplayInterface;
use Drupal\entity_browser\FieldWidgetDisplayManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'entity_reference_entity_browser' field widget.
 *
 * Modified version of
 * \Drupal\entity_browser\Plugin\Field\FieldWidget\EntityReferenceBrowserWidget
 * with hardcoded limitation to cardinality = 1.
 */
#[CustomFieldWidget(
  id: 'entity_reference_entity_browser',
  label: new TranslatableMarkup('Entity browser'),
  description: new TranslatableMarkup('Allows you to select items using Entity Browser.'),
  category: new TranslatableMarkup('Reference'),
  field_types: [
    'entity_reference',
  ],
)]
class EntityReferenceBrowserWidget extends EntityReferenceWidgetBase {

  /**
   * The cardinality we can support.
   *
   * @var int
   */
  protected const CARDINALITY = 1;

  /**
   * The depth of the delete button.
   *
   * This property exists so it can be changed if subclasses.
   *
   * @var int
   */
  protected const DELETE_DEPTH = 4;

  /**
   * Field widget display plugin manager.
   *
   * @var \Drupal\entity_browser\FieldWidgetDisplayManager
   */
  protected FieldWidgetDisplayManager $fieldDisplayManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->fieldDisplayManager = $container->get('plugin.manager.entity_browser.field_widget_display');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'entity_browser' => NULL,
      'open' => FALSE,
      'field_widget_display' => 'label',
      'field_widget_edit' => TRUE,
      'field_widget_remove' => TRUE,
      'field_widget_replace' => FALSE,
      'field_widget_display_settings' => [],
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function widgetSettingsForm(FormStateInterface $form_state, CustomFieldTypeInterface $field): array {
    $element = parent::widgetSettingsForm($form_state, $field);
    $settings = $this->getSettings() + self::defaultSettings();
    $target_type = $field->getTargetType();
    $entity_type = $this->entityTypeManager->getStorage($target_type)->getEntityType();

    $browsers = [];
    try {
      foreach ($this->entityTypeManager->getStorage('entity_browser')->loadMultiple() as $browser) {
        $browsers[$browser->id()] = $browser->label();
      }
    }
    catch (\Exception $exception) {
      // Silent fail, for now.
    }

    $element['entity_browser'] = [
      '#title' => $this->t('Entity browser'),
      '#type' => 'select',
      '#default_value' => $settings['entity_browser'],
      '#options' => $browsers,
      '#empty_option' => $this->t('- Select -'),
      '#required' => TRUE,
    ];
    unset($browser, $browsers);

    $displays = [];
    foreach ($this->fieldDisplayManager->getDefinitions() as $id => $definition) {
      try {
        $field_widget_display = $this->fieldDisplayManager->createInstance($id);
        assert($field_widget_display instanceof FieldWidgetDisplayInterface);
        if ($field_widget_display->isApplicable($entity_type)) {
          $displays[$id] = $definition['label'];
        }
      }
      catch (\Exception $exception) {
        // Silent fail, for now.
      }
    }
    unset($definition, $field_widget_display, $id);

    $id = Html::getId($field->getName()) . '-field-widget-display-settings-ajax-wrapper-' . md5($this->getUniqueIdentifier($field));
    $element['field_widget_display'] = [
      '#title' => $this->t('Entity display plugin'),
      '#type' => 'radios',
      '#default_value' => $settings['field_widget_display'],
      '#options' => $displays,
      '#ajax' => [
        'callback' => [static::class, 'updateFieldWidgetDisplaySettings'],
        'wrapper' => $id,
      ],
      '#limit_validation_errors' => [],
    ];

    if ($settings['field_widget_display']) {
      $element['field_widget_display_settings'] = [
        '#type' => 'details',
        '#title' => $this->t('Entity display plugin configuration'),
        '#open' => TRUE,
        '#prefix' => '<div id="' . $id . '">',
        '#suffix' => '</div>',
        '#tree' => TRUE,
      ];

      try {
        $value_keys = [
          'fields',
          $this->fieldName,
          'settings_edit_form',
          'settings',
          'fields',
          $field->getName(),
        ];

        $field_widget_display = $this->fieldDisplayManager->createInstance(
          $form_state->getValue(
            [...$value_keys, 'field_widget_display'],
            $settings['field_widget_display']
          ),
          $form_state->getValue(
            [...$value_keys, 'field_widget_display_settings'],
            $settings['field_widget_display_settings']
          ) + [
            'entity_type' => $target_type,
          ]
        );
        assert($field_widget_display instanceof FieldWidgetDisplayInterface);
        $element['field_widget_display_settings'] += $field_widget_display->settingsForm($element, $form_state);
      }
      catch (\Exception $exception) {
        // Silent fail, for now.
      }
    }

    $element['field_widget_edit'] = [
      '#title' => $this->t('Display Edit button'),
      '#type' => 'checkbox',
      '#default_value' => $settings['field_widget_edit'],
    ];

    $element['field_widget_remove'] = [
      '#title' => $this->t('Display Remove button'),
      '#type' => 'checkbox',
      '#default_value' => $settings['field_widget_remove'],
    ];

    $element['field_widget_replace'] = [
      '#title' => $this->t('Display Replace button'),
      '#description' => $this->t('This button will only be displayed if there is a single entity in the current selection.'),
      '#type' => 'checkbox',
      '#default_value' => $settings['field_widget_replace'],
    ];

    $element['open'] = [
      '#title' => $this->t('Show widget details as open by default'),
      '#description' => $this->t('If marked, the fieldset container that wraps the browser on the entity form will be loaded initially expanded.'),
      '#type' => 'checkbox',
      '#default_value' => $settings['open'],
    ];

    return $element;
  }

  /**
   * Ajax callback that updates the field widget display settings fieldset.
   *
   * @param array<string, mixed> $form
   *   The form definition for the widget settings.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state of the (entire) configuration form.
   */
  public static function updateFieldWidgetDisplaySettings(array $form, FormStateInterface $form_state): mixed {
    $array_parents = $form_state->getTriggeringElement()['#array_parents'];
    $up_two_levels = array_slice($array_parents, 0, count($array_parents) - 2);
    $settings_path = array_merge($up_two_levels, ['field_widget_display_settings']);

    return NestedArray::getValue($form, $settings_path);
  }

  /**
   * {@inheritdoc}
   */
  public function widget(FieldItemListInterface $items, int $delta, array $element, array &$form, FormStateInterface $form_state, CustomFieldTypeInterface $field): array {
    $element = parent::widget($items, $delta, $element, $form, $form_state, $field);
    $field_settings = $field->getFieldSettings();
    $settings = $this->getSettings() + self::defaultSettings();
    $field_name = $items->getFieldDefinition()->getName();
    $parents = is_array($form['#parents']) ? $form['#parents'] : [];
    $entity = $this->formElementEntity($parents, $items, $delta, $form_state, $field);

    // @todo Figure out a better way to enforce a 'clean start' when adding
    // new field item for whose delta a value already may have been stored
    // in the form state (below), as the field item 'remove' button does not
    // exist when building the widget element, so we cannot attach
    // 'removeItemSubmit' to it.
    $triggering_element = $form_state->getTriggeringElement();
    if (isset($triggering_element['#name'])) {
      $match = implode('_', [...$parents, $field_name, 'add_more']);
      if ($triggering_element['#name'] === $match) {
        // If the delta is not present in the user input, it means it is new or
        // is being added again. In both cases, we want to start with an empty
        // selection.
        // By forcing $entity to NULL, a selection stored in the form state (if
        // any) will also be set to NULL hereafter.
        $user_input = $form_state->getUserInput();
        if (!NestedArray::keyExists($user_input, [
          ...$parents,
          $field_name,
          $delta,
        ])) {
          $entity = NULL;
        }
      }
    }

    // We store current entity ID as we might need them in future requests. If
    // some other part of the form triggers an AJAX request with
    // #limit_validation_errors we won't have access to the value of the
    // target_id element and won't be able to build the form as a result of
    // that. This will cause missing submit (Remove, Edit, ...) elements, which
    // might result in unpredictable results.
    $parent_entity = $items->getEntity();
    $form_state_key = static::getFormStateKey("{$parent_entity->getEntityTypeId()}:{$parent_entity->id()}", $field_name, $delta);
    $form_state->set($form_state_key, $entity?->id());

    $id_string = $this->getUniqueElementId($form, $field_name, $delta, $field->getName());
    $hidden_id = Html::getUniqueId($id_string . '-target-id');

    $element += [
      '#id' => $id_string,
      '#type' => 'details',
      '#open' => (!is_null($entity) || $settings['open']),
      '#required' => $field_settings['required'],
      // We are not using Entity browser's hidden element since we maintain
      // selected entities in it during the entire process.
      'target_id' => [
        '#type' => 'hidden',
        '#id' => $hidden_id,
        // We need to repeat ID here as it is otherwise skipped when rendering.
        '#attributes' => [
          'id' => $hidden_id,
        ],
        '#default_value' => is_null($entity) ? '' : "{$entity->getEntityTypeId()}:{$entity->id()}",
        // #ajax is officially not supported for hidden elements, but if we
        // specify event manually, it works.
        '#ajax' => [
          'callback' => [static::class, 'updateWidgetCallback'],
          'wrapper' => $id_string,
          'event' => 'entity_browser_value_updated',
        ],
      ],
    ];

    // Get configuration required to check entity browser availability.
    $cardinality = static::CARDINALITY;
    $selection_mode = EntityBrowserElement::SELECTION_MODE_APPEND;

    // Enable entity browser if requirements for that are fulfilled.
    if (EntityBrowserElement::isEntityBrowserAvailable($selection_mode, $cardinality, (int) !is_null($entity))) {
      $persistentData = $this->getPersistentData($field);

      $element['entity_browser'] = [
        '#type' => 'entity_browser',
        '#entity_browser' => $settings['entity_browser'],
        '#cardinality' => $cardinality,
        '#selection_mode' => $selection_mode,
        '#default_value' => $entity,
        '#entity_browser_validators' => $persistentData['validators'],
        '#widget_context' => $persistentData['widget_context'],
        '#custom_hidden_id' => $hidden_id,
        '#process' => [
          ['\Drupal\entity_browser\Element\EntityBrowserElement', 'processEntityBrowser'],
          [static::class, 'processEntityBrowser'],
        ],
      ];
      $element['target_id']['#attributes']['data-entity-browser-available'] = 1;
    }
    else {
      // Allow non-ajax remove button to trigger ajax refresh when
      // cardinality.
      $element['target_id']['#attributes']['data-entity-browser-visible'] = 0;
    }

    $element['#attached']['library'][] = 'entity_browser/entity_reference';

    if (!is_null($entity)) {
      $element['current'] = $this->displayCurrentSelection($id_string, [(string) $items->getName()], $entity, $delta, $field);
    }

    return $element;
  }

  /**
   * Render API callback: Processes the entity browser element.
   *
   * @param array<string, mixed> $element
   *   The element.
   *
   * @return array<string, mixed>
   *   The updated element.
   */
  public static function processEntityBrowser(array &$element): array {
    if (NestedArray::keyExists($element, ['#attached', 'drupalSettings', 'entity_browser'])) {
      $uuid = key($element['#attached']['drupalSettings']['entity_browser']);
      $element['#attached']['drupalSettings']['entity_browser'][$uuid]['selector'] = '#' . $element['#custom_hidden_id'];
    }
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValue(mixed $value, array $column): mixed {
    if (empty($value['target_id'])) {
      return NULL;
    }

    $value['target_id'] = explode(':', $value['target_id'])[1];
    unset($value['current']);

    return $value;
  }

  /**
   * AJAX form callback.
   *
   * @param array<string, mixed> $form
   *   The form structure where widgets are being attached to. This might be a
   *   full form structure, or a sub-element of a larger form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array<string, mixed>
   *   The form part to update.
   */
  public static function updateWidgetCallback(array $form, FormStateInterface $form_state): array {
    $trigger = $form_state->getTriggeringElement();
    $reopen_browser = FALSE;
    // AJAX requests can be triggered by hidden "target_id" element when
    // entities are added or by one of the "Remove" buttons. Depending on that
    // we need to figure out where root of the widget is in the form structure
    // and use this information to return correct part of the form.
    $parents = [];
    if (
      NestedArray::keyExists($trigger, ['#ajax', 'event'])
      && $trigger['#ajax']['event'] === 'entity_browser_value_updated'
    ) {
      $parents = array_slice($trigger['#array_parents'], 0, -1);
    }
    elseif ($trigger['#type'] === 'submit' && str_ends_with($trigger['#name'], '_entity_browser_remove')) {
      $parents = array_slice($trigger['#array_parents'], 0, -static::DELETE_DEPTH);
    }
    elseif ($trigger['#type'] === 'submit' && str_ends_with($trigger['#name'], '_entity_browser_replace')) {
      $parents = array_slice($trigger['#array_parents'], 0, -static::DELETE_DEPTH);
      // We need to re-open the browser. Instead of just passing "TRUE", send
      // to the JS the unique part of the button's name that needs to be clicked
      // on to relaunch the browser.
      $reopen_browser = implode('-', array_slice($trigger['#parents'], 0, -static::DELETE_DEPTH));
    }

    $parents = NestedArray::getValue($form, $parents);
    $parents['#attached']['drupalSettings']['entity_browser_reopen_browser'] = $reopen_browser;
    return $parents;
  }

  /**
   * Submit callback for replace and remove button.
   *
   * @param array<string, mixed> $form
   *   The form structure where widgets are being attached to. This might be a
   *   full form structure, or a sub-element of a larger form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public static function removeItemSubmit(array $form, FormStateInterface $form_state): void {

    $triggering_element = $form_state->getTriggeringElement();
    if (!empty($triggering_element['#attributes']['data-entity-id']) && isset($triggering_element['#attributes']['data-row-id'])) {
      $array_parents = array_slice($triggering_element['#array_parents'], 0, -static::DELETE_DEPTH);

      // Set new value for this widget.
      $target_id_element = &NestedArray::getValue($form, array_merge($array_parents, ['target_id']));
      $form_state->setValueForElement($target_id_element, '');
      $user_input = &$form_state->getUserInput();
      NestedArray::setValue($user_input, $target_id_element['#parents'], '');

      // Rebuild form.
      $form_state->setRebuild();
    }
  }

  /**
   * Builds the render array for displaying the current results.
   *
   * @param string $id
   *   The ID for the details element and button key prefixes.
   * @param string[] $field_parents
   *   Field parents.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The referenced entity.
   * @param int $delta
   *   The order of this item in the array of sub-elements (0, 1, 2, etc.).
   * @param \Drupal\custom_field\Plugin\CustomFieldTypeInterface $field
   *   The custom field type object.
   *
   * @return array<string, mixed>
   *   The render array for the current selection.
   */
  protected function displayCurrentSelection(string $id, array $field_parents, EntityInterface $entity, int $delta, CustomFieldTypeInterface $field): array {
    $settings = $this->getSettings() + self::defaultSettings();
    $name_key = str_replace('-', '_', $id);

    $target_entity_type = $field->getTargetType();
    $field_widget_display_settings = $settings['field_widget_display_settings'] ?? [];

    try {
      $field_widget_display = $this->fieldDisplayManager->createInstance(
        $settings['field_widget_display'],
        $field_widget_display_settings + ['entity_type' => $target_entity_type]
      );
      assert($field_widget_display instanceof FieldWidgetDisplayInterface);
    }
    catch (\Exception $exception) {
      return [];
    }

    $classes = [
      'entities-list',
      Html::cleanCssIdentifier("entity-type--$target_entity_type"),
    ];

    $edit_button_access = $settings['field_widget_edit'] && $entity->access('update', $this->currentUser);
    if ($entity->getEntityTypeId() === 'file') {
      // On file entities, the "edit" button shouldn't be visible unless
      // the module "file_entity" is present, which will allow them to be
      // edited on their own form.
      $edit_button_access &= $this->moduleHandler->moduleExists('file_entity');
    }

    $display = $field_widget_display->view($entity);
    if (is_string($display)) {
      $display = ['#markup' => $display];
    }

    return [
      '#theme_wrappers' => ['container'],
      '#attributes' => [
        'class' => $classes,
        'data-entity-browser-entities-list' => 1,
      ],
      'items' => [
        [
          '#theme_wrappers' => ['container'],
          '#attributes' => [
            'class' => ['item-container', Html::getClass($field_widget_display->getPluginId())],
            'data-entity-id' => $entity->getEntityTypeId() . ':' . $entity->id(),
            'data-row-id' => $delta,
          ],
          'display' => $display,
          'remove_button' => [
            '#type' => 'submit',
            '#value' => $this->t('Remove'),
            '#ajax' => [
              'callback' => [static::class, 'updateWidgetCallback'],
              'wrapper' => $id,
            ],
            '#submit' => [[static::class, 'removeItemSubmit']],
            '#name' => $name_key . '_entity_browser_remove',
            '#limit_validation_errors' => [array_merge($field_parents, [$field->getName()])],
            '#attributes' => [
              'data-entity-id' => $entity->getEntityTypeId() . ':' . $entity->id(),
              'data-row-id' => $delta,
              'class' => ['remove-button'],
            ],
            '#access' => (bool) $settings['field_widget_remove'],
          ],
          'replace_button' => [
            '#type' => 'submit',
            '#value' => $this->t('Replace'),
            '#ajax' => [
              'callback' => [static::class, 'updateWidgetCallback'],
              'wrapper' => $id,
            ],
            '#submit' => [[static::class, 'removeItemSubmit']],
            '#name' => $name_key . '_entity_browser_remove',
            '#limit_validation_errors' => [array_merge($field_parents, [$field->getName()])],
            '#attributes' => [
              'data-entity-id' => $entity->getEntityTypeId() . ':' . $entity->id(),
              'data-row-id' => $delta,
              'class' => ['replace-button'],
            ],
            '#access' => $settings['field_widget_replace'],
          ],
          'edit_button' => [
            '#type' => 'submit',
            '#value' => $this->t('Edit'),
            '#name' => $name_key . '_entity_browser_edit',
            '#ajax' => [
              'url' => Url::fromRoute(
                'entity_browser.edit_form', [
                  'entity_type' => $entity->getEntityTypeId(),
                  'entity' => $entity->id(),
                ]
              ),
              'options' => [
                'query' => [
                  'details_id' => $id,
                ],
              ],
            ],
            '#attributes' => [
              'class' => ['edit-button'],
            ],
            '#access' => $edit_button_access,
          ],
        ],
      ],
    ];
  }

  /**
   * Determines the entity used for the form element.
   *
   * @param string[] $parents
   *   The field parents.
   * @param \Drupal\Core\Field\FieldItemListInterface $items
   *   Array of default values for this field.
   * @param int $delta
   *   The order of this item in the array of sub-elements (0, 1, 2, etc.).
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param \Drupal\custom_field\Plugin\CustomFieldTypeInterface $field
   *   The custom field type object.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   Return the entity if available.
   */
  protected function formElementEntity(array $parents, FieldItemListInterface $items, int $delta, FormStateInterface $form_state, CustomFieldTypeInterface $field): ?EntityInterface {
    $entity_type = $field->getTargetType();
    try {
      $entity_storage = $this->entityTypeManager->getStorage($entity_type);
    }
    catch (\Exception $exception) {
      return NULL;
    }

    // Find IDs from target_id element (it stores selected entities in form).
    // This was added to help solve a really edge casey bug in IEF.
    if (($target_id_entity = $this->getEntityByTargetId($parents, $items, $delta, $form_state, $field)) !== NULL) {
      return $target_id_entity;
    }

    // Determine if we're submitting and if submit came from this widget.
    $is_relevant_submit = FALSE;
    if ($trigger = $form_state->getTriggeringElement()) {

      // Can be triggered by the hidden target_id element or "Remove" button.
      $last_parent = end($trigger['#parents']);
      if (in_array($last_parent, ['target_id', 'remove_button', 'replace_button'])) {

        // In case there are more instances of this widget on the same page, we
        // need to check if the submission came from this instance.
        $field_name_key = count($trigger['#parents']) - (static::DELETE_DEPTH + 1);

        $is_relevant_submit =
          array_key_exists($field_name_key, $trigger['#parents'])
          && ($trigger['#parents'][$field_name_key] === $field->getName())
          && ($trigger['#parents'][$field_name_key - 1] === $delta);
      }
    }

    if ($is_relevant_submit === TRUE) {
      // Submit was triggered by hidden "target_id" element when entities were
      // added via entity browser.
      $parents = [];
      if (!empty($trigger['#ajax']['event']) && $trigger['#ajax']['event'] === 'entity_browser_value_updated') {
        $parents = $trigger['#parents'];
      }
      // Submit was triggered by one of the "Remove" buttons. We need to walk
      // a few levels up to read the value of the "target_id" element.
      elseif ($trigger['#type'] === 'submit' && str_ends_with($trigger['#name'], '_entity_browser_remove')) {
        $parents = array_merge(array_slice($trigger['#parents'], 0, -static::DELETE_DEPTH), ['target_id']);
      }

      $value = ($parents !== []) ? $form_state->getValue($parents) : NULL;
      if (is_string($value)) {
        return $this->processEntityId($value);
      }

      return NULL;
    }
    // ID from a previous request might be saved in the form state.
    else {
      $parent_entity = $items->getEntity();
      $form_state_key = static::getFormStateKey("{$parent_entity->getEntityTypeId()}:{$parent_entity->id()}", $items->getFieldDefinition()->getName(), $delta);
      if ($form_state->has($form_state_key)) {

        $stored_id = $form_state->get($form_state_key);
        if (is_string($stored_id)) {
          return $entity_storage->load($stored_id);
        }
      }
    }

    // We are loading for the first time, so we need to load any existing values
    // that might already exist on the entity.
    return $items[$delta]->{$field->getName() . '__entity'};
  }

  /**
   * Get the selected element from the target_id element on form.
   *
   * @param string[] $parents
   *   The field parents.
   * @param \Drupal\Core\Field\FieldItemListInterface $items
   *   Array of default values for this field.
   * @param int $delta
   *   The order of this item in the array of sub-elements (0, 1, 2, etc.).
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param \Drupal\custom_field\Plugin\CustomFieldTypeInterface $field
   *   The custom field type object.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   Return the entity if available.
   */
  protected function getEntityByTargetId(array $parents, FieldItemListInterface $items, int $delta, FormStateInterface $form_state, CustomFieldTypeInterface $field): ?EntityInterface {
    $target_id_element_path = [...$parents, $items->getName(), $delta, $field->getName(), 'target_id'];

    $user_input = $form_state->getUserInput();
    if (!NestedArray::keyExists($user_input, $target_id_element_path)) {
      return NULL;
    }

    // @todo Figure out how to avoid using raw user input.
    // (this comment is copied from \Drupal\entity_browser\Plugin\Field\FieldWidget\EntityReferenceBrowserWidget::getEntitiesByTargetId)
    $value = NestedArray::getValue($user_input, $target_id_element_path);
    if (is_string($value)) {
      return $this->processEntityId($value);
    }

    return NULL;
  }

  /**
   * Generate md5 hash using field parent keys array.
   *
   * @param string[] $field_parents
   *   The field parents.
   *
   * @return string
   *   The hash.
   */
  protected function getFieldParentsMd5Hash(array $field_parents): string {
    return md5((string) json_encode($field_parents));
  }

  /**
   * Gets data that should persist across Entity Browser renders.
   *
   * @param \Drupal\custom_field\Plugin\CustomFieldTypeInterface $field
   *   The custom field type object.
   *
   * @return array<string, array<string, mixed>>
   *   Data that should persist after the Entity Browser is rendered.
   */
  protected function getPersistentData(CustomFieldTypeInterface $field): array {
    $field_settings = $field->getFieldSettings();
    $handler = $field_settings['handler_settings'];
    return [
      'validators' => [
        'entity_type' => ['type' => $field->getTargetType()],
      ],
      'widget_context' => [
        'target_bundles' => !empty($handler['target_bundles']) ? $handler['target_bundles'] : [],
        'target_entity_type' => $field->getTargetType(),
        'cardinality' => static::CARDINALITY,
      ],
    ];
  }

  /**
   * Returns a unique identifier for the field.
   *
   * Based on \Drupal\Core\Field\FieldDefinition::getUniqueIdentifier.
   *
   * @param \Drupal\custom_field\Plugin\CustomFieldTypeInterface $field
   *   The custom field type object.
   *
   * @return string
   *   The identifier.
   */
  protected function getUniqueIdentifier(CustomFieldTypeInterface $field): string {
    return $field->getDataType() . '-' . $field->getTargetType() . '-' . $field->getName();
  }

  /**
   * Processes 'raw' entity ID input and loads the corresponding entity.
   *
   * @param string $user_input
   *   The string containing the entity type and ID.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   Return the entity if available.
   */
  protected function processEntityId(string $user_input): ?EntityInterface {
    $entities = EntityBrowserElement::processEntityIds($user_input);
    return $entities !== [] ? reset($entities) : NULL;
  }

  /**
   * Returns a key used to store the previously loaded entity.
   *
   * @param string $id
   *   The 'raw' entity ID of the parent entity.
   * @param string $field_name
   *   The (custom field) field name on the parent entity.
   * @param int $delta
   *   The order of this item in the array of sub-elements (0, 1, 2, etc.).
   *
   * @return string[]
   *   A key for form state storage.
   */
  protected static function getFormStateKey(string $id, string $field_name, int $delta): array {
    $parts = [
      $id,
      $field_name,
      $delta,
    ];
    return ['entity_browser_widget', implode(':', $parts)];
  }

  /**
   * {@inheritdoc}
   */
  public function calculateWidgetDependencies(): array {
    $dependencies = parent::calculateWidgetDependencies();
    $browser = $this->getSetting('entity_browser') ?? NULL;
    if ($browser) {
      /** @var \Drupal\entity_browser\Entity\EntityBrowser $entity_browser */
      $entity_browser = $this->entityTypeManager->getStorage('entity_browser')->load($browser);
      if ($entity_browser) {
        $dependencies[$entity_browser->getConfigDependencyKey()][] = $entity_browser->getConfigDependencyName();
      }
    }

    return $dependencies;
  }

  /**
   * {@inheritdoc}
   */
  public function onWidgetDependencyRemoval(array $dependencies): array {
    $settings = $this->getSettings();
    $changed = FALSE;
    $changed_settings = [];
    $browser = $this->getSetting('entity_browser') ?? NULL;
    if ($browser) {
      /** @var \Drupal\entity_browser\Entity\EntityBrowser $entity_browser */
      $entity_browser = $this->entityTypeManager->getStorage('entity_browser')->load($browser);
      if ($entity_browser && !empty($dependencies[$entity_browser->getConfigDependencyKey()][$entity_browser->getConfigDependencyName()])) {
        $settings['entity_browser'] = NULL;
        $changed = TRUE;
      }
    }
    if ($changed) {
      $changed_settings = $settings;
    }

    return $changed_settings;
  }

}
