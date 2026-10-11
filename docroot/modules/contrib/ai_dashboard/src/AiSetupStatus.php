<?php

namespace Drupal\ai_dashboard;

use Drupal\ai\AiProviderPluginManager;
use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Config\ConfigFactoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Contains service for getting the status of AI modules setup.
 */
class AiSetupStatus implements AiSetupStatusInterface {

  /**
   * The AI Provider plugin manager.
   *
   * @var \Drupal\ai\AiProviderPluginManager
   */
  protected AiProviderPluginManager $aiProviderManager;

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * The logger channel.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected LoggerInterface $logger;

  public function __construct(AiProviderPluginManager $ai_provider_manager, ConfigFactoryInterface $config_factory, LoggerInterface $logger) {
    $this->aiProviderManager = $ai_provider_manager;
    $this->configFactory = $config_factory;
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public function getProviderSetupStatus($provider_name) {
    $key_is_set = 'not-available';
    try {
      // Get the module that is provider for AI provider plugin.
      $definition = $this->aiProviderManager->getDefinition($provider_name);
      // Get the AI Provider plugin.
      $provider_instance = $this->aiProviderManager->createInstance($provider_name);
      // Check whether setup data is populated.
      $setup_data = $provider_instance->getSetupData();
      // Get the config property name that contains key machine name.
      if (!empty($setup_data['key_config_name'])) {
        $provider_config_settings = $this->configFactory->get($definition['provider'] . '.settings');
        // If config is new - the provider is not at all configured yet.
        if ($provider_config_settings->isNew()) {
          $key_is_set = 'no';
        }
        else {
          $key_name = $provider_config_settings->get($setup_data['key_config_name']);
          if (!empty($key_name)) {
            $key_is_set = 'yes';
          }
          else {
            $key_is_set = 'no';
          }
        }
      }
    }
    catch (PluginException $e) {
      $this->logger->warning($e->getMessage());
    }
    return $key_is_set;
  }

}
