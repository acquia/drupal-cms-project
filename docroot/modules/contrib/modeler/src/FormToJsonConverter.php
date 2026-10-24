<?php

namespace Drupal\modeler;

use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Converts a Drupal form array to a JSON-serializable format.
 */
class FormToJsonConverter {

  use StringTranslationTrait;

  /**
   * Maps a config-schema validation constraint to a widget "format".
   *
   * This keeps the modeler agnostic of vendor form-element type names: a field
   * advertises its technical contract via a constraint in the config schema,
   * and the React UI picks the right widget from the resulting "format". Add
   * future editors here (e.g. a Yaml constraint), not as per-type branches.
   */
  protected const CONSTRAINT_FORMATS = [
    'Json' => 'json',
  ];

  public function __construct(
    protected YamlSchemaLookup $yamlSchemaLookup,
    protected TypedConfigManagerInterface $typedConfigManager,
  ) {}

  /**
   * Derive a widget "format" for a field from its config-schema constraints.
   *
   * @param string $plugin_schema_key
   *   The plugin's config schema key (e.g. action.configuration.<id>).
   * @param string $field_key
   *   The field/property key within that config.
   *
   * @return string|null
   *   The format (e.g. "json"), or NULL when no known constraint applies.
   */
  protected function deriveFormat(string $plugin_schema_key, string $field_key): ?string {
    if ($plugin_schema_key === '') {
      return NULL;
    }
    try {
      $definition = $this->typedConfigManager->getDefinition($plugin_schema_key);
      $constraints = $definition['mapping'][$field_key]['constraints'] ?? [];
      foreach (self::CONSTRAINT_FORMATS as $constraint => $format) {
        if (array_key_exists($constraint, $constraints)) {
          return $format;
        }
      }
    }
    catch (\Throwable) {
      // No resolvable definition; fall back to the plain widget.
    }
    return NULL;
  }

  /**
   * Convert Drupal form array to JSON-serializable format.
   *
   * @param array $form
   *   The processed Drupal form array.
   * @param string $plugin_schema_key
   *   The plugin's config schema key for YAML schema discovery.
   *
   * @return array
   *   The JSON-serializable form representation.
   */
  public function convert(array $form, string $plugin_schema_key = ''): array {
    $json_form = [];
    // Track the most recent textarea key so we can link use_yaml /
    // validate_yaml checkboxes back to the textarea they control. This is
    // passed by reference into the recursive converter so that nested
    // children share the same tracking state as their siblings.
    $last_textarea_key = '';

    foreach (Element::children($form, TRUE) as $key) {
      // Skip internal Drupal form elements.
      if (in_array($key, ['actions', 'form_build_id', 'form_token', 'form_id'], TRUE)) {
        continue;
      }
      $field = $this->convertElement($key, $form[$key], $plugin_schema_key, $last_textarea_key);
      if ($field !== NULL) {
        $json_form[] = $field;
      }
    }
    return $json_form;
  }

