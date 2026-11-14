<?php

declare(strict_types=1);

namespace Drupal\custom_field\Hook;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\Html;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Field\FieldTypePluginManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Image\ImageFactory;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Render\Markup;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\Core\Utility\Token;
use Drupal\custom_field\Plugin\CustomField\FieldType\LinkTypeInterface;
use Drupal\custom_field\Plugin\CustomFieldTypeManagerInterface;
use Drupal\token\TokenEntityMapperInterface;
use Drupal\token\TokenModuleProvider;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Implements token hooks for the Custom Field module.
 */
class TokenHooks {

  use StringTranslationTrait;

  /**
   * Constructs a TokenHooks object.
   */
  public function __construct(
    protected ModuleHandlerInterface $moduleHandler,
    protected EntityFieldManagerInterface $entityFieldManager,
    protected EntityRepositoryInterface $entityRepository,
    protected EntityTypeBundleInfoInterface $entityTypeBundleInfo,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected CustomFieldTypeManagerInterface $customFieldTypeManager,
    protected FieldTypePluginManagerInterface $fieldTypePluginManager,
    protected ImageFactory $imageFactory,
    protected RendererInterface $renderer,
    protected Token $token,
    #[Autowire(service: 'token.module_provider')]
    protected ?TokenModuleProvider $tokenModuleProvider,
    #[Autowire(service: 'token.entity_mapper')]
    protected ?TokenEntityMapperInterface $tokenEntityMapper,
  ) {}

