<?php

declare(strict_types=1);

namespace Drupal\custom_field\Feeds\Target;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\custom_field\Plugin\CustomFieldFeedsManagerInterface;
use Drupal\feeds\Exception\EmptyFeedException;
use Drupal\feeds\Exception\TargetValidationException;
use Drupal\feeds\FieldTargetDefinition;
use Drupal\feeds\Plugin\Type\Target\ConfigurableTargetInterface;
use Drupal\feeds\Plugin\Type\Target\FieldTargetBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a custom field mapper.
 *
 * @FeedsTarget(
 *   id = "custom_field_feeds_target",
 *   field_types = {"custom"}
 * )
 */
class CustomField extends FieldTargetBase implements ConfigurableTargetInterface, ContainerFactoryPluginInterface {

  /**
   * The Custom field feeds manager.
   *
   * @var \Drupal\custom_field\Plugin\CustomFieldFeedsManagerInterface
   */
  protected $feedsManager;

  /**
   * Constructs a new CustomField feeds target object.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin id.
   * @param array $plugin_definition
   *   The plugin definition.
   * @param \Drupal\custom_field\Plugin\CustomFieldFeedsManagerInterface $feeds_manager
   *   The custom field feeds manager service.
   */
  public function __construct(array $configuration, $plugin_id, array $plugin_definition, CustomFieldFeedsManagerInterface $feeds_manager) {
    $this->feedsManager = $feeds_manager;
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.custom_field_feeds'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    $configuration = parent::defaultConfiguration() + ['timezone' => 'UTC'];
    $targets = $this->feedsManager->getFeedsTargets($this->settings);
    foreach ($targets as $name => $target) {
      $default_configuration = $target->defaultConfiguration();
      if (!empty($default_configuration)) {
        $configuration[$name] = $default_configuration;
      }
    }

    return $configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildConfigurationForm($form, $form_state);
    $targets = $this->feedsManager->getFeedsTargets($this->settings);
    $delta = 0;
    // Hack to find out the target delta.
    foreach ($form_state->getValues() as $key => $value) {
      if (str_starts_with($key, 'target-settings-')) {
        [, , $delta] = explode('-', $key);
        break;
      }
    }
    foreach ($targets as $name => $target) {
      $configuration = $this->configuration[$name] ?? [];
      $build_configuration_form = $target->buildConfigurationForm((int) $delta, $configuration);
      if (!empty($build_configuration_form)) {
        $form[(string) $name] = [
          '#type' => 'details',
          '#title' => $this->t('@name', ['@name' => (string) $name]),
          '#tree' => TRUE,
        ] + $build_configuration_form;
      }
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getSummary(): array {
    /** @var string[] $summary */
    $summary = parent::getSummary();

    $field_name = $this->getTargetDefinition()->getLabel();
    $targets = $this->feedsManager->getFeedsTargets($this->settings);
    foreach ($targets as $name => $target) {
      $configuration = $this->configuration[$name] ?? [];
      $target_summaries = $target->getSummary($configuration);
      if (!empty($target_summaries)) {
        $formatted_summary = new FormattableMarkup(
          '<strong>@field</strong> (@label):', [
            '@field' => $field_name,
            '@label' => $name,
          ],
        );
        $summary[] = $this->t('@summary', ['@summary' => $formatted_summary]);
        foreach ($target_summaries as $target_summary) {
          $summary[] = $target_summary;
        }
      }
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  protected static function prepareTarget(FieldDefinitionInterface $field_definition): FieldTargetDefinition {
    $definition = FieldTargetDefinition::createFromFieldDefinition($field_definition);
    /** @var \Drupal\custom_field\Plugin\CustomFieldFeedsManagerInterface $feeds_manager */
    $feeds_manager = \Drupal::service('plugin.manager.custom_field_feeds');
    $settings = $field_definition->getSettings();
    $targets = $feeds_manager->getFeedsTargets($settings);
    foreach ($targets as $name => $target) {
      $definition->addProperty($name);
      /** @var array $plugin_definition */
      $plugin_definition = $target->getPluginDefinition();
      $mark_unique = $plugin_definition['mark_unique'] ?? FALSE;
      if ($mark_unique) {
        $definition->markPropertyUnique($name);
      }
    }
    return $definition;
  }

  /**
   * {@inheritdoc}
   *
   * @return array<string, mixed>
   *   The array of values.
   */
  protected function prepareValue($delta, array &$values): array {
    $langcode = $this->getLangcode();
    if (!empty($values)) {
      $targets = $this->feedsManager->getFeedsTargets($this->settings);
      foreach ($values as $name => $value) {
        if (isset($targets[$name])) {
          $configuration = $this->configuration[$name] ?? [];
          $values[$name] = !is_null($value) ? $targets[$name]->prepareValue($value, $configuration, $langcode) : NULL;
        }
        else {
          $values[$name] = NULL;
        }
      }
      return $values;
    }
    else {
      throw new EmptyFeedException();
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function prepareValues(array $values): array {
    $return = [];
    foreach ($values as $delta => $columns) {
      try {
        $this->prepareValue($delta, $columns);
        $return[] = $columns;
      }
      catch (EmptyFeedException $e) {
        // Nothing wrong here.
      }
      catch (TargetValidationException $e) {
        // Validation failed.
        $this->addMessage($e->getFormattedMessage(), 'error');
      }
    }
    return $return;
  }

}