  /**
   * Convert a single form element to a JSON-serializable field.
   *
   * @param string $key
   *   The element's array key within its parent.
   * @param array $element
   *   The Drupal form element.
   * @param string $plugin_schema_key
   *   The plugin's config schema key for YAML schema discovery.
   * @param string $last_textarea_key
   *   (by reference) The most recent textarea key seen while iterating, used
   *   to link use_yaml / validate_yaml checkboxes back to their textarea.
   *
   * @return array|null
   *   The JSON-serializable field, or NULL when the element produces no field.
   */
  protected function convertElement(string $key, array $element, string $plugin_schema_key, string &$last_textarea_key): ?array {
    // A model owner hides fields it manages itself by denying access to them.
    // Dropping the element here covers groups and leaf elements alike, so a
    // hidden group takes its children with it.
    if (($element['#access'] ?? TRUE) === FALSE) {
      return NULL;
    }

    if ($key === 'eca_token_info') {
      $element['#markup'] = $this->t('This component supports tokens. Type [ in a configuration field to browse and insert tokens from step data, global, and template sources.');
    }

    // Container elements (details/fieldset/container) that actually wrap
    // child elements become a "group" carrying recursively-converted
    // children. A container WITH #markup and no real children falls through
    // to the markup branch below.
    $container_types = ['details', 'fieldset', 'container'];
    $children_keys = Element::children($element, TRUE);
    if (in_array($element['#type'] ?? '', $container_types, TRUE) && !empty($children_keys)) {
      $group = [
        'key' => $key,
        'type' => 'group',
        'title' => $element['#title'] ?? '',
      ];
      // For details, expose the open/closed state (defaults to open).
      if ($element['#type'] === 'details') {
        $group['open'] = (bool) ($element['#open'] ?? TRUE);
      }
      $states = $this->extractStates($element);
      if ($states !== NULL) {
        $group['states'] = $states;
      }
      $group['children'] = [];
      foreach ($children_keys as $child_key) {
        $child = $this->convertElement($child_key, $element[$child_key], $plugin_schema_key, $last_textarea_key);
        if ($child !== NULL) {
          $group['children'][] = $child;
        }
      }
      return $group;
    }

    // Handle elements with markup (either with container type
    // or without type).
    if (isset($element['#markup']) && in_array($element['#type'] ?? '', ['container', 'markup'], TRUE)) {
      $field = [
        'key' => $key,
        'type' => 'markup',
        'markup' => $element['#markup'],
      ];

      // Only add title if it exists.
      if (isset($element['#title'])) {
        $field['title'] = $element['#title'];
      }

      $states = $this->extractStates($element);
      if ($states !== NULL) {
        $field['states'] = $states;
      }

      return $field;
    }
    if (isset($element['#type'])) {
      $field = [
        'key' => $key,
        'type' => $element['#type'],
        // Core's machine_name element carries its label in #machine_name
        // rather than in #title.
        'title' => $element['#title'] ?? ($element['#type'] === 'machine_name' ? ($element['#machine_name']['label'] ?? $key) : $key),
        'description' => $element['#description'] ?? '',
        'required' => $element['#required'] ?? FALSE,
        'default_value' => $element['#default_value'] ?? '',
        'token_support' => $element['#eca_token_replacement'] ?? FALSE,
      ];

      // Only emit the flag when the element is actually disabled, so the UI
      // never has to tell FALSE apart from absent.
      if (!empty($element['#disabled'])) {
        $field['disabled'] = TRUE;
      }
      if (isset($element['#maxlength'])) {
        $field['maxlength'] = (int) $element['#maxlength'];
      }
      // The machine_name widget derives its value from another field while
      // the user types. Drupal stores that field as an array path; the UI
      // only knows flat field keys, so the last segment is what it needs.
      if ($element['#type'] === 'machine_name' && !empty($element['#machine_name']['source']) && is_array($element['#machine_name']['source'])) {
        $field['source'] = (string) end($element['#machine_name']['source']);
      }

      // For textarea fields, attempt to discover a YAML schema from
      // the Drupal config schema system. Convention: a schema at
      // "yaml.{plugin_schema_key}.{field_key}" defines the structure
      // of the YAML content for this field.
      if ($element['#type'] === 'textarea' && $plugin_schema_key !== '') {
        $yaml_schema = $this->yamlSchemaLookup->lookup($plugin_schema_key, $key);
        if ($yaml_schema !== NULL) {
          $field['yaml_schema'] = $yaml_schema;
        }
      }

      // Remember the last textarea key so that subsequent use_yaml /
      // validate_yaml checkboxes can reference the field they control.
      if ($element['#type'] === 'textarea') {
        $last_textarea_key = $key;
      }

      // When we encounter use_yaml or validate_yaml checkboxes produced
      // by ECA's FormFieldYamlTrait, annotate them with the key of the
      // textarea they belong to so the frontend can link them.
      if ($key === 'use_yaml' && $last_textarea_key !== '') {
        $field['yaml_field'] = $last_textarea_key;
      }
      if ($key === 'validate_yaml' && $last_textarea_key !== '') {
        $field['yaml_field'] = $last_textarea_key;
      }

      // Derive a widget "format" from the field's config-schema contract
      // (e.g. a Json constraint → JSON editor), so the UI stays agnostic of
      // vendor form-element type names.
      $format = $this->deriveFormat($plugin_schema_key, $key);
      if ($format !== NULL) {
        $field['format'] = $format;
      }

      // Extract Drupal #states into a normalized, JSON-friendly structure so
      // the frontend can drive conditional visibility/required behavior.
      $states = $this->extractStates($element);
      if ($states !== NULL) {
        $field['states'] = $states;
      }

      // Handle select/options elements.
      if (isset($element['#options'])) {
        $options = $element['#options'];
        // For select elements, let PHP own the empty-option decision and its
        // label (separation of concerns): the UI just renders what we emit.
        if ($element['#type'] === 'select') {
          $empty_option = $this->extractEmptyOption($element, $options);
          if ($empty_option !== NULL) {
            $field['empty_option'] = $empty_option;
          }
        }
        $field['options'] = $options;
      }

      // Handle specific field types.
      switch ($element['#type']) {
        case 'checkbox':
          $field['default_value'] = (bool) ($element['#default_value'] ?? FALSE);
          break;

        case 'number':
          $field['min'] = $element['#min'] ?? NULL;
          $field['max'] = $element['#max'] ?? NULL;
          $field['step'] = $element['#step'] ?? NULL;
          break;
      }

      return $field;
    }
    return NULL;
  }

