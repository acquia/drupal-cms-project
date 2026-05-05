<?php

declare(strict_types=1);

namespace Drupal\custom_field\Commands;

use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\custom_field\CustomFieldUpdateManagerInterface;
use Drupal\custom_field\Plugin\CustomField\FieldType\DateRangeType;
use Drupal\custom_field\Plugin\CustomField\FieldType\DateTimeType;
use Drupal\custom_field\Plugin\CustomFieldTypeManagerInterface;
use Drupal\field\Entity\FieldStorageConfig;
use Drush\Commands\DrushCommands;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Drush commands for managing custom field column updates.
 */
final class UpdaterCommands extends DrushCommands {

  use StringTranslationTrait;

  /**
   * The custom field update manager service.
   *
   * @var \Drupal\custom_field\CustomFieldUpdateManagerInterface
   */
  private readonly CustomFieldUpdateManagerInterface $updateManager;

  /**
   * The custom field type manager service.
   *
   * @var \Drupal\custom_field\Plugin\CustomFieldTypeManagerInterface
   */
  private readonly CustomFieldTypeManagerInterface $customFieldTypeManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  private readonly EntityTypeManagerInterface $entityTypeManager;

  /**
   * Constructs a new UpdaterCommands object.
   *
   * @param \Drupal\custom_field\CustomFieldUpdateManagerInterface $update_manager
   *   The custom field update manager service.
   * @param \Drupal\custom_field\Plugin\CustomFieldTypeManagerInterface $custom_field_type_manager
   *   The custom field type manager service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   */
  public function __construct(CustomFieldUpdateManagerInterface $update_manager, CustomFieldTypeManagerInterface $custom_field_type_manager, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct();
    $this->updateManager = $update_manager;
    $this->customFieldTypeManager = $custom_field_type_manager;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Add a new column to a custom field.
   *
   * @command custom_field:add-column
   * @usage drush custom_field:add-column
   *   Interactively adds a new column to a custom field.
   * @aliases cf-add-column
   *
   * @throws \Exception
   */
  public function addColumn(): void {
    // Prompt for field storage instance to get the FieldStorageConfig object.
    $field_storage = $this->promptFieldStorage();
    $entity_type_id = $field_storage->getTargetEntityTypeId();
    $field_name = $field_storage->getName();

    // Prompt for the new column name, validating it doesn't already exist.
    $new_column = $this->promptNewProperty($field_storage);
    $data_type = $this->promptDataType();

    // Prompt for column options based on the data type.
    $column_options = $this->promptColumnOptions($data_type);

    // Confirmation.
    $io = new SymfonyStyle($this->input, $this->output);
    $io->section('Summary');

    // Format options as a readable list.
    $options_display = 'None';
    if (!empty($column_options)) {
      $options_list = [];
      foreach ($column_options as $key => $value) {
        $value_display = is_bool($value) ? ($value ? 'TRUE' : 'FALSE') : $value;
        $options_list[] = "$key: $value_display";
      }
      $options_display = "\n  - " . implode("\n  - ", $options_list);
    }

    $io->listing([
      "entity_type_id: $entity_type_id",
      "field_name: $field_name",
      "column: $new_column",
      "data_type: $data_type",
      "options: $options_display",
    ]);

    if (!$io->confirm('Proceed with adding this column?')) {
      $io->warning('Operation cancelled.');
      return;
    }

    // Execute the addColumn method.
    $this->updateManager->addColumn($entity_type_id, $field_name, $new_column, $data_type, $column_options);

    // Process any pending batch operations (for data restoration).
    $this->processBatch();

    $this->io()->success((string) $this->t('Column @column added to @field_name on @entity_type.', [
      '@column' => $new_column,
      '@field_name' => $field_name,
      '@entity_type' => $entity_type_id,
    ]));

    // Output example update hook.
    $this->outputUpdateHookExample('add', [
      'entity_type_id' => $entity_type_id,
      'field_name' => $field_name,
      'column' => $new_column,
      'data_type' => $data_type,
      'column_options' => $column_options,
    ]);
  }

  /**
   * Remove a column from a custom field.
   *
   * @command custom_field:remove-column
   * @usage drush custom_field:remove-column
   *   Interactively removes a column from a custom field.
   * @aliases cf-remove-column
   *
   * @throws \Exception
   */
  public function removeColumn(): void {
    // Prompt for field storage instance to get the FieldStorageConfig object.
    $field_storage = $this->promptFieldStorage();
    $entity_type_id = $field_storage->getTargetEntityTypeId();
    $field_name = $field_storage->getName();

    // Prompt for the column to remove.
    $column = $this->promptColumn($field_storage);

    // Confirmation.
    $io = new SymfonyStyle($this->input, $this->output);
    $io->section('Summary');
    $io->listing([
      "entity_type_id: $entity_type_id",
      "field_name: $field_name",
      "column: $column",
    ]);

    if (!$io->confirm('Proceed with removing this column?')) {
      $io->warning('Operation cancelled.');
      return;
    }

    // Execute the removeColumn method.
    $this->updateManager->removeColumn($entity_type_id, $field_name, $column);

    // Process any pending batch operations (for data restoration).
    $this->processBatch();

    $this->io()->success((string) $this->t('Column @column removed from @field_name on @entity_type.', [
      '@column' => $column,
      '@field_name' => $field_name,
      '@entity_type' => $entity_type_id,
    ]));

    // Output example update hook.
    $this->outputUpdateHookExample('remove', [
      'entity_type_id' => $entity_type_id,
      'field_name' => $field_name,
      'column' => $column,
    ]);
  }

  /**
   * Prompt for the new property name, ensuring it doesn't already exist.
   *
   * @param \Drupal\field\Entity\FieldStorageConfig $field_storage
   *   The field storage configuration object.
   *
   * @return string
   *   The new property name.
   *
   * @throws \RuntimeException
   */
  protected function promptNewProperty(FieldStorageConfig $field_storage): string {
    $columns = $field_storage->getSetting('columns') ?? [];
    $existing_column_names = array_keys($columns);
    $field_name = $field_storage->getName();
    // Calculate a safe max column length to coincide with SQL column limit.
    $max_name_length = 64 - strlen($field_name) - 12;

    // phpcs:ignore Drupal.Semantics.FunctionT.WhiteSpace
    $question = new Question((string) $this->t('Enter new column name (lowercase, max @max_length chars)', ['@max_length' => $max_name_length]));
    $question->setValidator(function ($answer) use ($existing_column_names, $max_name_length) {
      $answer = strtolower(trim($answer));
      if (empty($answer)) {
        throw new \RuntimeException('The new column name is required.');
      }
      if (strlen($answer) > $max_name_length) {
        throw new \RuntimeException(sprintf('The column name cannot exceed %d characters.', $max_name_length));
      }
      if (in_array($answer, $existing_column_names, TRUE)) {
        throw new \RuntimeException("The column name '$answer' already exists in the field storage.");
      }
      // Validate machine name format (alphanumeric and underscores).
      if (!preg_match('/^(?!.*__)[a-zA-Z0-9_]+$/', $answer)) {
        throw new \RuntimeException('The column name must contain only alphanumeric characters and single underscores, no double underscores.');
      }
      return $answer;
    });

    return $this->io()->askQuestion($question);
  }

  /**
   * Prompt for the field storage instance of type 'custom'.
   *
   * @return \Drupal\field\Entity\FieldStorageConfig
   *   The selected field storage configuration object.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function promptFieldStorage(): FieldStorageConfig {
    // Load all field storage instances of type 'custom'.
    /** @var \Drupal\field\FieldStorageConfigInterface[] $field_storage_configs */
    $field_storage_configs = $this->entityTypeManager
      ->getStorage('field_storage_config')
      ->loadByProperties(['type' => 'custom']);

    if (empty($field_storage_configs)) {
      throw new \RuntimeException('No custom field storage instances found.');
    }

    // Prepare table rows: ID, Field Name, Entity Type, Bundles.
    $rows = [];
    $choices = [];
    $index = 0;
    foreach ($field_storage_configs as $field_storage) {
      $entity_type_id = $field_storage->getTargetEntityTypeId();
      $field_name = $field_storage->getName();

      // Get bundles where this field is used.
      /** @var \Drupal\field\FieldConfigInterface[] $field_configs */
      $field_configs = $this->entityTypeManager
        ->getStorage('field_config')
        ->loadByProperties([
          'entity_type' => $entity_type_id,
          'field_name' => $field_name,
        ]);
      $bundles = array_map(function ($field_config) {
        return $field_config->getTargetBundle();
      }, $field_configs);
      $bundles = !empty($bundles) ? implode(', ', $bundles) : 'None';

      $rows[] = [
        $index,
        $field_name,
        $entity_type_id,
        $bundles,
      ];
      $choices[$index] = $field_storage;
      $index++;
    }

    // Display the table.
    $table = new Table($this->output);
    $table
      ->setHeaders(['#', 'Field Name', 'Entity Type', 'Bundles'])
      ->setRows($rows);
    $table->render();

    $prompt = $this->t('Enter the number (#) of the field storage instance');
    $question = new Question((string) $prompt);
    $question->setValidator(function ($answer) use ($choices) {
      if (!isset($choices[$answer])) {
        throw new \RuntimeException('Invalid selection: ' . $answer);
      }
      return $choices[$answer];
    });

    return $this->io()->askQuestion($question);
  }