  /**
   * Implements hook_token_info().
   */
  #[Hook('token_info')]
  public function tokenInfo(): array {
    $info = [];
    if (!$this->moduleHandler->moduleExists('token')) {
      return $info;
    }
    $type_info = $this->fieldTypePluginManager->getDefinitions();
    $entity_types = $this->entityTypeManager->getDefinitions();

    foreach ($entity_types as $entity_type_id => $entity_type) {
      if (!$entity_type->entityClassImplements(ContentEntityInterface::class)) {
        continue;
      }

      // Make sure a token type exists for this entity.
      $token_type = $this->tokenEntityMapper?->getTokenTypeForEntityType($entity_type_id);
      if (empty($token_type)) {
        continue;
      }

      $field_definitions = $this->entityFieldManager->getFieldStorageDefinitions($entity_type_id);
      foreach ($field_definitions as $field_name => $field) {
        /** @var \Drupal\field\FieldStorageConfigInterface $field */
        // We only care about 'custom' field types.
        if ($field->getType() !== 'custom') {
          continue;
        }

        // Generate a description for the token.
        $labels = $this->tokenFieldLabel($entity_type_id, $field_name);
        $label = \array_shift($labels);
        $params['@type'] = $type_info[$field->getType()]['label'];
        if (!empty($labels)) {
          $params['%labels'] = implode(', ', $labels);
          $description = $this->t('@type field. Also known as %labels.', $params);
        }
        else {
          $description = $this->t('@type field.', $params);
        }

        $cardinality = $field->getCardinality();
        $cardinality = ($cardinality == FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED || $cardinality > 3) ? 3 : $cardinality;
        $field_token_name = $token_type . '-' . $field_name;
        $info['tokens'][$token_type][$field_name] = [
          'name' => Html::escape($label),
          'description' => $description,
          'module' => 'custom_field',
          // For multivalue fields the field token is a list type.
          'type' => $cardinality > 1 ? "list<$field_token_name>" : $field_token_name,
        ];
        // Field token type.
        $info['types'][$field_token_name] = [
          'name' => Html::escape($label),
          'description' => $this->t('@label tokens.', ['@label' => $field->getLabel()]),
          'needs-data' => $field_token_name,
          'module' => 'custom_field',
          'nested' => TRUE,
        ];
        // Account for multi-value custom fields.
        if ($cardinality > 1) {
          // Field list token type.
          $info['types']["list<$field_token_name>"] = [
            'name' => $this->t('List of @type values', ['@type' => Html::escape($label)]),
            'description' => $this->t('Tokens for lists of @type values.', ['@type' => Html::escape($label)]),
            'needs-data' => "list<$field_token_name>",
            'module' => 'custom_field',
            'nested' => TRUE,
          ];
          // Show a different token for each field delta.
          for ($delta = 0; $delta < $cardinality; $delta++) {
            $info['tokens']["list<$field_token_name>"][$delta] = [
              'name' => $this->t('@type type with delta @delta', ['@type' => Html::escape($label), '@delta' => $delta]),
              'module' => 'custom_field',
              'type' => $field_token_name,
            ];
          }
        }

        // Add subfield tokens.
        $settings = $field->getSettings();
        $custom_fields = $this->customFieldTypeManager->getCustomFieldItems($settings);
        foreach ($custom_fields as $name => $custom_field) {
          $label = $custom_field->getLabel();
          $type = $custom_field->getDataType();
          $subfield_token_name = $field_token_name . '-' . $name;
          // Subfield token type.
          $info['types'][$subfield_token_name] = [
            'name' => $this->t('@label', ['@label' => $label]),
            'needs-data' => $subfield_token_name,
            'module' => 'custom_field',
            'nested' => TRUE,
          ];
          // Define tokens for subfield values.
          $info['tokens'][$field_token_name][$name] = [
            'name' => $this->t('@label', ['@label' => $label]),
            'description' => $this->t('The %label subfield.', ['%label' => $label]),
            'type' => $subfield_token_name,
          ];
          // The field label token.
          $info['tokens'][$subfield_token_name]['field_label'] = [
            'name' => $this->t('Field label'),
            'description' => $this->t('The field label of the subfield.'),
          ];
          // Handle specific data types.
          if ($type === 'entity_reference' && $target_type = $custom_field->getTargetType()) {
            // @phpstan-ignore-next-line nullsafe.neverNull
            $entity_token_type = $this->tokenEntityMapper?->getTokenTypeForEntityType($target_type);
            if ($entity_token_type) {
              $info['tokens'][$subfield_token_name]['entity'] = [
                'name' => $this->t('Referenced entity'),
                'description' => $this->t('The referenced entity.'),
                'type' => $entity_token_type,
                'nested' => TRUE,
              ];
            }
          }
          if ($type === 'image') {
            $info['tokens'][$subfield_token_name]['alt'] = [
              'name' => $this->t('Alternative text'),
              'description' => $this->t("Alternative image text, for the image's 'alt' attribute."),
            ];
            $info['tokens'][$subfield_token_name]['title'] = [
              'name' => $this->t('Title'),
              'description' => $this->t("Image title text, for the image's 'title' attribute."),
            ];
            $info['tokens'][$subfield_token_name]['height'] = [
              'name' => $this->t('Height'),
              'description' => $this->t('The height of the image in pixels.'),
            ];
            $info['tokens'][$subfield_token_name]['width'] = [
              'name' => $this->t('Width'),
              'description' => $this->t('The width of the image in pixels.'),
            ];
            $info['tokens'][$subfield_token_name]['entity'] = [
              'name' => $this->t('File'),
              'description' => $this->t('The referenced entity'),
              'type' => 'file',
              'nested' => TRUE,
            ];
            $image_styles = image_style_options(FALSE);
            foreach ($image_styles as $style => $description) {
              $info['tokens'][$subfield_token_name][$style] = [
                'name' => $description,
                'description' => $this->t('Represents the image in the given image style.'),
                'type' => 'image_with_image_style',
              ];
            }
          }
          if ($type === 'file') {
            $info['tokens'][$subfield_token_name]['entity'] = [
              'name' => $this->t('File'),
              'description' => $this->t('The referenced entity'),
              'type' => 'file',
              'nested' => TRUE,
            ];
          }
          if ($type === 'datetime') {
            $info['tokens'][$subfield_token_name]['formatted'] = [
              'name' => $this->t('Formatted date'),
              'description' => $this->t('The formatted datetime value.'),
              'type' => 'date',
              'nested' => TRUE,
            ];
          }
          if ($type === 'daterange') {
            $info['tokens'][$field_token_name][$name]['nested'] = TRUE;
            $info['tokens'][$field_token_name][$name]['description'] = $this->t('Date range field');
            $info['tokens'][$subfield_token_name]['value'] = [
              'name' => $this->t('Start date value'),
              'description' => $this->t('The raw value of the start date.'),
            ];
            $info['tokens'][$subfield_token_name]['start_date'] = [
              'name' => $this->t('Start date format'),
              'description' => $this->t('The formatted datetime value.'),
              'type' => 'date',
              'nested' => TRUE,
            ];
            $info['tokens'][$subfield_token_name]['end_value'] = [
              'name' => $this->t('End date value'),
              'description' => $this->t('The raw value of the end date.'),
            ];
            $info['tokens'][$subfield_token_name]['end_date'] = [
              'name' => $this->t('End date format'),
              'description' => $this->t('The formatted datetime value.'),
              'type' => 'date',
              'nested' => TRUE,
            ];
            $info['tokens'][$subfield_token_name]['duration'] = [
              'name' => $this->t('Duration'),
              'description' => $this->t('The total duration in seconds between the start and end dates.'),
            ];
          }
          if ($type === 'time_range') {
            $info['tokens'][$field_token_name][$name]['description'] = $this->t('Time range field');
            $info['tokens'][$subfield_token_name]['value'] = [
              'name' => $this->t('Start time value'),
              'description' => $this->t('The raw value of the start time.'),
            ];
            $info['tokens'][$subfield_token_name]['end_value'] = [
              'name' => $this->t('End time value'),
              'description' => $this->t('The raw value of the end time.'),
            ];
            $info['tokens'][$subfield_token_name]['duration'] = [
              'name' => $this->t('Duration'),
              'description' => $this->t('The total duration in seconds between the start and end times.'),
            ];
          }
          else {
            $info['tokens'][$subfield_token_name]['value'] = [
              'name' => $this->t('Value'),
              'description' => $this->t('The raw value of the subfield.'),
            ];
          }
          if ($type === 'link') {
            $info['tokens'][$subfield_token_name]['title'] = [
              'name' => $this->t('Link text'),
              'description' => $this->t('The link title text.'),
            ];
          }
          if (\in_array($type, ['uri', 'link'])) {
            $info['tokens'][$subfield_token_name]['value']['name'] = $this->t('URI');
            $info['tokens'][$subfield_token_name]['value']['description'] = $this->t('The URI of the link.');
            // Add URL tokens.
            $info['tokens'][$subfield_token_name]['url'] = $info['tokens'][$subfield_token_name]['value'];
            $info['tokens'][$subfield_token_name]['url']['name'] = $this->t('URL');
            $info['tokens'][$subfield_token_name]['url']['description'] = $this->t('The URL of the link.');
            $info['tokens'][$subfield_token_name]['url']['type'] = 'url';
          }
          // Add a list label token for fields that allow it.
          if (\in_array($type, ['string', 'integer', 'float'])) {
            $info['tokens'][$subfield_token_name]['label'] = [
              'name' => $this->t('Label'),
              'description' => $this->t('The label from widget settings allowed values (if applicable).'),
            ];
          }
        }

      }
    }

    return $info;
  }

