<?php

declare(strict_types=1);

namespace Drupal\custom_field_media\Plugin\CustomField\FieldWidget;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Component\Render\MarkupInterface;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\Core\Field\FieldConfigInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Security\TrustedCallbackInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\custom_field\Attribute\CustomFieldWidget;
use Drupal\custom_field\Plugin\CustomField\FieldWidget\EntityReferenceWidgetBase;
use Drupal\custom_field\Plugin\CustomFieldTypeInterface;
use Drupal\field_ui\FieldUI;
use Drupal\media\Entity\Media;
use Drupal\media\MediaInterface;
use Drupal\media_library\MediaLibraryState;
use Drupal\media_library\MediaLibraryUiBuilder;

/**
 * Plugin implementation of the 'media_library_widget' widget.
 */
#[CustomFieldWidget(
  id: 'media_library_widget',
  label: new TranslatableMarkup('Media library'),
  description: new TranslatableMarkup('Allows you to select items from the media library.'),
  category: new TranslatableMarkup('Media'),
  field_types: [
    'entity_reference',
  ],
)]
class MediaLibraryWidget extends EntityReferenceWidgetBase implements TrustedCallbackInterface {

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(CustomFieldTypeInterface $custom_item): bool {
    return $custom_item->getTargetType() === 'media';
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'media_types' => [],
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function widgetSettingsForm(FormStateInterface $form_state, CustomFieldTypeInterface $field): array {
    $element = parent::widgetSettingsForm($form_state, $field);
    $field_settings = $field->getFieldSettings();
    $media_type_ids = $this->getAllowedMediaTypeIdsSorted($field_settings);

    if (count($media_type_ids) <= 1) {
      return $element;
    }

    $element['media_types'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Tab order'),
        $this->t('Weight'),
      ],
      '#tabledrag' => [
        [
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => 'weight',
        ],
      ],
      '#value_callback' => [static::class, 'setMediaTypesValue'],
    ];

    $media_types = $this->entityTypeManager->getStorage('media_type')
      ->loadMultiple($media_type_ids);
    $weight = 0;
    foreach ($media_types as $media_type_id => $media_type) {
      $label = $media_type->label();
      $element['media_types'][$media_type_id] = [
        'label' => ['#markup' => $label],
        'weight' => [
          '#type' => 'weight',
          '#title' => $this->t('Weight for @title', ['@title' => $label]),
          '#title_display' => 'invisible',
          '#default_value' => $weight,
          '#attributes' => ['class' => ['weight']],
        ],
        '#weight' => $weight,
        '#attributes' => ['class' => ['draggable']],
      ];
      $weight++;
    }

    return $element;
  }

  /**
   * Value callback to optimize the way the media type weights are stored.
   *
   * The tabledrag functionality needs a specific weight field, but we don't
   * want to store this extra weight field in our settings.
   *
   * @param array<string, mixed> $element
   *   An associative array containing the properties of the element.
   * @param mixed $input
   *   The incoming input to populate the form element. If this is FALSE,
   *   the element's default value should be returned.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return mixed
   *   The value to assign to the element.
   */
  public static function setMediaTypesValue(array &$element, $input, FormStateInterface $form_state): mixed {
    if ($input === FALSE || $input === NULL || $form_state->isRebuilding()) {
      return $element['#default_value'] ?? [];
    }

    // Sort the media types by weight value and set the value in the form state.
    uasort($input, 'Drupal\Component\Utility\SortArray::sortByWeightElement');
    $sorted_media_type_ids = array_keys($input);
    $form_state->setValue($element['#parents'], $sorted_media_type_ids);

    // We have to unset the child elements containing the weight fields for each
    // media type to stop FormBuilder::doBuildForm() from processing the weight
    // fields as well.
    foreach ($sorted_media_type_ids as $media_type_id) {
      unset($element[$media_type_id]);
    }

    return $sorted_media_type_ids;
  }

