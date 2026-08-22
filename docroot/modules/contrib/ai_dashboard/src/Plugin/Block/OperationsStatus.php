<?php

namespace Drupal\ai_dashboard\Plugin\Block;

use Drupal\ai\AiProviderPluginManager;
use Drupal\ai\Enum\AiModelCapability;
use Drupal\ai\Exception\AiSetupFailureException;
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
   * The hard coded selections to add for filtering purposes.
   *
   * This property is copied from Drupal\ai\Form\AiSettingsForm. It needs to be
   * accessible from there, but for the moment it is protected and there is no
   * method to retrieve it.
   *
   * @var array
   */
  protected $hardcodedSelections = [
    [
      'id' => 'chat_with_image_vision',
      'actual_type' => 'chat',
      'label' => 'Chat with Image Vision',
      'filter' => [AiModelCapability::ChatWithImageVision],
    ],
    [
      'id' => 'chat_with_complex_json',
      'actual_type' => 'chat',
      'label' => 'Chat with Complex JSON',
      'filter' => [AiModelCapability::ChatJsonOutput],
    ],
    [
      'id' => 'chat_with_structured_response',
      'actual_type' => 'chat',
      'label' => 'Chat with Structured Response',
      'filter' => [AiModelCapability::ChatStructuredResponse],
    ],
    [
      'id' => 'chat_with_tools',
      'actual_type' => 'chat',
      'label' => 'Chat with Tools/Function Calling',
      'filter' => [AiModelCapability::ChatTools],
    ],
  ];

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
    $operation_types = $this->aiProviderManager->getOperationTypes();
    // Add the hardcoded selections of filtered types.
    $operation_types = array_merge($operation_types, $this->hardcodedSelections);

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
          'class' => ['ai-dashboard-enabled-providers-title'],
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
        foreach ($operation_types as $type => $operation) {
          $supported = $this->checkOperationTypeStatus($operation['id'], $provider);
          $supported_label = $supported ? $this->t('Supported') : $this->t('Not supported');
          $items[$type] = [
            'data' => [
              '#markup' => $operation['label'] . ' <span class="operation-status visually-hidden">(' . $supported_label . ')</span>',
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
          '#markup' => '<h3>' . $operation['label'] . '</h3>',
        ],
        'description' => [
          '#markup' => !empty($operation['description']) ? '<p class="description">' . $operation['description'] . '</p>' : '',
        ],
        'setting' => [
          '#markup' => '<p class="operation-type-default-setting">' . $setting . '</p>',
        ],
      ];
    }
    return $build;
  }

  /**
   * Checks whether provider supports given operation.
   *
   * @param string $type
   *   The operation type.
   * @param string $provider_name
   *   The provider plugin name.
   *
   * @return bool
   *   TRUE in case the operation type is supported, FALSE - otherwise.
   */
  protected function checkOperationTypeStatus($type, $provider_name) {
    try {
      /** @var \Drupal\ai\AiProviderInterface $provider */
      $provider = $this->aiProviderManager->createInstance($provider_name);
      if (in_array($type, $provider->getSupportedOperationTypes())) {
        return TRUE;
      }
      // If this is a type defined in the plugin, no need to check further.
      elseif (in_array($type, array_keys($this->aiProviderManager->getOperationTypes()))) {
        return FALSE;
      }
      $setup_data = $provider->getSetupData();
      if (!empty($setup_data['default_models'][$type])) {
        return TRUE;
      }
      $operation_models = $provider->getConfiguredModels($type);
      if (!empty($operation_models)) {
        return TRUE;
      }
    }
    catch (PluginException | AiSetupFailureException $exception) {
      $this->logger->warning($exception->getMessage());
    }
    catch (\Error $exception) {
      $this->logger->error($exception->getMessage());
    }
    return FALSE;
  }

}
