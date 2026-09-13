<?php

namespace Drupal\eca_migrate\Plugin\migrate\process;

use Drupal\eca\Event\TriggerEvent;
use Drupal\migrate\Attribute\MigrateProcess;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;

/**
 * Dispatches an eca event during migration processing.
 *
 * The eca process plugin is used to transform data using an ECA model.
 * The source must be of scalar types, entities, stringable or
 * typed data objects.
 *
 * Available configuration keys:
 * - source: The input value.
 * - multiple: (optional) Whether the returned value is a list of separate
 *   values rather than a single (possibly composite) value. When set to TRUE,
 *   subsequent process plugins in the pipeline that do not handle multiples
 *   themselves are applied to each element individually. Defaults to FALSE.
 *   Note that this only affects downstream plugins; when the eca plugin is the
 *   last step in the pipeline, the whole returned value is written to the
 *   destination regardless of this setting.
 *
 * Examples:
 *
 * @code
 *   process:
 *     new_text_field:
 *       plugin: eca
 *       source: some_text_field
 * @endcode
 *
 * An example applying a per-element transformation to a multi-value result:
 *
 * @code
 *   process:
 *     field_tags:
 *       -
 *         plugin: eca
 *         source: raw_tags
 *         multiple: true
 *       -
 *         plugin: callback
 *         callable: trim
 * @endcode
 *
 * If the ECA model can not be triggered, then the plugin will
 * return the untransformed source value.
 *
 * @see \Drupal\eca\Plugin\DataType\DataTransferObject
 * @see \Drupal\migrate\Plugin\MigrateProcessInterface
 */
#[MigrateProcess(
  id: "eca",
  handle_multiples: TRUE,
)]
class Eca extends ProcessPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The event dispatcher.
   *
   * @var \Drupal\eca\Event\TriggerEvent
   */
  protected TriggerEvent $eventDispatcher;

  /**
   * The current migration.
   *
   * @var \Drupal\migrate\Plugin\MigrationInterface|null
   */
  protected ?MigrationInterface $migration;

  /**
   * Constructs the ECA plugin.
   */
  final public function __construct(array $configuration, $plugin_id, $plugin_definition, TriggerEvent $event_dispatcher, ?MigrationInterface $migration = NULL) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->eventDispatcher = $event_dispatcher;
    $this->migration = $migration;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition, ?MigrationInterface $migration = NULL) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('eca.trigger_event'),
      $migration
    );
  }

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $migration_id = $this->migration?->id() ?? '';

    /** @var \Drupal\eca_migrate\Event\EcaMigrateProcessEvent|null $event */
    $event = $this->eventDispatcher->dispatchFromPlugin(
      'migrate:process',
      $value,
      $row,
      $destination_property,
      $migration_id,
    );

    return $event ? $event->getValue() : $value;
  }

  /**
   * {@inheritdoc}
   */
  public function multiple() {
    // Reflect the author-declared "multiple" config key as-is: the returned
    // value is only treated as a list of separate values when the migration
    // explicitly opts in. The value itself is never reshaped to match this
    // flag, so declaring "multiple: true" while the ECA model returns a scalar
    // is a misconfiguration that the pipeline surfaces as a MigrateException
    // ("received instead of an array") rather than being silently coerced.
    return !empty($this->configuration['multiple']);
  }

}
