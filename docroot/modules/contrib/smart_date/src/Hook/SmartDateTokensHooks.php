<?php

namespace Drupal\smart_date\Hook;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\smart_date\Entity\SmartDateFormat;
use Drupal\smart_date\Plugin\Field\FieldType\SmartDateFieldItemList;
use Drupal\smart_date\SmartDateManager;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\token\TokenEntityMapperInterface;

/**
 * Hook implementations for smart_date.
 */
class SmartDateTokensHooks {
  use StringTranslationTrait;

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly EntityFieldManagerInterface $entityFieldManager,
    private readonly DateFormatterInterface $dateFormatter,
    private readonly MessengerInterface $messenger,
    private readonly SmartDateManager $smartDateManager,
    private readonly ?TokenEntityMapperInterface $tokenEntityMapper = NULL,
  ) {}

  /**
   * Implements hook_token_info().
   */
  #[Hook('token_info')]
  public function tokenInfo() {
    if ($this->tokenEntityMapper === NULL) {
      return;
    }
    $types = [];
    $tokens = [];
    foreach ($this->entityTypeManager->getDefinitions() as $entity_type_id => $entity_type) {
      if (!$entity_type->entityClassImplements(ContentEntityInterface::class)) {
        continue;
      }
      $token_type = $this->tokenEntityMapper->getTokenTypeForEntityType($entity_type_id);
      if (empty($token_type)) {
        continue;
      }
      // Build property tokens for all smart date fields.
      $fields = $this->entityFieldManager->getFieldStorageDefinitions($entity_type_id);
      foreach ($fields as $field_name => $field) {
        if ($field->getType() != 'smartdate') {
          continue;
        }
        $tokens[$token_type . '-' . $field_name]['value-custom'] = [
          'name' => $this->t('Start, custom format'),
          'description' => NULL,
          'dynamic' => TRUE,
          'module' => 'smart_date',
        ];
        $tokens[$token_type . '-' . $field_name]['end_value-custom'] = [
          'name' => $this->t('End, custom format'),
          'description' => NULL,
          'dynamic' => TRUE,
          'module' => 'smart_date',
        ];
        $tokens[$token_type . '-' . $field_name]['format'] = [
          'name' => $this->t('Formatted by Smart Date Format'),
          'description' => $this->t('Provide the id of a specific smart date format to use it for formatting.'),
          'dynamic' => TRUE,
          'module' => 'smart_date',
        ];
        $tokens[$token_type . '-' . $field_name]['value-format'] = [
          'name' => $this->t('Start, formatted by Smart Date Format'),
          'description' => $this->t('Provide the id of a specific smart date format to use it for formatting.'),
          'dynamic' => TRUE,
          'module' => 'smart_date',
        ];
        $tokens[$token_type . '-' . $field_name]['end_value-format'] = [
          'name' => $this->t('End, formatted by Smart Date Format'),
          'description' => $this->t('Provide the id of a specific smart date format to use it for formatting.'),
          'dynamic' => TRUE,
          'module' => 'smart_date',
        ];
      }
    }
    return [
      'types' => $types,
      'tokens' => $tokens,
    ];
  }

  /**
   * Implements hook_tokens().
   */
  #[Hook('tokens')]
  public function tokens($type, $tokens, array $data, array $options, BubbleableMetadata $bubbleable_metadata) {
    $replacements = [];
    if (empty($data['field_property'])) {
      return $replacements;
    }
    foreach ($tokens as $token => $original) {
      $list = $data[$data['field_name']];
      if (!$list instanceof SmartDateFieldItemList) {
        continue;
      }
      $parts = explode(':', $token, 2);
      // Test for a delta as the first part.
      if (is_numeric($parts[0])) {
        $delta = $parts[0];
        if (count($parts) > 1) {
          $parts = explode(':', $parts[1], 2);
          $property_name = $parts[0];
          $format_value = $parts[1] ?? NULL;
        }
        else {
          continue;
        }
      }
      else {
        $delta = 0;
        $property_name = $parts[0];
        $format_value = $parts[1] ?? NULL;
      }
      // Now parse out the pieces of the token name.
      $name_parts = explode('-', $property_name);
      $approach = array_pop($name_parts);
      $field = $list->get($delta);
      if ($approach == 'custom') {
        if (!$format_value) {
          // This token requires a value, so skip if absent.
          continue;
        }
        // Get the requested property and apply the provided format.
        if ($name_parts && $prop_needed = array_pop($name_parts)) {
          $field_ts = $field->get($prop_needed)->getValue();
          $replacements[$original] = $this->dateFormatter->format($field_ts, '', $format_value);
        }
      }
      elseif ($approach == 'format') {
        if (!$format_value) {
          // Our tokens require a value, so skip if absent.
          $format_value = 'default';
        }
        $format = SmartDateFormat::load($format_value);
        if (!$format) {
          $this->messenger->addError($this->t('Unable to load specified Smart Date format: @format', [
            '@format' => $format_value,
          ]));
          if ($format_value === 'default') {
            return $replacements;
          }
          else {
            $format = SmartDateFormat::load('default');
            if (!$format) {
              $this->messenger->addError($this->t('Unable to load default Smart Date format'));
              return $replacements;
            }
          }
        }
        $settings = $format->getOptions();
        // Apply the specified smart date format.
        // If a property was specified, only use that.
        if ($name_parts && $prop_needed = array_pop($name_parts)) {
          $field_ts = $field->get($prop_needed)->getValue();
          $replacements[$original] = $this->smartDateManager->formatSmartDate($field_ts, $field_ts, $settings, NULL, 'string');
        }
        else {
          $start = $field->get('value')->getValue();
          $end = $field->get('end_value')->getValue();
          $replacements[$original] = $this->smartDateManager->formatSmartDate($start, $end, $settings, NULL, 'string');
        }
      }
    }
    return $replacements;
  }

}