  /**
   * Prompt for the column to remove from the field storage.
   *
   * @param \Drupal\field\Entity\FieldStorageConfig $field_storage
   *   The field storage configuration object.
   *
   * @return string
   *   The selected column name.
   *
   * @throws \RuntimeException
   */
  protected function promptColumn(FieldStorageConfig $field_storage): string {
    // Get the columns from the field storage settings.
    $columns = $field_storage->getSetting('columns') ?? [];

    if (empty($columns)) {
      throw new \RuntimeException('No columns found for field ' . $field_storage->getName() . '.');
    }
    // Removing the only column left is not allowed.
    elseif (count($columns) === 1) {
      throw new \RuntimeException('The field ' . $field_storage->getName() . ' has only 1 column.');
    }

    // Prepare table rows: ID, Name, Type.
    $rows = [];
    $choices = [];
    $index = 0;
    foreach ($columns as $column_name => $column_info) {
      $rows[] = [
        $index,
        $column_name,
        $column_info['type'] ?? 'Unknown',
      ];
      $choices[$index] = $column_name;
      $index++;
    }

    // Display the table.
    $table = new Table($this->output);
    $table
      ->setHeaders(['#', 'Column Name', 'Type'])
      ->setRows($rows);
    $table->render();

    // phpcs:ignore Drupal.Semantics.FunctionT.WhiteSpace
    $question = new Question((string) $this->t('Enter the number (#) of the column to remove'));
    $question->setValidator(function ($answer) use ($choices) {
      if (!isset($choices[$answer])) {
        throw new \RuntimeException('Invalid selection: ' . $answer);
      }
      return $choices[$answer];
    });

    return $this->io()->askQuestion($question);
  }