  /**
   * Implements hook_tokens().
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  #[Hook('tokens')]
  public function tokens(string $type, array $tokens, array $data, array $options, BubbleableMetadata $bubbleable_metadata): array {
    $replacements = [];
    if (!$this->moduleHandler->moduleExists('token')) {
      return $replacements;
    }
    $langcode = $options['langcode'] ?? NULL;

    // Handle entity tokens.
    if ($type == 'entity' && !empty($data['entity_type']) && !empty($data['entity']) && !empty($data['token_type'])) {
      /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
      $entity = $data['entity'];
      if (!($entity instanceof ContentEntityInterface)) {
        return $replacements;
      }

      if (!isset($options['langcode'])) {
        // Set the active language so that it is passed along.
        $langcode = $options['langcode'] = $entity->language()->getId();
      }

      // Obtain the entity with the correct language.
      $entity = $this->entityRepository->getTranslationFromContext($entity, $langcode);

      foreach ($tokens as $name => $original) {
        $delta = NULL;
        $property_name = NULL;
        $field_name = $name;
        // For the [entity:field_name] token.
        if (str_contains($name, ':')) {
          $parts = explode(':', $name);
          $field_name = $parts[0];
          $next_part = $parts[1] ?? NULL;
          if (is_numeric($next_part)) {
            $delta = (int) $next_part;
            $property_name = $parts[2] ?? NULL;
          }
          else {
            $property_name = $next_part;
          }
        }

        if ($this->tokenModuleProvider?->getTokenModule($data['token_type'], $field_name) != 'custom_field') {
          continue;
        }

        // Skip tokens not for this field.
        if (!$entity->hasField($field_name) || $entity->get($field_name)->isEmpty()) {
          continue;
        }

        $display_options = 'token';
        // Handle [entity:field_name] and [entity:field_name:0] tokens.
        if ($field_name === $name && !isset($property_name) || (isset($delta) && !isset($property_name))) {
          $view_display = $this->getTokenViewDisplay($entity);
          if (!$view_display) {
            // We don't have the token view display and should fall back on
            // default formatters. If the field has specified a formatter to be
            // used by default with tokens, use that, otherwise use the
            // default formatter.
            $field_type_definition = $this->fieldTypePluginManager->getDefinition($entity->getFieldDefinition($field_name)->getType());
            if (empty($field_type_definition['default_token_formatter']) && empty($field_type_definition['default_formatter'])) {
              continue;
            }
            $display_options = [
              'type' => !empty($field_type_definition['default_token_formatter']) ? $field_type_definition['default_token_formatter'] : $field_type_definition['default_formatter'],
              'label' => 'hidden',
            ];
          }

          // Render only one delta.
          if (isset($delta)) {
            if ($field_delta = $entity->{$field_name}[$delta]) {
              $field_output = $field_delta->view($display_options);
            }
            // If no such delta exists, let's not replace the token.
            else {
              continue;
            }
          }
          // Render the whole field (with all deltas).
          else {
            $field_output = $entity->{$field_name}->view($display_options);
            // If we are displaying all field items we need this #pre_render
            // callback.
            $field_output['#pre_render'][] = '\Drupal\token\TokenFieldRender::preRender';
          }
          $field_output['#token_options'] = $options;
          $replacements[$original] = $this->renderer->renderInIsolation($field_output);
        }
        // Handle [entity:field_name:value] and [entity:field_name:0:value]
        // tokens.
        elseif ($field_tokens = $this->token->findWithPrefix($tokens, $field_name)) {
          // With multiple nested tokens for the same field name, this might
          // match the same field multiple times. Filter out those that have
          // already been replaced.
          $field_tokens = \array_filter($field_tokens, function ($token) use ($replacements) {
            return !isset($replacements[$token]);
          });

          if (empty($field_tokens)) {
            continue;
          }

          // Retrieve field items and handle deltas.
          $field_items = $entity->get($field_name);
          if ($field_items->isEmpty()) {
            continue;
          }
          /** @var \Drupal\custom_field\Plugin\Field\FieldType\CustomItem $item */
          $item = isset($delta) ? ($field_items[$delta] ?? NULL) : $field_items->first();
          if (!$item) {
            continue;
          }

