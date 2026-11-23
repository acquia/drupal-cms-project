<?php

declare(strict_types=1);

namespace Drupal\eca_config\Plugin\Action;

use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eca\Attribute\EcaAction;
use Drupal\eca\Plugin\Action\ConfigurableActionBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Action to retrieve a list of config objects and store it in a token.
 */
#[Action(
  id: 'eca_config_list',
  label: new TranslatableMarkup('Config: get list'),
)]
#[EcaAction(
  description: new TranslatableMarkup('Gets a list of configuration names and stores it as a token.'),
  version_introduced: '3.0.8',
)]
class ConfigList extends ConfigurableActionBase {

  /**
   * The config factory service.
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->configFactory = $container->get(ConfigFactoryInterface::class);
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'token_name' => '',
      'prefix' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $configuration = $this->getConfiguration();

    $form['token_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Token name'),
      '#description' => $this->t('The name of the token to store the configuration list.'),
      '#default_value' => $configuration['token_name'],
      '#required' => TRUE,
      '#eca_token_reference' => TRUE,
    ];
    $form['prefix'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Configuration prefix'),
      '#description' => $this->t('Optional prefix to filter configuration names.'),
      '#default_value' => $configuration['prefix'],
      '#eca_token_replacement' => TRUE,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->setConfiguration([
      'token_name' => $form_state->getValue('token_name'),
      'prefix' => $form_state->getValue('prefix'),
    ]);
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function execute(?object $object = NULL): void {
    $configuration = $this->getConfiguration();
    $prefix = $this->tokenService->getOrReplace($configuration['prefix']);

    $this->tokenService->addTokenData(
      $configuration['token_name'],
      $this->configFactory->listAll($prefix),
    );
  }

}
