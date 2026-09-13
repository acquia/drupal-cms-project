<?php

declare(strict_types=1);

namespace Drupal\custom_field\Plugin\CustomField\FieldWidget;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Entity\TranslatableInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\custom_field\Attribute\CustomFieldWidget;
use Drupal\custom_field\Plugin\CustomField\FieldType\EntityReference;
use Drupal\custom_field\Plugin\CustomFieldTypeInterface;

/**
 * Plugin implementation of the 'hierarchical_select' widget.
 */
#[CustomFieldWidget(
  id: 'hierarchical_select',
  label: new TranslatableMarkup('Hierarchical select'),
  category: new TranslatableMarkup('Reference'),
  field_types: [
    'entity_reference',
  ],
)]
class HierarchicalSelectWidget extends EntityReferenceWidgetBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'force_deepest_level' => FALSE,
      'level_labels' => TRUE,
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function widgetSettingsForm(FormStateInterface $form_state, CustomFieldTypeInterface $field): array {
    $element = parent::widgetSettingsForm($form_state, $field);
    $settings = $this->getSettings() + static::defaultSettings();

    $element['force_deepest_level'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Force deepest level'),
      '#description' => $this->t('This will require the deepest level in the term tree to be selected.'),
      '#default_value' => $settings['force_deepest_level'],
    ];
    $element['level_labels'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show level labels'),
      '#description' => $this->t('Show labels above widgets. The first level will be the field label and child levels will be the parent term for that level.'),
      '#default_value' => $settings['level_labels'],
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function widget(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state, CustomFieldTypeInterface $field): array {
    $element = parent::widget($items, $delta, $element, $form, $form_state, $field);
    assert($field instanceof EntityReference);
    $field_settings = $field->getFieldSettings();
    $settings = $this->getSettings() + static::defaultSettings();
    /** @var \Drupal\taxonomy\TermStorageInterface $term_storage */
    $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');
    /** @var \Drupal\Core\Field\FieldItemInterface $item */
    $item = $items[$delta];
    $langcode = $item->getEntity()->language()->getId();
    $field_name = $item->getFieldDefinition()->getName();
    $name = $field->getName();
    $parents = $form['#parents'] ?? [];
    $wrapper = $this->getUniqueElementId($form, $field_name, $delta, $name);
    $translations_enabled = $this->moduleHandler->moduleExists('content_translation');
    $target_type = $field->getTargetType();

    // Account for parents structure from paragraphs field if applicable.
    $value_keys = array_merge($parents, [$field_name, $delta, $name]);
    $values = $form_state->getValues();
    $field_value = NestedArray::getValue($values, $value_keys);

    // If there are no processed values, use the input.
    if (empty($field_value)) {
      $user_input = $form_state->getUserInput();
      $field_value = NestedArray::getValue($user_input, $value_keys);
    }
    if (!empty($field_value)) {
      $levels = $field_value['levels'] ?? [];
    }
    // Use the saved values.
    else {
      $levels = !empty($item->{$name}) ? $this->getPathToRoot((int) $item->{$name}) : [];
    }

    $handler = $field->getSelectionHandler($field_settings, $target_type);
    $base_options = [];
    $views_enabled = $this->moduleHandler->moduleExists('views');
    if ($views_enabled && $handler::class === 'Drupal\views\Plugin\EntityReferenceSelection\ViewsSelection') {
      $configuration = $handler->configuration;
      // Return early if the view hasn't been selected.
      if (empty($configuration['view']['view_name'])) {
        return $element;
      }
      try {
        /** @var \Drupal\views\Entity\View $view */
        $view = $this->entityTypeManager->getStorage('view')->load($configuration['view']['view_name']);
        $limit = 0;
        if ($display = $view->getDisplay($configuration['view']['display_name'])) {
          // If the views display has a limit set, use it.
          if (isset($display['display_options']['pager']['options']['items_per_page'])) {
            $limit = $display['display_options']['pager']['options']['items_per_page'];
          }
        }
        $views_options = $handler->getReferenceableEntities(NULL, 'CONTAINS', $limit);
        foreach ($views_options as $category => $terms) {
          /** @var \Drupal\taxonomy\Entity\Vocabulary $vocabulary */
          $vocabulary = $this->entityTypeManager->getStorage('taxonomy_vocabulary')->load($category);
          foreach ($terms as $id => $term) {
            $base_options[(string) $vocabulary->label()][$id] = $term;
          }
        }
      }
      catch (InvalidPluginDefinitionException | PluginNotFoundException $e) {
        return $element;
      }
    }
    else {
      $target_bundles = $field_settings['handler_settings']['target_bundles'] ?? [];
      if (empty($target_bundles)) {
        return $element;
      }
      $base_options = $this->getBaseOptions($target_bundles, $langcode, $translations_enabled);
    }

    $title = $element['#title'];
    // Unset the title so we can display it inline for the level.
    unset($element['#title']);
    if ($settings['level_labels']) {
      $element['#wrapper_attributes']['class'][] = 'custom-field-level-labels';
    }
    $element['#type'] = 'item';
    $element['#tree'] = TRUE;
    $element['levels'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => $wrapper,
        'class' => ['custom-field-levels-wrapper'],
      ],
    ];
    // Always display the top level term field.
    $element['levels'][0] = [
      '#type' => 'select',
      '#title' => $title,
      '#options' => $base_options,
      '#empty_option' => $this->t('- Select -'),
      '#default_value' => $levels[0] ?? NULL,
      '#required' => $element['#required'],
      '#ajax' => [
        'callback' => [$this, 'ajaxLoadNextLevel'],
        'wrapper' => $wrapper,
        'event' => 'change',
      ],
    ];
    foreach ($levels as $key => $level) {
      /** @var \Drupal\taxonomy\TermInterface $parent */
      if (!empty($level) && $parent = $term_storage->load($level)) {
        $child_options = $this->getOptionsForLevel($parent, $langcode, $translations_enabled);
        $level_key = $key + 1;
        $level_value = $levels[$level_key] ?? NULL;
        // Add a new level for each parent level that has child terms.
        if (!empty($child_options)) {
          $element['levels'][$level_key] = [
            '#type' => 'select',
            '#title' => $this->t('@term', ['@term' => $parent->getName()]),
            '#title_display' => $settings['level_labels'] ? 'before' : 'invisible',
            '#options' => $child_options,
            '#empty_option' => $this->t('- Select -'),
            '#default_value' => $level_value,
            '#required' => $settings['force_deepest_level'] ?? FALSE,
            '#ajax' => [
              'callback' => [$this, 'ajaxLoadNextLevel'],
              'wrapper' => $wrapper,
              'event' => 'change',
            ],
            '#element_validate' => [[$this, 'validateLevel']],
          ];
          // If the top level changed, unset the child level value.
          if (!empty($level_value) && !isset($child_options[$level_value])) {
            $element['levels'][$level_key]['#value'] = NULL;
          }
        }
      }
    }

    return $element;
  }

  /**
   * The #element_validate callback for levels.
   *
   * @param array $element
   *   An associative array containing the properties and children of the
   *   generic form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form for the form this element belongs to.
   *
   * @see \Drupal\Core\Render\Element\FormElement::processPattern()
   */
  public function validateLevel(array $element, FormStateInterface $form_state): void {
    /** @var \Drupal\taxonomy\TermStorageInterface $term_storage */
    $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');
    $parents = array_slice($element['#parents'], 0, -1);
    $values = $form_state->getValues();
    $user_input = $form_state->getUserInput();
    $levels = NestedArray::getValue($values, $parents);
    $error_name = implode('][', $element['#parents']);
    $errors = $form_state->getErrors();
    // Clear the errors to prevent stale values that don't exist in options.
    $form_state->clearErrors();
    if (isset($errors[$error_name])) {
      // Unset the error for our widget.
      unset($errors[$error_name]);
    }
    // Now loop through and re-apply the remaining form error messages.
    foreach ($errors as $name => $error_message) {
      $form_state->setErrorByName($name, $error_message);
    }
    foreach ($levels as $key => $level) {
      // Skip the root level.
      if ($key === 0) {
        continue;
      }
      if (!empty($level)) {
        // The parent id will be 1 level up.
        $parent_id = $levels[$key - 1];
        // Check if the parent level term id exists in the level's parents.
        $parent_terms = array_keys($term_storage->loadAllParents($level));
        if (!in_array($parent_id, $parent_terms)) {
          unset($levels[$key]);
        }
      }
    }
    // Update values and input.
    NestedArray::setValue($values, $parents, $levels);
    NestedArray::setValue($user_input, $parents, $levels);
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(CustomFieldTypeInterface $custom_item): bool {
    return $custom_item->getTargetType() === 'taxonomy_term';
  }

  /**
   * Ajax callback to retrieve levels.
   *
   * @param array $form
   *   The form from which the display IDs are being requested.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state of the form.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The Ajax response.
   */
  public function ajaxLoadNextLevel(array &$form, FormStateInterface $form_state): AjaxResponse {
    $trigger = $form_state->getTriggeringElement();
    $wrapper_id = $trigger['#ajax']['wrapper'];
    $form_state_keys = array_slice($trigger['#array_parents'], 0, -1);

    // Get the updated element from the form structure.
    $updated_element = NestedArray::getValue($form, $form_state_keys);
    $children = Element::children($updated_element);

    $response = new AjaxResponse();
    // Add a ReplaceCommand to replace the content inside the widget's wrapper.
    $response->addCommand(new ReplaceCommand('#' . $wrapper_id, $updated_element));
    if (count($children) > 1) {
      $ids = array_keys($response->getAttachments()['drupalSettings']['ajax'] ?? []);
      if (!empty($ids)) {
        // The first item in the array is actually the last triggered.
        $focus_id = reset($ids);
        $response->addCommand(new InvokeCommand('#' . $focus_id, 'focus'));
      }
    }
    $form_state->setRebuild();

    return $response;
  }

  /**
   * Gets the path from a term to the root of the taxonomy tree.
   *
   * @param int|null $tid
   *   The term ID.
   *
   * @return array
   *   An array containing the term IDs from root to the given term.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getPathToRoot(?int $tid): array {
    if (!$tid) {
      return [];
    }
    /** @var \Drupal\taxonomy\TermInterface $term */
    $term = $this->entityTypeManager->getStorage('taxonomy_term')->load($tid);
    if ($term) {
      $parent_tid = $term->get('parent')->target_id;
      if ($parent_tid != 0) {
        return array_merge($this->getPathToRoot((int) $parent_tid), [$tid]);
      }
    }
    return [$tid];
  }

  /**
   * Helper function to get top level term options.
   *
   * @param string[] $target_bundles
   *   The enabled target bundles.
   * @param string $langcode
   *   The language code.
   * @param bool $translations_enabled
   *   The 'content_translation' module is enabled.
   *
   * @return array
   *   The first level options.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getBaseOptions(array $target_bundles, string $langcode, bool $translations_enabled): array {
    $has_admin_access = $this->currentUser->hasPermission('administer taxonomy');
    /** @var \Drupal\taxonomy\TermStorageInterface $term_storage */
    $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');
    $options = [];
    foreach ($target_bundles as $target_bundle) {
      /** @var \Drupal\taxonomy\Entity\Vocabulary $vocabulary */
      if ($vocabulary = $this->entityTypeManager->getStorage('taxonomy_vocabulary')->load($target_bundle)) {
        /** @var \Drupal\taxonomy\TermInterface[]|object[] $terms */
        if ($terms = $term_storage->loadTree((string) $vocabulary->id(), 0, 1, $translations_enabled)) {
          foreach ($terms as $term) {
            if ($translations_enabled && ($term instanceof TranslatableInterface) && $term->hasTranslation($langcode)) {
              $term = $term->getTranslation($langcode);
              $tid = $term->id();
              $published = method_exists($term, 'isPublished') && $term->isPublished();
              $label = $term->label();
            }
            else {
              // @phpstan-ignore property.notFound
              $tid = $term->tid;
              // @phpstan-ignore property.notFound
              $published = $term->status;
              // @phpstan-ignore property.notFound
              $label = $term->name;
            }
            if (!$has_admin_access && !$published) {
              continue;
            }
            $options[(string) $vocabulary->label()][$tid] = $label;
          }
        }
      }
    }
    if (count($options) === 1) {
      $options = reset($options);
    }

    return $options;
  }

  /**
   * Helper function to return options array from child terms.
   *
   * @param \Drupal\taxonomy\TermInterface $parent
   *   The parent term.
   * @param string $langcode
   *   The language code.
   * @param bool $translations_enabled
   *   The 'content_translation' module is enabled.
   *
   * @return array<int|string, string>
   *   The options.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getOptionsForLevel($parent, string $langcode, bool $translations_enabled): array {
    /** @var \Drupal\taxonomy\TermStorageInterface $term_storage */
    $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');
    $has_admin_access = $this->currentUser->hasPermission('administer taxonomy');
    $vocabulary = $parent->bundle();
    $options = [];
    if ($terms = $term_storage->loadTree($vocabulary, (int) $parent->id(), 1, $translations_enabled)) {
      foreach ($terms as $term) {
        if ($translations_enabled && ($term instanceof TranslatableInterface) && $term->hasTranslation($langcode)) {
          $term = $term->getTranslation($langcode);
          $tid = $term->id();
          $published = method_exists($term, 'isPublished') && $term->isPublished();
          $label = $term->label();
        }
        else {
          // @phpstan-ignore property.notFound
          $tid = $term->tid;
          // @phpstan-ignore property.notFound
          $published = (bool) $term->status;
          // @phpstan-ignore property.notFound
          $label = $term->name;
        }
        if (!$has_admin_access && !$published) {
          continue;
        }
        $options[$tid] = $label;
      }
    }

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValue(mixed $value, array $column): ?array {
    $levels = $value['levels'] ? array_filter($value['levels']) : [];
    if (empty($levels)) {
      return NULL;
    }

    return ['target_id' => end($levels)];
  }

}