  /**
   * {@inheritdoc}
   */
  public function widget(FieldItemListInterface $items, int $delta, array $element, array &$form, FormStateInterface $form_state, CustomFieldTypeInterface $field): array {
    $element = parent::widget($items, $delta, $element, $form, $form_state, $field);
    $field_parents = $element['#field_parents'];
    $view_builder = $this->entityTypeManager->getViewBuilder('media');
    $field_name = $items->getFieldDefinition()->getName();
    $sub_field_name = $field->getName();
    $field_widget_id = implode(':', $field_parents);
    $wrapper_id = $this->getUniqueElementId($form, $field_name, $delta, $field->getName());
    $name_key = str_replace('-', '_', $wrapper_id);
    $limit_validation_errors = [$field_parents];
    $field_settings = $field->getFieldSettings();
    $item = $items[$delta];
    $input = $form_state->getUserInput();
    $referenced_entity = NULL;
    $id = $item->{$sub_field_name};

    if ($input) {
      $selection = NestedArray::getValue($input, $field_parents);
      if (!empty($selection)) {
        $target_id = $selection['selection'][0]['target_id'] ?? NULL;
        $selected_id = $selection['media_library_selection'] ?? NULL;
        $id = $target_id ?? $selected_id;
      }
    }

    if ($id !== NULL && $id !== "") {
      $referenced_entity = $this->entityTypeManager->getStorage('media')->load($id);
    }

    $element += [
      '#type' => 'fieldset',
      '#cardinality' => 1,
      // If no target bundles are specified, all target bundles are allowed.
      '#target_bundles' => $field_settings['handler_settings']['target_bundles'] ?? [],
      '#attributes' => [
        'id' => $wrapper_id,
        'class' => ['js-media-library-widget'],
      ],
      '#pre_render' => [
        [$this, 'preRenderWidget'],
      ],
      '#theme_wrappers' => [
        'fieldset__media_library_widget',
      ],
    ];
    $element['#attached']['library'][] = 'media_library/widget';

    if ($field_settings['required']) {
      $element['#element_validate'][] = [static::class, 'validateRequired'];
    }

    // When the list of allowed types in the field configuration is null,
    // ::getAllowedMediaTypeIdsSorted() returns all existing media types. When
    // the list of allowed types is an empty array, we show a message to users
    // and ask them to configure the field if they have access.
    $allowed_media_type_ids = $this->getAllowedMediaTypeIdsSorted($field_settings);
    if (!$allowed_media_type_ids) {
      $element['no_types_message'] = [
        '#markup' => $this->getNoMediaTypesAvailableMessage($items->getFieldDefinition()),
      ];
      return $element;
    }

    if (empty($referenced_entity)) {
      $element['#field_prefix']['empty_selection'] = [
        '#markup' => $this->t('No media item is selected.'),
      ];
    }

    $element['selection'] = [
      '#type' => 'container',
      '#theme_wrappers' => [
        'container__media_library_widget_selection',
      ],
      '#attributes' => [
        'class' => [
          'js-media-library-selection',
        ],
      ],
    ];

    if ($referenced_entity) {
      if ($referenced_entity->access('view')) {
        // @todo Make the view mode configurable in https://www.drupal.org/project/drupal/issues/2971209
        $preview = $view_builder->view($referenced_entity, 'media_library');
      }
      else {
        $item_label = $referenced_entity->access('view label') ? $referenced_entity->label() : new FormattableMarkup('@label @id', [
          '@label' => $referenced_entity->getEntityType()->getSingularLabel(),
          '@id' => $referenced_entity->id(),
        ]);
        $preview = [
          '#theme' => 'media_embed_error',
          '#message' => $this->t('You do not have permission to view @item_label.', ['@item_label' => $item_label]),
        ];
      }
      $element['selection'][0] = [
        '#theme' => 'media_library_item__widget',
        '#attributes' => [
          'class' => [
            'js-media-library-item',
          ],
        ],
        'remove_button' => [
          '#type' => 'submit',
          '#name' => $name_key . '_media_remove',
          '#value' => $this->t('Remove'),
          '#media_id' => $referenced_entity->id(),
          '#attributes' => [
            'aria-label' => $referenced_entity->access('view label') ? $this->t('Remove @label', ['@label' => $referenced_entity->label()]) : $this->t('Remove media'),
          ],
          '#ajax' => [
            'callback' => [static::class, 'updateWidget'],
            'wrapper' => $wrapper_id,
            'progress' => [
              'type' => 'throbber',
              'message' => $referenced_entity->access('view label') ? $this->t('Removing @label.', ['@label' => $referenced_entity->label()]) : $this->t('Removing media.'),
            ],
          ],
          '#submit' => [[static::class, 'removeItem']],
          // Prevent errors in other widgets from preventing removal.
          '#limit_validation_errors' => $limit_validation_errors,
        ],
        'rendered_entity' => $preview,
        'target_id' => [
          '#type' => 'hidden',
          '#value' => $referenced_entity->id(),
        ],
      ];
    }

    // Create a new media library URL with the correct state parameters.
    $selected_type_id = reset($allowed_media_type_ids);
    // This particular media library opener needs some extra metadata for its
    // \Drupal\media_library\MediaLibraryOpenerInterface::getSelectionResponse()
    // to be able to target the element whose 'data-media-library-widget-value'
    // attribute is the same as $field_widget_id. The entity ID, entity type ID,
    // bundle, field name are used for access checking.
    $entity = $items->getEntity();
    $opener_parameters = [
      'field_widget_id' => $field_widget_id,
      'entity_type_id' => $entity->getEntityTypeId(),
      'bundle' => $entity->bundle(),
      'field_name' => $field_name,
    ];
    // Only add the entity ID when we actually have one. The entity ID needs to
    // be a string to ensure that the media library state generates its
    // tamper-proof hash in a consistent way.
    if (!$entity->isNew()) {
      $opener_parameters['entity_id'] = (string) $entity->id();

      if ($entity instanceof RevisionableInterface && $entity->getEntityType()->isRevisionable()) {
        $opener_parameters['revision_id'] = (string) $entity->getRevisionId();
      }
    }
    $remaining = $referenced_entity ? 0 : 1;
    $state = MediaLibraryState::create('custom_field_media.opener.form_element', $allowed_media_type_ids, $selected_type_id, $remaining, $opener_parameters);

    // Add a button that will load the Media library in a modal using AJAX.
    $element['open_button'] = [
      '#type' => 'button',
      '#value' => $this->t('Add media'),
      '#name' => $name_key . '_media_open',
      '#attributes' => [
        'class' => [
          'js-media-library-open-button',
        ],
      ],
      '#media_library_state' => $state,
      '#ajax' => [
        'callback' => [static::class, 'openMediaLibrary'],
        'progress' => [
          'type' => 'throbber',
          'message' => $this->t('Opening media library.'),
        ],
      ],
      // Allow the media library to be opened even if there are form errors.
      '#limit_validation_errors' => [],
    ];

    // When the user returns from the modal to the widget, we want to shift the
    // focus back to the open button. If the user is not allowed to add more
    // items, the button needs to be disabled. Since we can't shift the focus to
    // disabled elements, the focus is set back to the open button via
    // JavaScript by adding the 'data-disabled-focus' attribute.
    // @see Drupal.behaviors.MediaLibraryWidgetDisableButton
    if ($remaining === 0) {
      $triggering_element = $form_state->getTriggeringElement();
      if ($triggering_element && ($trigger_parents = $triggering_element['#array_parents']) && end($trigger_parents) === 'media_library_update_widget') {
        // The widget is being rebuilt from a selection change.
        $element['open_button']['#attributes']['data-disabled-focus'] = 'true';
        $element['open_button']['#attributes']['class'][] = 'visually-hidden';
      }
      else {
        // The widget is being built without a selection change, so we can just
        // set the item to disabled now, there is no need to set the focus
        // first.
        $element['open_button']['#disabled'] = TRUE;
        $element['open_button']['#attributes']['class'][] = 'visually-hidden';
      }
    }

    // This hidden field and button are used to add new items to the widget.
    $element['media_library_selection'] = [
      '#type' => 'hidden',
      '#attributes' => [
        // This is used to pass the selection from the modal to the widget.
        'data-media-library-widget-value' => $field_widget_id,
      ],
    ];

    // When a selection is made this hidden button is pressed to add new media
    // items based on the "media_library_selection" value.
    $element['media_library_update_widget'] = [
      '#type' => 'submit',
      '#value' => $this->t('Update widget'),
      '#name' => $name_key . '_media_update_widget',
      '#ajax' => [
        'callback' => [static::class, 'updateWidget'],
        'wrapper' => $wrapper_id,
        'progress' => [
          'type' => 'throbber',
          'message' => $this->t('Adding selection.'),
        ],
      ],
      '#attributes' => [
        'data-media-library-widget-update' => $field_widget_id,
        'class' => ['js-hide'],
      ],
      '#validate' => [[static::class, 'validateItems']],
      '#submit' => [[static::class, 'addItems']],
      // We need to prevent the widget from being validated when no media items
      // are selected. When a media field is added in a subform, entity
      // validation is triggered in EntityFormDisplay::validateFormValues().
      // Since the media item is not added to the form yet, this triggers errors
      // for required media fields.
      '#limit_validation_errors' => !empty($referenced_entity) ? $limit_validation_errors : [],
    ];

    return $element;
  }

