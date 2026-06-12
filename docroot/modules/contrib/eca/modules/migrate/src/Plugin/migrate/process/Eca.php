<?php

namespace Drupal\eca_migrate\Plugin\migrate\process;

use Drupal\eca\Event\TriggerEvent;
use Drupal\migrate\Attribute\MigrateProcess;
use Drupal\migrate\MigrateExecutableInterface;
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
 * If the ECA model can not be triggered, then the plugin will
 * return the untransformed source value.
 *
 * To run an eca model only on a specific migration, use a constant,
 * which will be available within the model in the row token,
 * e.g. row:source:constants:migration_id, and add a
 * condition in the model to check for that constant.
 *
 * @code
 *   source:
 *     constants:
 *       migration_id: csv_file_migration
 *   process:
 *     new_text_field:
 *       plugin: eca
 *       source: some_text_field
 * @endcode
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
   * Constructs the ECA plugin.
   */
  final public function __construct(array $configuration, $plugin_id, $plugin_definition, TriggerEvent $event_dispatcher) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->eventDispatcher = $event_dispatcher;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('eca.trigger_event')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    /** @var \Drupal\eca_migrate\Event\EcaMigrateProcessEvent|null $event */
    $event = $this->eventDispatcher->dispatchFromPlugin(
      'migrate:process',
      $value,
      $row,
      $destination_property,
    );

    return ($event) ? $event->getValue() : $value;
  }

}
