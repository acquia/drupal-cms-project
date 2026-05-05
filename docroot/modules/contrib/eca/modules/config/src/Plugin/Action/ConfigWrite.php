<?php

namespace Drupal\eca_config\Plugin\Action;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\ComplexDataInterface;
use Drupal\Core\TypedData\TypedDataInterface;
use Drupal\eca\Attribute\EcaAction;
use Drupal\eca\Plugin\DataType\DataTransferObject;
use Drupal\eca\Plugin\FormFieldYamlTrait;
use Drupal\eca\Service\YamlParser;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Yaml\Exception\ParseException;

/**
 * Action to write configuration.
 */
#[Action(
  id: 'eca_config_write',
  label: new TranslatableMarkup('Config: write'),
)]
#[EcaAction(
  description: new TranslatableMarkup('Writes into configuration from a token.'),
  version_introduced: '1.0.0',
)]
class ConfigWrite extends ConfigActionBase {

  use FormFieldYamlTrait;

  /**
   * The YAML parser.
   *
   * @var \Drupal\eca\Service\YamlParser
   */
  protected YamlParser $yamlParser;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->setYamlParser($container->get('eca.service.yaml_parser'));
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    $result = parent::access($object, $account, TRUE);
    if ($result->isAllowed() && $this->configuration['use_yaml'] && $this->configuration['validate_yaml']) {
      try {
        $this->yamlParser->parse($this->configuration['config_value']);
      }
      catch (ParseException) {
        $result = AccessResult::forbidden('YAML data is not valid.');
      }
    }
    return $return_as_object ? $result : $result->isAllowed();
  }

  /**
   * {@inheritdoc}
   */
  public function execute(?object $object = NULL): void {
    $token = $this->tokenService;
    $config_name = $token->replace($this->configuration['config_name']);
    $config_key = $this->configuration['config_key'] !== '' ? (string) $token->replace($this->configuration['config_key']) : '';
    $config_value = $this->configuration['config_value'];
    if ($this->configuration['use_yaml']) {
      try {
        $config_value = $this->yamlParser->parse($config_value);
      }
      catch (ParseException $e) {
        $this->logger->error('Tried parsing a config value in action "eca_config_write" as YAML format, but parsing failed.');
        return;
      }
    }
    else {
      $config_value = $token->getOrReplace($config_value);
      if ($config_value instanceof DataTransferObject) {
        $config_value = $config_value->count() ? $config_value->toArray() : $config_value->getString();
      }
      elseif ($config_value instanceof ComplexDataInterface) {
        $config_value = $config_value->toArray();
      }
      elseif ($config_value instanceof TypedDataInterface) {
        $config_value = $config_value->getValue();
      }
      if ($config_value instanceof EntityInterface) {
        $config_value = $config_value->toArray();
      }
    }
    $entity_type_id = $this->getConfigManager()->getEntityTypeIdByName($config_name);
    if ($entity_type_id !== NULL) {
      /** @var \Drupal\Core\Config\Entity\ConfigEntityInterface|null $config */
      $config = $this->getConfigManager()->loadConfigEntityByName($config_name);

      if ($config_key !== '') {
        $key_parts = explode('.', $config_key);
        if (count($key_parts) > 1) {
          // Handle nested keys.
          // Get the top-level property.
          $top_level_key = $key_parts[0];

          if ($config !== NULL) {
            // Get existing value to merge with.
            $existing = $config->get($top_level_key);
            if (!is_array($existing)) {
              $existing = [];
            }
          }
          else {
            $existing = [];
          }

          // Set nested value using NestedArray.
          $nested_parts = array_slice($key_parts, 1);
          NestedArray::setValue($existing, $nested_parts, $config_value);
          $config_value = [$top_level_key => $existing];
        }
        else {
          // Single-level key.
          $config_value = [$config_key => $config_value];
        }
      }

      if ($config === NULL) {
        $config = $this->entityTypeManager->getStorage($entity_type_id)->create($config_value);
      }
      else {
        foreach ($config_value as $key => $value) {
          $config->set($key, $value);
        }
      }

    }
    else {
      $config_factory = $this->getConfigFactory();

      $config = $config_factory->getEditable($config_name);
      $current_value = $config->get($config_key);

      // Change the configuration value only when the new config value differs.
      if ($current_value !== $config_value) {
        if ($config_key === '') {
          $config->setData($config_value);
        }
        else {
          $config->set($config_key, $config_value);
        }
      }
    }

    if ($this->configuration['save_config']) {
      $config->save();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'config_value' => '',
      'use_yaml' => FALSE,
      'validate_yaml' => FALSE,
      'save_config' => FALSE,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['config_value'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Config value'),
      '#description' => $this->t('The value to set.'),
      '#default_value' => $this->configuration['config_value'],
      '#weight' => -70,
      '#eca_token_replacement' => TRUE,
    ];
    $this->buildYamlFormFields(
      $form,
      $this->t('Interpret above config value as YAML format'),
      $this->t('Nested data can be set using YAML format, for example <em>front: /node</em>. When using this format, this option needs to be enabled. When using tokens and YAML altogether, make sure that tokens are wrapped as a string. Example: <em>front: "[myurl:path]"</em>'),
      -60,
    );
    $form['save_config'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Save configuration'),
      '#default_value' => $this->configuration['save_config'],
      '#weight' => -50,
      '#description' => $this->t('Save the given config to the Drupal database.'),
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['config_value'] = $form_state->getValue('config_value');
    $this->configuration['use_yaml'] = !empty($form_state->getValue('use_yaml'));
    $this->configuration['validate_yaml'] = !empty($form_state->getValue('validate_yaml'));
    $this->configuration['save_config'] = !empty($form_state->getValue('save_config'));
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * Set the YAML parser.
   *
   * @param \Drupal\eca\Service\YamlParser $yaml_parser
   *   The YAML parser.
   */
  public function setYamlParser(YamlParser $yaml_parser): void {
    $this->yamlParser = $yaml_parser;
  }

}
