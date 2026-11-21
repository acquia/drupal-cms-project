<?php

namespace Drupal\bpmn_io\Service;

use Drupal\bpmn_io\Plugin\ModelerApiModeler\BpmnIo;
use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Render\Element;
use Drupal\modeler_api\Api;
use Drupal\modeler_api\Plugin\ModelerApiModelOwner\ModelOwnerInterface;

/**
 * Provides services to prepare components for the bpmn_io modeler.
 */
readonly class PrepareComponents {

  /**
   * Constructs the prepare components service.
   */
  public function __construct(
    protected Api $modelerApi,
    protected LoggerChannelInterface $logger,
    protected ModuleExtensionList $extensions,
  ) {}

  /**
   * Returns all the templates for the modeler UI.
   *
   * This includes templates for events, conditions and actions.
   *
   * @param \Drupal\modeler_api\Plugin\ModelerApiModelOwner\ModelOwnerInterface $owner
   *   The model owner.
   *
   * @return array
   *   The list of all templates.
   */
  public function getTemplates(ModelOwnerInterface $owner): array {
    $templates = [];
    foreach (BpmnIo::SUPPORTED_COMPONENT_TYPES as $componentType => $appliesTo) {
      foreach ($this->modelerApi->availableOwnerComponents($owner, $componentType) as $plugin) {
        $pluginType = $owner->ownerComponentId($componentType);
        $form = $owner->buildConfigurationForm($plugin);
        $docUrl = $owner->pluginDocUrl($plugin, $pluginType);
        $templates[] = $this->properties($plugin, $pluginType, $appliesTo, $form, $docUrl);
      }
    }
    return $templates;
  }

  /**
   * Sanitize config values for the raw XML.
   *
   * @param string $pluginId
   *   The plugin ID.
   * @param string $key
   *   The key of the config value.
   * @param mixed $value
   *   The config value.
   *
   * @return string
   *   The sanitized config value.
   */
  public function sanitizeConfigValue(string $pluginId, string $key, mixed $value): string {
    if (is_array($value)) {
      return json_encode($value);
    }
    if (is_null($value)) {
      return '';
    }
    if (is_object($value) && method_exists($value, '__toString')) {
      return $value->__toString();
    }
    if (!is_string($value)) {
      if (is_bool($value)) {
        return $value ? 'yes' : 'no';
      }
      if (is_scalar($value)) {
        return (string) $value;
      }
      $this->logger->error('Unsupported configuration value type %type for %key in component %component', [
        '%type' => gettype($value),
        '%key' => $key,
        '%component' => $pluginId,
      ]);
      $value = '';
    }
    return $value;
  }

  /**
   * Converts config values to value type of default config.
   *
   * @param array $config
   *   The config values.
   * @param array $defaultConfig
   *   The default config values.
   * @param bool $unsetFalse
   *   Whether boolean FALSE values should be unset or not.
   */
  public function upcastConfiguration(array &$config, array $defaultConfig, bool $unsetFalse = TRUE): void {
    foreach ($config as $key => $value) {
      $defaultConfig['replace_tokens'] = FALSE;
      if (isset($defaultConfig[$key]) && !is_string($defaultConfig[$key])) {
        if (is_bool($defaultConfig[$key])) {
          $config[$key] = mb_strtolower($value) === 'yes';
          if ($unsetFalse && $config[$key] === FALSE) {
            unset($config[$key]);
          }
        }
        elseif (is_array($defaultConfig[$key])) {
          try {
            $config[$key] = json_decode($value, TRUE, 512, JSON_THROW_ON_ERROR);
          }
          catch (\JsonException $e) {
            $config[$key] = explode("\n", $value);
          }
        }
      }
    }
  }

  /**
   * Helper function to build a template for an event, condition or action.
   *
   * @param \Drupal\Component\Plugin\PluginInspectionInterface $plugin
   *   The event, condition or action plugin for which the template should
   *   be build.
   * @param string $pluginType
   *   The string identifying the plugin type, which is one of event, condition
   *   or action.
   * @param string $appliesTo
   *   The string to tell the modeler, to which object type the template will
   *   apply. Valid values are "bpmn:Event", "bpmn:sequenceFlow" or "bpmn:task".
   * @param array $form
   *   An array containing the configuration form of the plugin.
   * @param string|null $docUrl
   *   The documentation URL if it exists, NULL otherwise.
   *
   * @return array
   *   The completed template for BPMN modelers for the given plugin and its
   *   fields.
   */
  protected function properties(PluginInspectionInterface $plugin, string $pluginType, string $appliesTo, array $form, ?string $docUrl): array {
    $properties = [
      [
        'label' => 'Plugin ID',
        'type' => 'Hidden',
        'value' => $plugin->getPluginId(),
        'binding' => [
          'type' => 'camunda:property',
          'name' => 'pluginid',
        ],
      ],
    ];
    $extraDescriptions = [];
    $defaultConfiguration = $plugin instanceof ConfigurableInterface ? $plugin->defaultConfiguration() : [];
    foreach ($this->prepareConfigFields($form, $extraDescriptions, $defaultConfiguration) as $key => $field) {
      $value = $this->sanitizeConfigValue($plugin->getPluginId(), $key, $field['value'] ?? NULL);
      $property = [
        'label' => $field['label'],
        'type' => $field['type'],
        'value' => $value,
        'editable' => $field['editable'] ?? TRUE,
        'binding' => [
          'type' => 'camunda:field',
          'name' => $field['name'],
        ],
      ];
      if (!empty($field['required'])) {
        $property['constraints']['notEmpty'] = TRUE;
      }
      if (isset($field['description']) && !is_array($field['description'])) {
        $property['description'] = (string) $field['description'];
      }
      if (isset($field['extras'])) {
        /* @noinspection SlowArrayOperationsInLoopInspection */
        $property = array_merge_recursive($property, $field['extras']);
      }
      $properties[] = $property;
    }
    $extraDescriptions = array_unique($extraDescriptions);
    $pluginDefinition = $plugin->getPluginDefinition();
    $provider = $pluginDefinition['provider'] ?? 'core';
    $template = [
      'name' => (string) ($pluginDefinition['label'] ?? $pluginDefinition['name'] ?? $plugin->getPluginId()),
      'id' => 'org.drupal.' . $pluginType . '.' . $plugin->getPluginId(),
      'category' => [
        'id' => $provider,
        'name' => $provider === 'core' ? 'Drupal Core' : $this->extensions->getName($provider),
      ],
      'appliesTo' => [$appliesTo],
      'properties' => $properties,
    ];
    if (isset($pluginDefinition['description']) || $extraDescriptions) {
      $template['description'] = strip_tags(($pluginDefinition['description'] ?? '') . ' ' . implode(' ', $extraDescriptions));
    }
    if ($docUrl) {
      $template['documentationRef'] = $docUrl;
    }
    return $template;
  }

  /**
   * Helper function preparing config fields for events, conditions and actions.
   *
   * @param array $form
   *   The array to which the fields should be added.
   * @param array $extraDescriptions
   *   An array receiving all markup "fields" which can be displayed separately
   *   in the UI.
   * @param array $defaultConfiguration
   *   The default configuration for this form.
   *
   * @return array
   *   The prepared config fields.
   */
  protected function prepareConfigFields(array $form, array &$extraDescriptions, array $defaultConfiguration): array {
    $fields = [];
    foreach ($form as $key => $definition) {
      if (!is_array($definition)) {
        continue;
      }
      $children = Element::children($form[$key]);
      if ($children) {
        $childForm = [];
        foreach ($children as $child) {
          $childForm[$child] = $form[$key][$child];
        }
        foreach ($this->prepareConfigFields($childForm, $extraDescriptions, $defaultConfiguration) as $childKey => $childField) {
          $fields[$childKey] = $childField;
        }
      }
      $label = $definition['#title'] ?? $this->convertKeyToLabel($key);
      $description = $definition['#description'] ?? NULL;
      $value = $definition['#default_value'] ?? $defaultConfiguration[$key] ?? NULL;
      $weight = $definition['#weight'] ?? 0;
      $type = 'String';
      $required = $definition['#required'] ?? FALSE;
      // @todo Map to more proper property types of bpmn-js.
      switch ($definition['#type'] ?? 'markup') {

        case 'hidden':
        case 'actions':
          // The modelers can't handle these types, so we ignore them for
          // the templates.
          continue 2;

        case 'item':
        case 'markup':
        case 'container':
          if (isset($definition['#markup'])) {
            $extraDescriptions[] = (string) $definition['#markup'];
          }
          continue 2;

        case 'textarea':
          $value = $value ?? '';
          $type = 'Text';
          break;

        case 'checkbox':
          $value = $value ?? FALSE;
          $fields[$key] = $this->checkbox($key, $label, $weight, $description, $value);
          continue 2;

        case 'checkboxes':
        case 'radios':
        case 'select':
          $value = $value ?? [];
          $fields[$key] = $this->optionsField($key, $label, $weight, $description, form_select_options($definition), $value, $required);
          continue 2;

      }
      if (is_bool($value)) {
        $fields[$key] = $this->checkbox($key, $label, $weight, $description, $value);
        continue;
      }
      $field = [
        'name' => $key,
        'label' => $label,
        'weight' => $weight,
        'type' => $type,
        'value' => $value ?? '',
        'required' => $required,
      ];
      if ($description !== NULL) {
        $field['description'] = $description;
      }
      $fields[$key] = $field;
    }

    return $fields;
  }

  /**
   * Prepares a field with options as a drop-down.
   *
   * @param string $name
   *   The field name.
   * @param string $label
   *   The field label.
   * @param int $weight
   *   The field weight for sorting.
   * @param string|null $description
   *   The optional field description.
   * @param array $options
   *   Key/value list of available options.
   * @param mixed $value
   *   The default value for the field.
   * @param bool $required
   *   The setting, if this field is required to be filled by the user.
   *
   * @return array
   *   Prepared option field.
   */
  protected function optionsField(string $name, string $label, int $weight, ?string $description, array $options, mixed $value, bool $required = FALSE): array {
    $field = [
      'name' => $name,
      'label' => $label,
      'weight' => $weight,
      'type' => 'Dropdown',
      'value' => $value,
      'required' => $required,
      'extras' => [
        'choices' => [],
      ],
    ];
    if ($description !== NULL) {
      $field['description'] = $description;
    }
    return $field;
  }

  /**
   * Prepares a field as a checkbox.
   *
   * @param string $name
   *   The field name.
   * @param string $label
   *   The field label.
   * @param int $weight
   *   The field weight for sorting.
   * @param string|null $description
   *   The optional field description.
   * @param bool $value
   *   The default value for the field.
   *
   * @return array
   *   Prepared checkbox field.
   */
  protected function checkbox(string $name, string $label, int $weight, ?string $description, bool $value): array {
    $field = [
      'name' => $name,
      'label' => $label,
      'weight' => $weight,
      'type' => 'Dropdown',
      'value' => $value,
      'extras' => [
        'choices' => [],
      ],
    ];
    if ($description !== NULL) {
      $field['description'] = $description;
    }
    return $field;
  }

  /**
   * Builds a field label from the key.
   *
   * @param string $key
   *   The key of the field from which to build a label.
   *
   * @return string
   *   The built label for the field identified by key.
   */
  protected function convertKeyToLabel(string $key): string {
    $labelParts = explode('_', $key);
    $labelParts[0] = ucfirst($labelParts[0]);
    return implode(' ', $labelParts);
  }

}