  /**
   * Validates whether the widget is required and contains values.
   *
   * @param array<string, mixed> $element
   *   The form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array<string, mixed> $form
   *   The form array.
   */
  public static function validateRequired(array $element, FormStateInterface $form_state, array $form): void {
    // If a remove button triggered submit, this validation isn't needed.
    if (in_array([static::class, 'removeItem'], $form_state->getSubmitHandlers(), TRUE)) {
      return;
    }

    // If user has no access, the validation isn't needed.
    if (isset($element['#access']) && !$element['#access']) {
      return;
    }

    // Don't validate the default value form.
    if (isset($form_state->getBuildInfo()['base_form_id'])) {
      if ($form_state->getBuildInfo()['base_form_id'] == 'field_config_form') {
        return;
      }
    }

    // Trigger error if the field is required and no media is present. Although
    // the Form API's default validation would also catch this, the validation
    // error message is too vague, so a more precise one is provided here.
    if (empty($element['selection'][0]['target_id'])) {
      $form_state->setError($element, new TranslatableMarkup('@name field is required.', ['@name' => $element['#title']]));
    }
  }

  /**
   * Gets the enabled media type IDs sorted by weight.
   *
   * @param array<string, mixed> $settings
   *   The field settings.
   *
   * @return array|null
   *   The media type IDs sorted by weight.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getAllowedMediaTypeIdsSorted(array $settings): ?array {
    // Get the media type IDs sorted by the user in the settings form.
    $sorted_media_type_ids = $this->getSetting('media_types');

    // Get the configured media types from the field storage.
    $handler_settings = $settings['handler_settings'];
    // The target bundles will be blank when saving field storage settings,
    // when first adding a media reference field.
    $allowed_media_type_ids = $handler_settings['target_bundles'] ?? NULL;

    // When there are no allowed media types, return the empty array.
    if ($allowed_media_type_ids === []) {
      return $allowed_media_type_ids;
    }

    // When no target bundles are configured for the field, all are allowed.
    if ($allowed_media_type_ids === NULL) {
      $allowed_media_type_ids = $this->entityTypeManager->getStorage('media_type')->getQuery()->execute();
    }

    // When the user did not sort the media types, return the media type IDs
    // configured for the field.
    if (empty($sorted_media_type_ids)) {
      return $allowed_media_type_ids;
    }

    // Some of the media types may no longer exist, and new media types may have
    // been added that we don't yet know about. We need to make sure new media
    // types are added to the list and remove media types that are no longer
    // configured for the field.
    $new_media_type_ids = array_diff($allowed_media_type_ids, $sorted_media_type_ids);
    // Add new media type IDs to the list.
    $sorted_media_type_ids = array_merge($sorted_media_type_ids, array_values($new_media_type_ids));
    // Remove media types that are no longer available.
    $sorted_media_type_ids = array_intersect($sorted_media_type_ids, $allowed_media_type_ids);

    // Make sure the keys are numeric.
    return array_values($sorted_media_type_ids);
  }

  /**
   * Gets the message to display when there are no allowed media types.
   *
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The field definition.
   *
   * @return \Drupal\Component\Render\MarkupInterface
   *   The message to display when there are no allowed media types.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getNoMediaTypesAvailableMessage(FieldDefinitionInterface $field_definition): MarkupInterface {
    $entity_type_id = $field_definition->getTargetEntityTypeId();

    $default_message = $this->t('There are no allowed media types configured for this field. Contact the site administrator.');

    // Show the default message if the user does not have the permissions to
    // configure the fields for the entity type.
    if (!$this->currentUser->hasPermission("administer $entity_type_id fields")) {
      return $default_message;
    }

    // Show a message for privileged users to configure the field if the Field
    // UI module is not enabled.
    if (!$this->moduleHandler->moduleExists('field_ui')) {
      return $this->t('There are no allowed media types configured for this field. Edit the field settings to select the allowed media types.');
    }

    // Add a link to the message to configure the field if the Field UI module
    // is enabled.
    $route_parameters = FieldUI::getRouteBundleParameter($this->entityTypeManager->getDefinition($entity_type_id), $field_definition->getTargetBundle());
    if ($field_definition instanceof FieldConfigInterface) {
      $route_parameters['field_config'] = $field_definition->id();
      $url = Url::fromRoute('entity.field_config.' . $entity_type_id . '_field_edit_form', $route_parameters);
      if ($url->access($this->currentUser)) {
        return $this->t('There are no allowed media types configured for this field. <a href=":url">Edit the field settings</a> to select the allowed media types.', [
          ':url' => $url->toString(),
        ]);
      }
    }

    // If the user for some reason doesn't have access to the Field UI, fall
    // back to the default message.
    return $default_message;
  }

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks(): array {
    return ['preRenderWidget'];
  }

  /**
   * Prepares the widget's render element for rendering.
   *
   * @param array<string, mixed> $element
   *   The element to transform.
   *
   * @return array<string, mixed>
   *   The transformed element.
   *
   * @see ::formElement()
   */
  public function preRenderWidget(array $element): array {
    if (isset($element['open_button'])) {
      $element['#field_suffix']['open_button'] = $element['open_button'];
      unset($element['open_button']);
    }
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValue(mixed $value, array $column): mixed {
    if (isset($value['selection']) && is_array($value['selection'])) {
      return reset($value['selection'])['target_id'];
    }
    return NULL;
  }

  /**
   * Submit callback for remove buttons.
   *
   * @param array<string, mixed> $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public static function removeItem(array $form, FormStateInterface $form_state): void {
    // During the form rebuild, formElement() will create field item widget
    // elements using re-indexed deltas, so clear out FormState::$input to
    // avoid a mismatch between old and new deltas. The rebuilt elements will
    // have #default_value set appropriately for the current state of the field,
    // so nothing is lost in doing this.
    // @see Drupal\media_library\Plugin\Field\FieldWidget\MediaLibraryWidget::extractFormValues
    $triggering_element = $form_state->getTriggeringElement();
    $parents = array_slice($triggering_element['#parents'], 0, -2);
    $user_input = $form_state->getUserInput();
    NestedArray::setValue($user_input, $parents, NULL);
    $form_state->setUserInput($user_input);

    // Get the parents required to find the top-level widget element.
    if (count($triggering_element['#array_parents']) < 4) {
      throw new \LogicException('Expected the remove button to be more than four levels deep in the form. Triggering element parents were: ' . implode(',', $triggering_element['#array_parents']));
    }

    $form_state->setRebuild();
  }

  /**
   * AJAX callback to open the library modal.
   *
   * @param array<string, mixed> $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   An AJAX response to open the media library.
   */
  public static function openMediaLibrary(array $form, FormStateInterface $form_state): AjaxResponse {
    $triggering_element = $form_state->getTriggeringElement();
    $library_ui = \Drupal::service('media_library.ui_builder')->buildUi($triggering_element['#media_library_state']);
    $dialog_options = MediaLibraryUiBuilder::dialogOptions();
    return (new AjaxResponse())
      ->addCommand(new OpenModalDialogCommand($dialog_options['title'], $library_ui, $dialog_options));
  }

  /**
   * Validates that newly selected items can be added to the widget.
   *
   * Making an invalid selection from the view should not be possible, but we
   * still validate in case other selection methods (ex: upload) are valid.
   *
   * @param array<string, mixed> $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public static function validateItems(array $form, FormStateInterface $form_state): void {
    $button = $form_state->getTriggeringElement();
    $element = NestedArray::getValue($form, array_slice($button['#array_parents'], 0, -1));
    $media = static::getNewMediaItem($element, $form_state);
    if (!($media)) {
      return;
    }

    // Validate that selected media is of an allowed bundle.
    $all_bundles = \Drupal::service('entity_type.bundle.info')->getBundleInfo('media');
    $bundle_labels = array_map(function ($bundle) use ($all_bundles) {
      return $all_bundles[$bundle]['label'];
    }, $element['#target_bundles']);
    if ($element['#target_bundles'] && !in_array($media->bundle(), $element['#target_bundles'], TRUE)) {
      $form_state->setError($element, new TranslatableMarkup('The media item "@label" is not of an accepted type. Allowed types: @types', [
        '@label' => $media->label(),
        '@types' => implode(', ', $bundle_labels),
      ]));
    }
  }

  /**
   * Updates the field state and flags the form for rebuild.
   *
   * @param array<string, mixed> $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public static function addItems(array $form, FormStateInterface $form_state): void {
    // During the form rebuild, formElement() will create field item widget
    // elements using re-indexed deltas, so clear out FormState::$input to
    // avoid a mismatch between old and new deltas. The rebuilt elements will
    // have #default_value set appropriately for the current state of the field,
    // so nothing is lost in doing this.
    // @see Drupal\media_library\Plugin\Field\FieldWidget\MediaLibraryWidget::extractFormValues
    $button = $form_state->getTriggeringElement();
    $parents = array_slice($button['#parents'], 0, -1);
    $parents[] = 'selection';
    $user_input = $form_state->getUserInput();
    NestedArray::setValue($user_input, $parents, NULL);

    $element = NestedArray::getValue($form, array_slice($button['#array_parents'], 0, -1));

    $media = static::getNewMediaItem($element, $form_state);
    // Any ID can be passed to the widget, so we have to check access.
    if ($media && $media->access('view')) {
      $form_state->setRebuild();
    }
  }

  /**
   * Gets newly selected media items.
   *
   * @param array<string, mixed> $element
   *   The wrapping element for this widget.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return \Drupal\media\MediaInterface|null
   *   The selected media item.
   */
  protected static function getNewMediaItem(array $element, FormStateInterface $form_state): ?MediaInterface {
    // Get the new media IDs passed to our hidden button. We need to use the
    // actual user input, since when #limit_validation_errors is used, any
    // non-validated user input is not added to the form state.
    // @see FormValidator::handleErrorsWithLimitedValidation()
    $values = $form_state->getUserInput();
    $path = $element['#parents'];
    $value = NestedArray::getValue($values, $path);

    if (!empty($value['media_library_selection'])) {
      $id = $value['media_library_selection'];
      if (is_numeric($id)) {
        return Media::load($id);
      }
    }
    return NULL;
  }

  /**
   * AJAX callback to update the widget when the selection changes.
   *
   * @param array<string, mixed> $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   An AJAX response to update the selection.
   */
  public static function updateWidget(array $form, FormStateInterface $form_state): AjaxResponse {
    $triggering_element = $form_state->getTriggeringElement();
    $wrapper_id = $triggering_element['#ajax']['wrapper'];

    // This callback is either invoked from the remove button or the update
    // button, which have different nesting levels.
    $is_remove_button = end($triggering_element['#parents']) === 'remove_button';
    $length = $is_remove_button ? -3 : -1;
    if (count($triggering_element['#array_parents']) < abs($length)) {
      throw new \LogicException('The element that triggered the widget update was at an unexpected depth. Triggering element parents were: ' . implode(',', $triggering_element['#array_parents']));
    }
    $parents = array_slice($triggering_element['#array_parents'], 0, $length);
    $element = NestedArray::getValue($form, $parents);

    // Always clear the textfield selection to prevent duplicate additions.
    $element['media_library_selection']['#value'] = '';

    $response = new AjaxResponse();
    $response->addCommand(new ReplaceCommand("#$wrapper_id", $element));

    // When the remove button is clicked, shift focus to the next remove button.
    // When the last item is deleted, we no longer have a selection and shift
    // the focus to the open button.
    if ($is_remove_button) {
      $delta_to_focus = 0;
      $response->addCommand(new InvokeCommand("#$wrapper_id [data-media-library-item-delta=$delta_to_focus]", 'focus'));
    }
    // Shift focus to the open button if the user removed the last selected
    // item, or when the user has added items to the selection and is allowed to
    // select more items. When the user is not allowed to add more items, the
    // button needs to be disabled. Since we can't shift the focus to disabled
    // elements, the focus is set via JavaScript by adding the
    // 'data-disabled-focus' attribute and we also don't want to set the focus
    // here.
    // @see Drupal.behaviors.MediaLibraryWidgetDisableButton
    elseif (!isset($element['open_button']['#attributes']['data-disabled-focus'])) {
      $response->addCommand(new InvokeCommand("#$wrapper_id .js-media-library-open-button", 'focus'));
    }

    return $response;
  }

}