  /**
   * Prompt for the data type.
   *
   * @param string|null $data_type
   *   The provided data type, if any.
   *
   * @return string
   *   The prompted data type.
   *
   * @throws \RuntimeException
   */
  protected function promptDataType(?string $data_type = NULL): string {
    if ($data_type) {
      return $data_type;
    }

    $definitions = $this->customFieldTypeManager->getDefinitions();

    if (empty($definitions)) {
      throw new \RuntimeException('No field type plugins found.');
    }

    // Sort definitions alphabetically by ID.
    ksort($definitions);

    // Prepare table rows: ID, Label.
    $rows = [];
    $choices = [];
    $index = 0;
    foreach ($definitions as $id => $definition) {
      $rows[] = [
        $index,
        $id,
        (string) $definition['label'],
      ];
      $choices[$index] = $id;
      $index++;
    }

    // Display the table.
    $table = new Table($this->output);
    $table
      ->setHeaders(['#', 'ID', 'Label'])
      ->setRows($rows);
    $table->render();

    $prompt = $this->t('Enter the number (#) of the field type for the new column');
    $question = new Question((string) $prompt);
    $question->setValidator(function ($answer) use ($choices) {
      if (!isset($choices[$answer])) {
        throw new \RuntimeException('Invalid selection: ' . $answer);
      }
      return $choices[$answer];
    });

    return $this->io()->askQuestion($question);
  }