  /**
   * Extract and normalize a Drupal element's #states into a JSON structure.
   *
   * Drupal #states look like:
   * @code
   *   ['visible'  => [':input[name="use_yaml"]' => ['checked' => TRUE]]]
   *   ['invisible' => [':input[name="mode"]'     => ['value'   => 'advanced']]]
   *   ['required' => [':input[name="enabled"]'   => ['checked' => TRUE]]]
   * @endcode
   *
   * The selector ":input[name=\"KEY\"]" (and the "KEY[subkey]" array-name form)
   * is simplified to the bare field key "KEY". Each Drupal condition object is
   * mapped to a normalized condition supporting the common keys "value",
   * "checked" and "empty".
   *
   * Each state type is normalized to a list of condition GROUPS. Groups are
   * OR'd together (any group matching satisfies the state) and conditions
   * within a group are AND'd (all must match). This mirrors Drupal semantics:
   *   - Multiple selectors within one state type combine with logical AND
   *     (one group carrying all their conditions).
   *   - A selector mapped to a numerically-indexed list of condition arrays
   *     (the "OR form") means "match ANY", producing one OR alternative per
   *     entry.
   *   - A "value" that is itself an array of scalars means "equals any listed
   *     value", producing one OR alternative per value.
   * When AND and OR forms mix within one state type, the alternatives combine
   * as the Cartesian product: A AND (B1 OR B2) = (A AND B1) OR (A AND B2).
   *
   * @param array $element
   *   The Drupal form element.
   *
   * @return array|null
   *   A normalized states structure keyed by state type (visible, invisible,
   *   required, optional). Each value is a list of OR groups, and each group
   *   is a list of AND conditions. Returns NULL when the element declares no
   *   usable #states.
   */
  protected function extractStates(array $element): ?array {
    if (empty($element['#states']) || !is_array($element['#states'])) {
      return NULL;
    }

    $supported = ['visible', 'invisible', 'required', 'optional'];
    $states = [];

    foreach ($element['#states'] as $state_type => $selectors) {
      if (!in_array($state_type, $supported, TRUE) || !is_array($selectors)) {
        continue;
      }
      // Each selector contributes a list of OR alternatives (one or more
      // normalized conditions). Selectors AND together, so we build the
      // Cartesian product of their alternatives into a list of OR groups.
      $groups = [[]];
      foreach ($selectors as $selector => $condition) {
        if (!is_string($selector) || !is_array($condition)) {
          continue;
        }
        $field_key = $this->simplifySelector($selector);
        if ($field_key === NULL) {
          continue;
        }
        $alternatives = $this->normalizeSelectorConditions($field_key, $condition);
        if ($alternatives === []) {
          continue;
        }
        $expanded = [];
        foreach ($groups as $group) {
          foreach ($alternatives as $alternative) {
            $expanded[] = array_merge($group, [$alternative]);
          }
        }
        $groups = $expanded;
      }
      // Drop the seed empty group if no usable conditions were collected.
      if ($groups === [[]]) {
        continue;
      }
      $states[$state_type] = $groups;
    }

    return $states === [] ? NULL : $states;
  }

  /**
   * Normalize a single selector's condition into a list of OR alternatives.
   *
   * Handles the simple associative form (`['value' => 'x']`), the Drupal
   * OR form (a numerically-indexed list of condition arrays), and the
   * array-value form (`['value' => ['a', 'b']]`, meaning "equals any").
   *
   * @param string $field_key
   *   The simplified field key the condition observes.
   * @param array $condition
   *   The raw Drupal condition array for one selector.
   *
   * @return array
   *   A list of normalized condition arrays representing OR alternatives.
   *   Empty when the condition carries no supported keys.
   */
  protected function normalizeSelectorConditions(string $field_key, array $condition): array {
    // OR form: a numerically-indexed list of condition arrays under one
    // selector. Each entry becomes its own OR alternative.
    if (array_is_list($condition) && $condition !== []) {
      $alternatives = [];
      foreach ($condition as $entry) {
        if (is_array($entry)) {
          $alternatives = array_merge($alternatives, $this->normalizeSelectorConditions($field_key, $entry));
        }
      }
      return $alternatives;
    }

    // Array-value form: "value" is a list of scalars ("equals any"). Expand
    // to one OR alternative per value.
    if (array_key_exists('value', $condition) && is_array($condition['value'])) {
      $alternatives = [];
      foreach ($condition['value'] as $value) {
        $alternatives[] = ['field' => $field_key, 'value' => $value];
      }
      return $alternatives;
    }

    // Simple associative form: a single condition supporting value / checked /
    // empty.
    $normalized = ['field' => $field_key];
    $has_key = FALSE;
    if (array_key_exists('value', $condition)) {
      $normalized['value'] = $condition['value'];
      $has_key = TRUE;
    }
    if (array_key_exists('checked', $condition)) {
      $normalized['checked'] = (bool) $condition['checked'];
      $has_key = TRUE;
    }
    if (array_key_exists('empty', $condition)) {
      $normalized['empty'] = (bool) $condition['empty'];
      $has_key = TRUE;
    }
    return $has_key ? [$normalized] : [];
  }

