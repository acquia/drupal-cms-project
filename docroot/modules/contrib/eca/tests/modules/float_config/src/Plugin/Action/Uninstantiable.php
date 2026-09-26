<?php

namespace Drupal\eca_test_float_config\Plugin\Action;

use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eca\Attribute\EcaAction;
use Drupal\eca\Plugin\Action\ConfigurableActionBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * An action that throws when it is instantiated.
 *
 * It exists so that a test can tell whether hook_config_schema_info_alter()
 * instantiates plugins: its configuration schema section is only altered when
 * the hook works from the plugin definition.
 *
 * @see \Drupal\eca\Hook\ConfigSchemaHooks::configSchemaInfoAlter()
 */
#[Action(
  id: 'eca_test_float_config_uninstantiable',
  label: new TranslatableMarkup('Uninstantiable action'),
)]
#[EcaAction(
  description: new TranslatableMarkup('This action cannot be instantiated.'),
  no_docs: TRUE,
)]
class Uninstantiable extends ConfigurableActionBase {

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    throw new \LogicException('The eca_test_float_config_uninstantiable action must not be instantiated.');
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'delay' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function execute(?object $object = NULL): void {}

}
