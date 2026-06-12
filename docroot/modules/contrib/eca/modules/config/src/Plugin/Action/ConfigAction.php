<?php

namespace Drupal\eca_config\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Config\Action\ConfigActionManager;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eca\Attribute\EcaAction;
use Drupal\eca\Plugin\Action\ConfigurableActionBase;
use Drupal\eca\Plugin\ECA\PluginFormTrait;
use Drupal\eca\Service\YamlParser;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Yaml\Exception\ParseException;

/**
 * Action to read configuration.
 */
#[Action(
  id: 'eca_config_action',
  label: new TranslatableMarkup('Config Action'),
)]
#[EcaAction(
  description: new TranslatableMarkup('Execute a Drupal Core ConfigAction.'),
  version_introduced: '3.0.0',
)]
class ConfigAction extends ConfigurableActionBase {

  use PluginFormTrait;

  /**
   * The config action plugin manager.
   *
   * @var \Drupal\Core\Config\Action\ConfigActionManager
   */
  protected ConfigActionManager $configActionManager;

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
    $instance->configActionManager = $container->get('plugin.manager.config_action');
    $instance->yamlParser = $container->get('eca.service.yaml_parser');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE): AccessResultInterface|bool {
    $result = AccessResult::forbidden();
    $account = $account ?: $this->currentUser;
    if ($account->hasPermission('administer site configuration')) {
      $result = AccessResult::allowed();
      $configName = trim($this->tokenService->replaceClear($this->configuration['config_name']));
      if ($configName === '') {
        $result = AccessResult::forbidden('Invalid config name.');
      }
      else {
        $actionId = $this->tokenService->replaceClear($this->configuration['action_id']);
        if ($actionId === '_eca_token') {
          $actionId = trim($this->getTokenValue('action_id', ''));
        }
        if (!isset($this->getActions()[$actionId])) {
          $result = AccessResult::forbidden('Invalid config action.');
        }
      }
    }
    return $return_as_object ? $result->cachePerPermissions() : FALSE;
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function execute(?object $object = NULL): void {
    try {
      $data = $this->yamlParser->parse($this->configuration['data']);
    }
    catch (ParseException) {
      $this->logger->error('Tried parsing data in action "eca_config_action", but parsing failed.');
      return;
    }
    $configName = $this->tokenService->replaceClear($this->configuration['config_name']);
    $actionId = $this->tokenService->replaceClear($this->configuration['action_id']);
    if ($actionId === '_eca_token') {
      $actionId = $this->getTokenValue('action_id', '');
    }
    $this->configActionManager->applyAction($actionId, $configName, $data);
  }

  /**
   * Provides the list of available config actions, indexed by action ID.
   *
   * @return array
   *   The list of available config actions.
   */
  protected function getActions(): array {
    $actions = [];
    foreach ($this->configActionManager->getDefinitions() as $action_id => $definition) {
      if ($label = $definition['admin_label']) {
        $actions[$action_id] = (string) $label . ' (' . $action_id . ')';
      }
    }
    asort($actions);
    return $actions;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'action_id' => '',
      'config_name' => '',
      'data' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['action_id'] = [
      '#type' => 'select',
      '#title' => $this->t('Config action'),
      '#description' => $this->t('The config action to be applied.'),
      '#default_value' => $this->configuration['action_id'],
      '#options' => $this->getActions(),
      '#required' => TRUE,
      '#weight' => -90,
      '#eca_token_select_option' => TRUE,
    ];
    $form['config_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Config name'),
      '#description' => $this->t('The config name, for example <em>system.site</em>.'),
      '#default_value' => $this->configuration['config_name'],
      '#required' => TRUE,
      '#weight' => -80,
      '#eca_token_replacement' => TRUE,
    ];
    $form['data'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Data'),
      '#description' => $this->t('The data for the config action, provided in YAML format.'),
      '#default_value' => $this->configuration['data'],
      '#weight' => -70,
      '#eca_token_replacement' => TRUE,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['action_id'] = $form_state->getValue('action_id');
    $this->configuration['config_name'] = $form_state->getValue('config_name');
    $this->configuration['data'] = $form_state->getValue('data');
    parent::submitConfigurationForm($form, $form_state);
  }

}