  /**
   * Simplify a Drupal #states selector to a bare field key.
   *
   * Handles ":input[name=\"KEY\"]" and the array-name form
   * ":input[name=\"KEY[subkey]\"]", reducing both to the first segment "KEY".
   *
   * @param string $selector
   *   The Drupal #states selector.
   *
   * @return string|null
   *   The simplified field key, or NULL when the selector cannot be parsed.
   */
  protected function simplifySelector(string $selector): ?string {
    if (!preg_match('/name="([^"\\[\\]]+)/', $selector, $matches)) {
      return NULL;
    }
    return $matches[1];
  }

  /**
   * Determine the empty option (placeholder) for a select element.
   *
   * PHP owns both the DECISION (whether an empty option applies) and its
   * LABEL, mirroring Drupal core's Select::processSelect() rules, so the UI
   * only has to render what is emitted. Because the converter receives a
   * post-process form, core has usually already prepended the empty option
   * into #options. This method handles both situations:
   *   - Promotion: when #options already contains the empty entry, capture it
   *     into a structured empty_option and REMOVE it from the emitted options
   *     so options carry only real choices.
   *   - Synthesis (belt-and-suspenders): when the select did NOT go through
   *     processSelect (e.g. built outside the form pipeline) but core's rule
   *     says an empty option applies, synthesize one with a translated default
   *     label.
   *
   * Core's rule (non-multiple selects only): an empty option applies when
   *   (#required && !isset(#default_value)) || isset(#empty_value)
   *   || isset(#empty_option)
   * where #required is treated as TRUE when #states['required'] is set.
   * Multiple selects never get an empty option.
   *
   * @param array $element
   *   The Drupal select form element.
   * @param array &$options
   *   The options array to emit (modified in place: the promoted empty entry
   *   is removed).
   *
   * @return array|null
   *   An ['value' => string, 'label' => string] structure when an empty option
   *   applies, or NULL when none does.
   */
  protected function extractEmptyOption(array $element, array &$options): ?array {
    // Multiple selects never get an empty option.
    if (!empty($element['#multiple'])) {
      return NULL;
    }

    $empty_value = $element['#empty_value'] ?? '';
    $empty_value_string = (string) $empty_value;

    // #required is treated as TRUE when #states['required'] is set, mirroring
    // core's FormElementBase::processPattern/Select handling.
    $required = !empty($element['#required']) || isset($element['#states']['required']);

    // Core's rule for whether an empty option applies at all.
    $rule_applies =
      ($required && !isset($element['#default_value']))
      || isset($element['#empty_value'])
      || isset($element['#empty_option']);

    // Promotion path: the post-process form may already contain the empty
    // entry merged into #options. Detect it via a string-cast key compare.
    foreach ($options as $option_key => $option_label) {
      if ((string) $option_key === $empty_value_string && !is_array($option_label)) {
        // Only promote when core's rule says this is genuinely the empty
        // option (avoids mis-promoting a legitimate '' choice that a plugin
        // deliberately added without requesting an empty placeholder).
        if (!$rule_applies) {
          break;
        }
        $label = (string) $option_label;
        unset($options[$option_key]);
        return [
          'value' => $empty_value_string,
          'label' => $label,
        ];
      }
    }

    // Synthesis path: no empty entry present, but core's rule says one
    // applies. Use the explicit #empty_option label when provided, otherwise
    // core's translated defaults ('- Select -' when required, '- None -'
    // otherwise). The PHP layer translates here because the label is data the
    // UI renders verbatim.
    if ($rule_applies) {
      if (isset($element['#empty_option'])) {
        $label = (string) $element['#empty_option'];
      }
      else {
        $label = $required ? (string) $this->t('- Select -') : (string) $this->t('- None -');
      }
      return [
        'value' => $empty_value_string,
        'label' => $label,
      ];
    }

    return NULL;
  }

}