  /**
   * Prompt for column options based on the data type.
   *
   * Supported options:
   *  - string: max_length (1-255, optional, default 255)
   *  - telephone: max_length (1-256, optional, default 256)
   *  - integer/float: unsigned (boolean, default FALSE), size (tiny, small,
   *    medium, big, normal, optional)
   *  - decimal: unsigned (boolean, default FALSE), precision (1-65, optional,
   *    default 10), scale (0-30, optional, default 2)
   *  - entity_reference: target_type (required)
   *  - datetime: datetime_type (date or datetime, required)
   *
   * @param string $data_type
   *   The selected data type.
   *
   * @return array
   *   The column options array, with NULL values filtered out.
   *
   * @throws \RuntimeException
   */
  protected function promptColumnOptions(string $data_type): array {
    $options = [];

    if (in_array($data_type, ['integer', 'float', 'decimal'])) {
      $prompt = $this->t('Is the field unsigned? (yes/no)');
      $options['unsigned'] = $this->promptBoolean(
        (string) $prompt,
        FALSE,
      );
    }
    switch ($data_type) {
      case 'string':
        $prompt = $this->t('Enter max length for string (1-255, optional, default 255)');
        $max_length = $this->promptNumeric(
          (string) $prompt,
          1,
          255,
          FALSE,
          255
        );
        $options['length'] = $max_length;
        break;

      case 'telephone':
        $prompt = $this->t('Enter max length for telephone (1-256, optional, default 256)');
        $max_length = $this->promptNumeric(
          (string) $prompt,
          1,
          256,
          FALSE,
          256
        );
        $options['length'] = $max_length;
        break;

      case 'integer':
      case 'float':
        $prompt = $this->t('Select size (optional, press Enter to skip)');
        $options['size'] = $this->promptChoice(
          (string) $prompt,
          ['tiny', 'small', 'medium', 'big', 'normal'],
          'normal'
        );
        break;

      case 'decimal':
        $prompt_precision = $this->t('Enter precision for decimal (10-32, optional, default 10)');
        $options['precision'] = $this->promptNumeric(
          (string) $prompt_precision,
          10,
          32,
          FALSE,
          10
        );
        $prompt_scale = $this->t('Enter scale for decimal (0-10, optional, default 2)');
        $options['scale'] = $this->promptNumeric(
          (string) $prompt_scale,
          0,
          10,
          FALSE,
          2
        );
        break;

      case 'entity_reference':
        $options['target_type'] = $this->promptTargetEntityType();
        break;

      case 'datetime':
      case 'daterange':
        $date_time_types = [
          DateTimeType::DATETIME_TYPE_DATE,
          DateTimeType::DATETIME_TYPE_DATETIME,
        ];
        if ($data_type === 'daterange') {
          $date_time_types[] = DateRangeType::DATETIME_TYPE_ALLDAY;
        }
        $question = new ChoiceQuestion(
          (string) $this->t('Select datetime type'),
          $date_time_types,
          1
        );
        $options['datetime_type'] = $this->io()->askQuestion($question);
        break;
    }

    // Filter out NULL values for optional fields.
    return array_filter($options, function ($value) {
      return $value !== NULL;
    });
  }

  /**
   * Prompt for a boolean value using arrow keys.
   *
   * @param string $prompt
   *   The prompt message.
   * @param bool $default
   *   The default value.
   *
   * @return bool|null
   *   The boolean value, or NULL if optional and skipped.
   *
   * @throws \RuntimeException
   */
  protected function promptBoolean(string $prompt, bool $default): ?bool {
    $choices = ['no', 'yes'];
    $default_index = $default ? 1 : 0;

    $question = new ChoiceQuestion($prompt, $choices, $default_index);
    $question->setValidator(function ($answer) {
      return $answer === 'yes';
    });

    return $this->io()->askQuestion($question);
  }