          /** @var array<string|int, mixed> $nested_array */
          $nested_array = [];
          // Process the tokens into a structured array by delta.
          foreach ($field_tokens as $key => $value) {
            $parts = explode(':', (string) $key);
            $current = &$nested_array;
            // Traverse all parts except the last one to build the nested
            // structure.
            for ($i = 0; $i < count($parts) - 1; $i++) {
              $part = $parts[$i];
              // Ensure the current level is an array.
              if (!isset($current[$part]) || !\is_array($current[$part])) {
                $current[$part] = [];
              }
              $current = &$current[$part];
            }
            // Assign the value at the deepest level.
            $last_part = end($parts);
            $current[$last_part] = $value;

            // Break the reference.
            unset($current);
          }

          // Generate the replacements.
          foreach ($nested_array as $property => $properties) {
            if (is_numeric($property)) {
              $delta = (int) $property;
              $item = $field_items[$delta] ?? NULL;
              if (!$item) {
                continue;
              }

              // Iterate through the nested properties.
              foreach ($properties as $nested_property => $nested_values) {
                $this->processProperty(
                  $nested_property,
                  $nested_values,
                  $item,
                  $langcode,
                  $options,
                  $bubbleable_metadata,
                  $replacements
                );
              }
            }
            else {
              // Handle the top-level property directly.
              $this->processProperty(
                (string) $property,
                $properties,
                $item,
                $langcode,
                $options,
                $bubbleable_metadata,
                $replacements
              );
            }
          }
        }
      }
      // Remove the cloned object from memory.
      unset($entity);
    }

    // Return the result so that we can now use the token.
    return $replacements;
  }

  /**
   * Helper function to get computed property values.
   *
   * @param \Drupal\Core\Field\FieldItemInterface $item
   *   The field item.
   * @param string $property_name
   *   The main property name.
   * @param string $sub_property_name
   *   The appended string that builds the computed property.
   *
   * @return mixed|null
   *   The value or NULL if the property doesn't exist.
   */
  private function getComputedValue(FieldItemInterface $item, string $property_name, string $sub_property_name): mixed {
    $property_string = "{$property_name}__{$sub_property_name}";
    return $item->{$property_string} ?? NULL;
  }

  /**
   * Finds the value corresponding to a specific key in a structured array.
   *
   * @param array<string|int|float, mixed> $array
   *   The array to search in.
   * @param mixed $value
   *   The key to search for. Can be a string, int, or float.
   *
   * @return mixed
   *   The corresponding value if found, or the $value if not found.
   */
  private function findValueByKey(array $array, mixed $value): mixed {
    foreach ($array as $item) {
      if (isset($item['key']) && $item['key'] === $value) {
        return $item['label'] ?? $value;
      }
    }

    return $value;
  }

  /**
   * Processes a property and applies replacements.
   *
   * @param string $property
   *   The property name (e.g., "string", "image").
   * @param mixed $properties
   *   The property values or nested properties.
   * @param \Drupal\Core\Field\FieldItemInterface $item
   *   The field item for the current delta.
   * @param string $langcode
   *   The language code.
   * @param array<string, mixed> $options
   *   The array of tokens options.
   * @param \Drupal\Core\Render\BubbleableMetadata $bubbleable_metadata
   *   The cache metadata.
   * @param array &$replacements
   *   The array to store token replacements.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  private function processProperty(string $property, mixed $properties, FieldItemInterface $item, string $langcode, array $options, BubbleableMetadata $bubbleable_metadata, array &$replacements): void {
    if (!$this->moduleHandler->moduleExists('token')) {
      return;
    }

    $settings = $item->getFieldDefinition()->getSettings();
    $custom_fields = $this->customFieldTypeManager->getCustomFieldItems($settings);
    if ($custom_item = $custom_fields[$property] ?? NULL) {
      $data_type = $custom_item->getDataType();
      $raw_value = $item->{$property};
      $referenced_entity = NULL;
      $link_url = NULL;
      $is_reference = \in_array($data_type, [
        'entity_reference',
        'image',
        'file',
      ]);
      if ($is_reference && $referenced_entity = $item->{$property . '__entity'}) {
        $referenced_entity = $this->entityRepository->getTranslationFromContext($referenced_entity, $langcode);
      }
      if ($custom_item instanceof LinkTypeInterface && !empty($item->{$property})) {
        $link_url = $custom_item->getUrl($item);
      }
      if (\is_array($properties)) {
        foreach ($properties as $sub_property => $property_value) {
          if ($sub_property === 'value') {
            // Convert map types to JSON.
            if (\in_array($data_type, ['map', 'map_string'])) {
              $raw_value = !empty($raw_value) ? Json::encode($raw_value) : NULL;
            }
            $replacements[$property_value] = $raw_value;
          }
          // Account for the daterange | time_range end_value property.
          elseif ($sub_property === 'end_value') {
            $replacements[$property_value] = $item->{$property . '__end'};
          }
          // Account for the daterange | time_range duration property.
          elseif ($sub_property === 'duration') {
            $replacements[$property_value] = $item->{$property . '__duration'};
          }
          elseif ($sub_property === 'field_label') {
            $replacements[$property_value] = $custom_item->getLabel();
          }
          elseif ($sub_property === 'label') {
            $allowed_values = $custom_item->getFieldSetting('allowed_values') ?? [];
            $replacements[$property_value] = $this->findValueByKey($allowed_values, $raw_value);
          }
          elseif ($sub_property === 'entity' && $referenced_entity) {
            $token_type = $this->tokenEntityMapper?->getTokenTypeForEntityType($referenced_entity->getEntityTypeId(), TRUE);
            if (\is_array($property_value)) {
              if ($token_type) {
                $entity_tokens = $this->buildTokenReplacement($property_value);
                $replacements += $this->token->generate($token_type, $entity_tokens, [$token_type => $referenced_entity], $options, $bubbleable_metadata);
              }
            }
            else {
              $replacements[$property_value] = $referenced_entity->label();
            }
          }
          // Image replacements.
          elseif ($data_type === 'image') {
            $image_style_storage = $this->entityTypeManager->getStorage('image_style');
            if (\in_array($sub_property, ['alt', 'title', 'width', 'height'])) {
              $replacement_value = $this->getComputedValue($item, $property, $sub_property);
              $replacements[$property_value] = $replacement_value;
            }
            // Provide image_with_image_style tokens for image fields.
            elseif ($style = $image_style_storage->load($sub_property)) {
              /** @var \Drupal\image\Entity\ImageStyle $style */
              $original_uri = $referenced_entity->getFileUri();
              if (\is_array($property_value)) {
                $image_width = $item->{$property . '__width'} ?? NULL;
                $image_height = $item->{$property . '__height'} ?? NULL;
                foreach ($property_value as $image_property => $image_value) {
                  // Only generate the image derivative if needed.
                  if ($image_property === 'width' || $image_property === 'height') {
                    $dimensions = [
                      'width' => $image_width,
                      'height' => $image_height,
                    ];
                    $style->transformDimensions($dimensions, $original_uri);
                    $replacements[$image_value] = $dimensions[$image_property];
                  }
                  elseif ($image_property === 'uri') {
                    $replacements[$image_value] = $style->buildUri($original_uri);
                  }
                  elseif ($image_property === 'url') {
                    // Encloses the URL in a markup object to prevent HTML
                    // escaping.
                    $replacements[$image_value] = Markup::create($style->buildUrl($original_uri));
                  }
                  else {
                    // Generate the image derivative if it doesn't already
                    // exist.
                    $derivative_uri = $style->buildUri($original_uri);
                    $derivative_exists = TRUE;
                    if (!file_exists($derivative_uri)) {
                      $derivative_exists = $style->createDerivative($original_uri, $derivative_uri);
                    }
                    if ($derivative_exists) {
                      $image = $this->imageFactory->get($derivative_uri);
                      // Provide the replacement.
                      switch ($image_property) {
                        case 'mimetype':
                          $replacements[$image_value] = $image->getMimeType();
                          break;

                        case 'filesize':
                          $replacements[$image_value] = $image->getFileSize();
                          break;
                      }
                    }
                  }
                }
              }
              else {
                // Encloses the URL in a markup object to prevent HTML escaping.
                $replacements[$property_value] = Markup::create($style->buildUrl($original_uri));
              }
            }
          }
          // Datetime & Daterange replacements.
          elseif (\in_array($data_type, ['daterange', 'datetime'])) {
            foreach (['formatted', 'start_date', 'end_date'] as $computed_property) {
              if ($sub_property !== $computed_property) {
                continue;
              }
              // Datetime fields have different token structure.
              if ($sub_property === 'formatted') {
                $computed_property = 'date';
              }
              // Get the computed date object.
              $date = $item->{$property . '__' . $computed_property};
              // If we don't have a valid date, we can't replace anything.
              if (!$date instanceof DrupalDateTime) {
                continue;
              }
              $timestamp = $date->getTimestamp();
              // If no sub-parts to the token, return timestamp.
              if (!\is_array($property_value)) {
                $replacements[$property_value] = $timestamp;
              }
              else {
                if (isset($property_value['custom'])) {
                  if (!\is_array($property_value['custom'])) {
                    // If no sub-parts to the token, return timestamp.
                    $replacements[$property_value['custom']] = $timestamp;
                  }
                  else {
                    // Flatten nested properties for custom formatted date.
                    $property_value = $this->buildTokenReplacement($property_value);
                  }
                }
                $replacements += $this->token->generate('date', $property_value, ['date' => $timestamp], $options, $bubbleable_metadata);
              }
            }
          }

          // Uri & Link replacements.
          elseif ($link_url instanceof Url) {
            // The link title.
            if ($data_type === 'link' && $sub_property === 'title') {
              $replacements[$property_value] = $item->{$property . '__title'};
            }
            if ($sub_property === 'url') {
              if (\is_array($property_value)) {
                $url_tokens = $this->buildTokenReplacement($property_value);
                $replacements += $this->token->generate('url', $url_tokens, ['url' => $link_url], $options, $bubbleable_metadata);
              }
              else {
                $replacements[$property_value] = $link_url->toString();
              }
            }
          }
        }
      }
      // Fallback to the reference label.
      elseif ($referenced_entity) {
        $replacements[$properties] = $referenced_entity->label();
      }
      // Fallback to raw value for everything else.
      else {
        $replacements[$properties] = $raw_value;
      }
    }
  }

  /**
   * Returns the token view display for the given entity if enabled.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   *
   * @return \Drupal\Core\Entity\Display\EntityViewDisplayInterface|null
   *   The view display or null.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  private function getTokenViewDisplay(EntityInterface $entity): ?EntityViewDisplayInterface {
    $view_mode_name = $entity->getEntityTypeId() . '.' . $entity->bundle() . '.token';
    /** @var  \Drupal\Core\Entity\Display\EntityViewDisplayInterface|null $view_display */
    $view_display = $this->entityTypeManager->getStorage('entity_view_display')->load($view_mode_name);
    return ($view_display && $view_display->status()) ? $view_display : NULL;
  }

  /**
   * Returns the label of a certain field.
   *
   * Therefore, it looks up in all bundles to find the most used instance.
   *
   * Based on views_entity_field_label().
   *
   * @param string $entity_type_id
   *   The entity type ID.
   * @param string $field_name
   *   The name of the field.
   *
   * @return array<string|int, mixed>
   *   An array containing the most used label(s) for the field, or an array
   *   with the field name if no label is found.
   *
   * @todo Re-sync this method with views_entity_field_label().
   *
   * @see views_entity_field_label()
   */
  private function tokenFieldLabel(string $entity_type_id, string $field_name): array {
    $labels = [];
    // Count the number of instances per label per field.
    foreach (\array_keys($this->entityTypeBundleInfo->getBundleInfo($entity_type_id)) as $bundle) {
      $bundle_instances = $this->entityFieldManager->getFieldDefinitions($entity_type_id, $bundle);
      if (isset($bundle_instances[$field_name])) {
        $instance = $bundle_instances[$field_name];
        $label = (string) $instance->getLabel();
        $labels[$label] = isset($labels[$label]) ? ++$labels[$label] : 1;
      }
    }

    if (empty($labels)) {
      return [$field_name];
    }

    // Sort the field labels by it most used label and return the labels.
    arsort($labels);
    return \array_keys($labels);
  }

  /**
   * Helper function to recursively build replacements.
   *
   * @param array<string, mixed> $input
   *   An array of token parts.
   *
   * @return array<string, mixed>
   *   The flattened token output.
   */
  private function buildTokenReplacement(array $input): array {
    $result = [];
    foreach ($input as $key => $value) {
      if (\is_array($value)) {
        // Recursively process nested arrays, prefixing the key.
        foreach ($this->buildTokenReplacement($value) as $subKey => $subValue) {
          $result[$key . ':' . $subKey] = $subValue;
        }
      }
      else {
        // Leaf node: add directly to result.
        $result[$key] = $value;
      }
    }

    return $result;
  }

}
