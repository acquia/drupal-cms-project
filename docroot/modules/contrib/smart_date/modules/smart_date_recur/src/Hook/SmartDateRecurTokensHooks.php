<?php

namespace Drupal\smart_date_recur\Hook;

use Drupal\Component\Utility\DeprecationHelper;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\smart_date_recur\Entity\SmartDateRule;
use Drupal\smart_date\Plugin\Field\FieldType\SmartDateFieldItemList;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\token\TokenEntityMapperInterface;

/**
 * Hook implementations for smart_date_recur.
 */
class SmartDateRecurTokensHooks {
  use StringTranslationTrait;

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly EntityFieldManagerInterface $entityFieldManager,
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
        $tokens[$token_type . '-' . $field_name]['prev'] = [
          'name' => $this->t('Previous Instance'),
          'description' => $this->t('Provide the id of a specific smart date format to use it for formatting.'),
          'dynamic' => TRUE,
          'module' => 'smart_date_recur',
        ];
        $tokens[$token_type . '-' . $field_name]['next'] = [
          'name' => $this->t('Next instance'),
          'description' => $this->t('Provide the id of a specific smart date format to use it for formatting.'),
          'dynamic' => TRUE,
          'module' => 'smart_date_recur',
        ];
        $tokens[$token_type . '-' . $field_name]['rule_text'] = [
          'name' => $this->t('Recurring Rule Text'),
          'description' => NULL,
          'module' => 'smart_date_recur',
        ];
        $tokens[$token_type . '-' . $field_name]['upcoming'] = [
          'name' => $this->t('Display upcoming instances'),
          'description' => $this->t('Specify the number of values to return, or zero to return all. Optionally provide the id of a specific smart date format to use it for formatting.'),
          'dynamic' => TRUE,
          'module' => 'smart_date_recur',
        ];
        $tokens[$token_type . '-' . $field_name]['past'] = [
          'name' => $this->t('Display past instances'),
          'description' => $this->t('Specify the number of values to return, or zero to return all. Optionally provide the id of a specific smart date format to use it for formatting.'),
          'dynamic' => TRUE,
          'module' => 'smart_date_recur',
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
  public static function tokens($type, $tokens, array $data, array $options, BubbleableMetadata $bubbleable_metadata) {
    $replacements = [];
    if (empty($data['field_property'])) {
      return $replacements;
    }
    /** @var \Drupal\Core\Render\RendererInterface $renderer */
    $renderer = \Drupal::service('renderer');
    foreach ($tokens as $token => $original) {
      $list = $data[$data['field_name']];
      if (!$list instanceof SmartDateFieldItemList) {
        continue;
      }
      // Default to the first item in the list.
      $delta = NULL;
      $parts = explode(':', $token, 2);
      // Test for a delta as the first part.
      if (is_numeric($parts[0])) {
        if (count($parts) > 1) {
          // Save the delta and process the next part.
          $delta = $parts[0];
          $parts = explode(':', $parts[1], 2);
        }
        else {
          continue;
        }
      }
      $property_name = $parts[0];
      // Stop here if it's not a recurring property.
      if (!in_array($property_name, [
        'prev',
        'next',
        'rule_text',
        'upcoming',
        'past',
      ])) {
        continue;
      }
      // Now parse out the pieces of the token name.
      if ($property_name == 'rule_text') {
        // If a delta specified, only use that value.
        if ($delta !== NULL) {
          $list = [
            $list->get($delta),
          ];
        }
        $rule_text = [];
        $rrules_processed = [];
        foreach ($list as $field) {
          $rrid = $field?->get('rrule')->getValue();
          if (!$rrid || in_array($rrid, $rrules_processed)) {
            continue;
          }
          $rule = SmartDateRule::load($rrid);
          $rule_text[] = $rule->getTextRule();
          $rrules_processed[] = $rrid;
        }
        // @todo More structure or markup around multiple values?
        if (!class_exists(DeprecationHelper::class)) {
          // @phpstan-ignore-next-line
          $output = DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '10.3.0', fn() => $renderer->renderInIsolation($rule_text), fn() => $renderer->renderPlain($rule_text));
        }
        $output = DeprecationHelper::backwardsCompatibleCall(currentVersion: \Drupal::VERSION, deprecatedVersion: '10.3', currentCallable: fn() => $renderer->renderInIsolation($rule_text), deprecatedCallable: fn() => $renderer->renderPlain($rule_text));
        $replacements[$original] = $output;
        continue;
        // End rule_text token processing.
      }
      // Provide upcoming or past instances.
      // Default to show only one instance.
      $num_to_show = 1;
      if (!empty($parts[1])) {
        $parts = explode(':', $parts[1]);
      }
      else {
        $parts = [];
      }
      // Minimize additional markup in the rendered values.
      $settings = [
        'show_next' => FALSE,
        'add_classes' => FALSE,
        'time_wrapper' => FALSE,
        'current_upcoming' => FALSE,
      ];
      switch ($property_name) {
        case 'upcoming':
        case 'past':
          // If showing multiple and no quantity specified, show 10.
          $num_to_show = count($parts) ? array_shift($parts) : 10;
        case 'prev':
        case 'next':
          $settings['format'] = $parts[0] ?? 'default';
      }
      $recur_manager = \Drupal::service('smart_date_recur.manager');
      // Load the Smart Date Format and merged it into the settings.
      $format = $recur_manager->loadSmartDateFormat($settings['format'] ?? 'default');
      if (is_array($format) && $format) {
        $settings += $format;
      }
      switch ($property_name) {
        case 'prev':
        case 'past':
          $settings['upcoming_display'] = 0;
          $settings['past_display'] = $num_to_show;
          break;

        case 'upcoming':
        case 'next':
          $settings['upcoming_display'] = $num_to_show;
          $settings['past_display'] = 0;
      }
      $elements = [];
      // Key the values by timestamp so they can be sorted.
      foreach ($list as $delta => $item) {
        // Drop any rows with invalid values.
        if (empty($item->value) || empty($item->end_value)) {
          continue;
        }
        // Save the original delta within the item.
        $item->delta = $delta;
        $elements[$item->value] = $item;
      }
      ksort($elements);
      $elements = array_values($elements);
      // Use the Recur Formatter's helper function to find the next instance.
      $next_index = $recur_manager->findNextInstance($elements, $settings);
      // Use the Recur Formatter's helper function to splice out the instances.
      $values = $recur_manager->subsetInstances($elements, $next_index, $settings);
      // For 'prev' and 'next' tokens, extract the value out of the list.
      if (in_array($property_name, [
        'prev',
        'next',
      ])) {
        // Return just the date value.
        if ($property_name == 'next') {
          $extraction_key = '#upcoming_display';
        }
        else {
          $extraction_key = '#past_display';
        }
        $set = $values[$extraction_key]['#items'];
        if (is_array($set)) {
          $values = array_pop($set);
        }
      }
      if (!class_exists(DeprecationHelper::class)) {
        // @phpstan-ignore-next-line
        $output = DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '10.3.0', fn() => $renderer->renderInIsolation($values), fn() => $renderer->renderPlain($values));
      }
      $output = DeprecationHelper::backwardsCompatibleCall(currentVersion: \Drupal::VERSION, deprecatedVersion: '10.3', currentCallable: fn() => $renderer->renderInIsolation($values), deprecatedCallable: fn() => $renderer->renderPlain($values));
      $replacements[$original] = $output;
    }
    return $replacements;
  }

}