  /**
   * Prompt for a numeric value within a range.
   *
   * @param string $prompt
   *   The prompt message.
   * @param int $min
   *   The minimum value.
   * @param int $max
   *   The maximum value.
   * @param bool $required
   *   Whether the value is required.
   * @param int|null $default
   *   The default value if provided.
   *
   * @return int|null
   *   The numeric value, or NULL if optional and skipped.
   *
   * @throws \RuntimeException
   */
  protected function promptNumeric(string $prompt, int $min, int $max, bool $required, ?int $default = NULL): ?int {
    $question = new Question($prompt);
    $question->setValidator(function ($answer) use ($min, $max, $required, $default) {
      $answer = trim((string) $answer);
      if (!$required && $answer === '') {
        return $default;
      }
      if (!is_numeric($answer) || $answer < $min || $answer > $max) {
        throw new \RuntimeException(sprintf('Please enter a number between %d and %d.', $min, $max));
      }
      return (int) $answer;
    });

    return $this->io()->askQuestion($question);
  }

  /**
   * Prompt for a choice from a list of options using arrow keys.
   *
   * @param string $prompt
   *   The prompt message.
   * @param array $options
   *   The list of valid options.
   * @param string|null $default
   *   The default option, or NULL if optional.
   *
   * @return string|null
   *   The selected option, or NULL if optional and skipped.
   *
   * @throws \RuntimeException
   */
  protected function promptChoice(string $prompt, array $options, ?string $default): ?string {
    // If optional and no default, add a "Skip" option.
    $choices = $options;
    if ($default === NULL) {
      $choices[] = 'Skip';
    }

    $question = new ChoiceQuestion($prompt, $choices, $default);
    $question->setValidator(function ($answer) use ($options, $default) {
      if ($default === NULL && $answer === 'Skip') {
        return NULL;
      }
      if (!in_array($answer, $options, TRUE)) {
        throw new \RuntimeException('Invalid selection.');
      }
      return $answer;
    });

    return $this->io()->askQuestion($question);
  }

  /**
   * Prompt for the target entity type.
   *
   * @return string
   *   The selected entity type ID.
   *
   * @throws \RuntimeException
   */
  protected function promptTargetEntityType(): string {
    // Get all entity type definitions.
    $entity_type_definitions = $this->entityTypeManager->getDefinitions();

    if (empty($entity_type_definitions)) {
      throw new \RuntimeException('No entity types found.');
    }

    // Separate content and configuration entity types.
    $content_entities = [];
    $config_entities = [];
    foreach ($entity_type_definitions as $id => $definition) {
      if ($definition->entityClassImplements(ContentEntityInterface::class)) {
        $content_entities[$id] = $definition;
      }
      elseif ($definition->entityClassImplements(ConfigEntityInterface::class)) {
        $config_entities[$id] = $definition;
      }
    }

    // Sort each group alphabetically by label.
    uasort($content_entities, function ($a, $b) {
      return strcmp((string) $a->getLabel(), (string) $b->getLabel());
    });
    uasort($config_entities, function ($a, $b) {
      return strcmp((string) $a->getLabel(), (string) $b->getLabel());
    });

    // Prepare table rows: ID, Label.
    $rows = [];
    $choices = [];
    $index = 0;

    // Add content entities first.
    if (!empty($content_entities)) {
      $rows[] = ['Content Entities', '', ''];
      foreach ($content_entities as $id => $definition) {
        $rows[] = [
          $index,
          $id,
          (string) $definition->getLabel(),
        ];
        $choices[$index] = $id;
        $index++;
      }
    }

    // Add configuration entities.
    if (!empty($config_entities)) {
      $rows[] = ['Configuration Entities', '', ''];
      foreach ($config_entities as $id => $definition) {
        $rows[] = [
          $index,
          $id,
          (string) $definition->getLabel(),
        ];
        $choices[$index] = $id;
        $index++;
      }
    }

    // Display the table.
    $io = new SymfonyStyle($this->input, $this->output);
    $io->title((string) $this->t('Available Target Entity Types'));
    $table = new Table($this->output);
    $table
      ->setHeaders(['#', 'ID', 'Label'])
      ->setRows($rows);
    $table->render();

    // phpcs:ignore Drupal.Semantics.FunctionT.WhiteSpace
    $question = new Question((string) $this->t('Enter the number (#) of the target entity type: '));
    $question->setValidator(function ($answer) use ($choices) {
      if (!isset($choices[$answer])) {
        throw new \RuntimeException('Invalid selection: ' . $answer);
      }
      return $choices[$answer];
    });

    return $this->io()->askQuestion($question);
  }

