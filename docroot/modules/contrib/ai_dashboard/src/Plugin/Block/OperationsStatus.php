<?php

namespace Drupal\ai_dashboard\Plugin\Block;

use Drupal\ai\AiProviderPluginManager;
use Drupal\ai\Exception\AiSetupFailureException;
use Drupal\ai\Utility\PseudoOperationTypes;
use Drupal\ai_dashboard\AiSetupStatusInterface;
use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a block for module list reduced to given packages.
 *
 * @Block(
 *   id = "ai_operations_status",
 *   admin_label = @Translation("AI Operations Status"),
 *   category = @Translation("AI Dashboard"),
 * )
 */
#[Block(
  id: "ai_operations_status",
  admin_label: new TranslatableMarkup("AI Operations Status"),
  category: new TranslatableMarkup("AI Dashboard"),
)]
class OperationsStatus extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The AI Provider plugin manager.
   *
   * @var \Drupal\ai\AiProviderPluginManager
   */
  protected AiProviderPluginManager $aiProviderManager;

  /**
   * The AI module settings.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected ImmutableConfig $config;

  /**
   * The module extension list.
   *
   * @var \Drupal\Core\Extension\ModuleExtensionList
   */
  protected ModuleExtensionList $moduleList;

  /**
   * The AI Setup Status service.
   *
   * @var \Drupal\ai_dashboard\AiSetupStatusInterface
   */
  protected AiSetupStatusInterface $aiSetupStatus;

  /**
   * The logger channel.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected LoggerInterface $logger;

  /**
   * The real (plugin-defined) operation types, keyed by operation type ID.
   *
   * Populated in build(). Used to tell recognized operation types apart from
   * ones a provider only exposes through its setup data or configured models.
   *
   * @var array
   */
  protected array $realOperationTypes = [];

  /**
   * Per-request cache of configured models.
   *
   * Keyed by "provider ID|operation type|capabilities", so each provider is
   * asked for its models at most once per combination while building.
   *
   * @var array
   */
  protected array $configuredModelsCache = [];

  public function __construct(array $configuration, $plugin_id, $plugin_definition, ConfigFactoryInterface $config_factory, AiProviderPluginManager $ai_provider_manager, ModuleExtensionList $module_list, AiSetupStatusInterface $ai_setup_status, LoggerInterface $logger) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->aiProviderManager = $ai_provider_manager;
    $this->config = $config_factory->get('ai.settings');
    $this->moduleList = $module_list;
    $this->aiSetupStatus = $ai_setup_status;
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory'),
      $container->get('ai.provider'),
      $container->get('extension.list.module'),
      $container->get('ai_dashboard.setup_status'),
      $container->get('logger.channel.ai_dashboard')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [];
    $providers = $this->aiProviderManager->getDefinitions();
    $this->realOperationTypes = $this->aiProviderManager->getOperationTypes();
    $operation_types = $this->realOperationTypes;
    // Add the capability-filtered pseudo operation types (e.g. "Chat with
    // Image Vision"), matching the ones offered in the AI settings form.
    $operation_types = array_merge($operation_types, PseudoOperationTypes::getDefaultPseudoOperationTypes());

    if (!empty($providers)) {
      $build['providers'] = [
        '#type' => 'details',
        '#title' => $this->t('Providers'),
        '#open' => FALSE,
        '#description' => $this->t('Enable and configure AI providers used by your site. Each provider may require specific credentials or settings.'),
      ];
      $build['providers']['enabled'] = [
        '#type' => 'container',
      ];
      $build['providers']['enabled']['title'] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['ai-dashboard-enabled-providers-title', 'ai-font-size-xs'],
        ],
        'content' => [
          '#markup' => $this->t('Currently enabled'),
        ],
      ];
      $build['providers']['enabled']['status'] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => [
            'ai-dashboard-enabled-providers-status',
          ],
        ],
      ];
      foreach ($providers as $provider => $definition) {
        $items = [];
        try {
          $provider_instance = $this->aiProviderManager->createInstance($provider);
        }
        catch (PluginException | AiSetupFailureException $exception) {
          $this->logger->warning($exception->getMessage());
          $provider_instance = NULL;
        }
        foreach ($operation_types as $type => $operation) {
          $supported = $provider_instance && $this->checkOperationTypeStatus($provider_instance, $operation);
          $supported_label = $supported ? $this->t('Supported') : $this->t('Not supported');
          $items[$type] = [
            'data' => [
              '#markup' => $this->t('@label <span class="operation-status visually-hidden">(@status)</span>', [
                '@label' => $operation['label'],
                '@status' => $supported_label,
              ]),
            ],
            '#wrapper_attributes' => [
              'class' => [
                'ai-dashboard-operation-type',
                $supported ? 'operation-type-supported' : 'operation-type-not-supported',
              ],
            ],
          ];
        }
        $link_to_configuration = Url::fromRoute('ai.admin_providers')->toString();
        $key_is_set = $this->aiSetupStatus->getProviderSetupStatus($provider);
        $module_info = $this->moduleList->get($definition['provider']);
        if (!empty($module_info->info['configure'])) {
          $link_to_configuration = Url::fromRoute($module_info->info['configure'])->toString();
        }

        $status_text = match ($key_is_set) {
          'yes' => $this->t('Provider @provider configured', [
            '@provider' => $definition['label'],
          ]),
          'no' => $this->t('Provider @provider not configured', [
            '@provider' => $definition['label'],
          ]),
          default => $this->t('Configuration status for provider @provider not available', [
            '@provider' => $definition['label'],
          ]),
        };

        $build['providers']['enabled']['status'][$provider] = [
          '#type' => 'container',
          '#attributes' => [
            'class' => [
              'ai-dashboard-provider',
            ],
          ],
          'status-text' => [
            '#theme' => 'ai_dashboard_provider_setup_status',
            '#is_key_set' => $key_is_set,
            '#status_text' => $status_text,
            '#configuration_link' => $link_to_configuration,
          ],
          'provider-details' => [
            '#type' => 'details',
            '#title' => $definition['label'],
            'available_operations' => [
              '#title' => $this->t('Available operations'),
              '#theme' => 'item_list',
              '#items' => $items,
              '#empty' => $this->t('No available operations found.'),
            ],
          ],
        ];
      }
    }
    $build['default_models'] = [
      '#type' => 'details',
      '#title' => $this->t('Default models'),
      '#attributes' => [
        'class' => [
          'ai-dashboard-default-models-status',
        ],
      ],
    ];
    foreach ($operation_types as $type => $operation) {
      $setting = $this->t('Not available setting');
      $model = $this->config->get('default_providers.' . $operation['id']);
      if (!empty($model)) {
        $setting = $providers[$model['provider_id']]['label'] . ' / ' . $model['model_id'];
      }
      $build['default_models'][$type] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => [
            'ai-dashboard-operation-type-default',
          ],
        ],
        'title' => [
          '#type' => 'html_tag',
          '#tag' => 'h3',
          '#value' => $operation['label'],
          '#attributes' => ['class' => ['ai-heading-h6']],
        ],
        'description' => !empty($operation['description']) ? [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#value' => $operation['description'],
          '#attributes' => ['class' => ['description']],
        ] : [],
        'setting' => [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#value' => $setting,
          '#attributes' => ['class' => ['operation-type-default-setting', 'ai-font-size-s']],
        ],
      ];
    }
    return $build;
  }

  /**
   * Checks whether provider supports given operation.
   *
   * @param \Drupal\ai\AiProviderInterface $provider
   *   The provider plugin instance to check.
   * @param array $operation
   *   The operation type definition, as returned by
   *   \Drupal\ai\AiProviderPluginManager::getOperationTypes() or
   *   \Drupal\ai\Utility\PseudoOperationTypes::getDefaultPseudoOperationTypes().
   *   May contain an 'actual_type' key (the real operation type to check,
   *   defaults to the 'id') and a 'filter' key (a list of
   *   \Drupal\ai\Enum\AiModelCapability cases the operation is restricted
   *   to, defaults to none).
   *
   * @return bool
   *   TRUE in case the operation type is supported, FALSE - otherwise.
   */
  protected function checkOperationTypeStatus($provider, array $operation) {
    $actual_type = $operation['actual_type'] ?? $operation['id'];
    $capabilities = $operation['filter'] ?? [];
    try {
      // Capability-filtered pseudo operation types (e.g. "Chat with Image
      // Vision") can only be confirmed by checking whether a configured
      // model actually exposes the required capabilities. Supporting the
      // underlying operation type in general is not enough on its own.
      if (!empty($capabilities)) {
        // Like the AI settings form, never ask a provider for models before
        // it reports itself as usable for this type and capabilities.
        return $provider->isUsable($actual_type, $capabilities)
          && !empty($this->getConfiguredModels($provider, $actual_type, $capabilities));
      }
      if (in_array($actual_type, $provider->getSupportedOperationTypes())) {
        return TRUE;
      }
      // If this is a type defined in the plugin, no need to check further.
      elseif (array_key_exists($actual_type, $this->realOperationTypes)) {
        return FALSE;
      }
      $setup_data = $provider->getSetupData();
      if (!empty($setup_data['default_models'][$actual_type])) {
        return TRUE;
      }
      if (!empty($this->getConfiguredModels($provider, $actual_type))) {
        return TRUE;
      }
    }
    catch (\Exception $exception) {
      // Includes AiSetupFailureException as well as any provider or HTTP
      // client failure while listing models: one broken provider must not
      // take down the whole block.
      $this->logger->warning($exception->getMessage());
    }
    catch (\Error $exception) {
      $this->logger->error($exception->getMessage());
    }
    return FALSE;
  }

  /**
   * Gets a provider's configured models, memoized for this block instance.
   *
   * @param \Drupal\ai\AiProviderInterface $provider
   *   The provider plugin instance.
   * @param string $operation_type
   *   The real operation type.
   * @param array $capabilities
   *   The \Drupal\ai\Enum\AiModelCapability cases to filter by.
   *
   * @return array
   *   The configured models.
   */
  protected function getConfiguredModels($provider, string $operation_type, array $capabilities = []): array {
    $capability_key = implode(',', array_map(
      static fn ($capability) => $capability instanceof \UnitEnum ? $capability->name : (string) $capability,
      $capabilities
    ));
    $key = $provider->getPluginId() . '|' . $operation_type . '|' . $capability_key;
    return $this->configuredModelsCache[$key] ??= $provider->getConfiguredModels($operation_type, $capabilities);
  }

}