  /**
   * Process any pending batch operations.
   *
   * @throws \Exception
   *   If the batch process fails.
   */
  protected function processBatch(): void {
    // Check if a batch is set.
    $batch = batch_get();
    if ($batch) {
      // Process the batch using Drush.
      $batch_result = drush_backend_batch_process();
      if ($batch_result === FALSE) {
        throw new \Exception('Failed to process batch for data restoration. Please check the logs for errors.');
      }
      // Clear the batch after processing.
      batch_set([]);
    }
  }

  /**
   * Output an example update hook for the executed command.
   *
   * @param string $operation
   *   The operation ('add' or 'remove').
   * @param array $params
   *   The parameters used in the command.
   */
  protected function outputUpdateHookExample(string $operation, array $params): void {
    $io = $this->io();
    $io->section('For deploying this update to other environments, copy the example update hook to your custom module .install file. Replace the module name (MY_MODULE) and sequence (N) accordingly.');

    $hook_name = 'MY_MODULE_update_N';
    $description = $operation === 'add' ?
      "Add column '{$params['column']}' to '{$params['field_name']}' on '{$params['entity_type_id']}'." :
      "Remove column '{$params['column']}' from '{$params['field_name']}' on '{$params['entity_type_id']}'.";
    $action = $operation === 'add' ? 'addColumn' : 'removeColumn';

    // Start the code block.
    $code[] = "/**";
    $code[] = " * $description";
    $code[] = " */";
    $code[] = "function $hook_name() {";
    $code[] = "  \\Drupal::service('custom_field.update_manager')->$action(";
    $code[] = "    '{$params['entity_type_id']}',";
    $code[] = "    '{$params['field_name']}',";
    $code[] = "    '{$params['column']}',";
    if ($operation === 'add') {
      $code[] = "    '{$params['data_type']}',";
      // Format column_options as a PHP array.
      $options_str = !empty($params['column_options']) ? $this->formatArray($params['column_options']) : '';
      if (!empty($options_str)) {
        $code[] = "    $options_str";
      }
    }
    $code[] = "  );";
    $code[] = "}";
    $code[] = "";

    // Output the formatted code block.
    $io->block(implode("\n", $code), NULL, 'fg=green;bg=black', ' ', TRUE);
  }

  /**
   * Format an array as a PHP array string with proper indentation.
   *
   * @param array $array
   *   The array to format.
   * @param int $indent
   *   The number of spaces to indent each level.
   *
   * @return string
   *   The formatted array string.
   */
  protected function formatArray(array $array, int $indent = 4): string {
    if (empty($array)) {
      return '[]';
    }

    $lines = ['['];
    $spaces = str_repeat(' ', $indent);
    foreach ($array as $key => $value) {
      if (is_string($value)) {
        $lines[] = "$spaces'$key' => '$value',";
      }
      elseif (is_bool($value)) {
        $lines[] = "$spaces'$key' => " . ($value ? 'TRUE' : 'FALSE') . ",";
      }
      else {
        $lines[] = "$spaces'$key' => " . var_export($value, TRUE) . ",";
      }
    }
    $lines[] = str_repeat(' ', $indent) . ']';

    return implode("\n", $lines);
  }

}
