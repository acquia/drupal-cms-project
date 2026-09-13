<?php

namespace Drupal\custom_field\Plugin\Field\FieldFormatter;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Utility\Token;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'custom_template' formatter.
 */
#[FieldFormatter(
  id: 'custom_template',
  label: new TranslatableMarkup('Custom template'),
  description: new TranslatableMarkup('Render the custom field using a custom template with token replacement.'),
  field_types: [
    'custom',
  ],
  weight: 4,
)]
class CustomTemplateFormatter extends BaseFormatter {

  /**
   * The token service.
   *
   * @var \Drupal\Core\Utility\Token
   */
  protected Token $tokenService;

  /**
   * The token entity mapper service.
   *
   * @var \Drupal\token\TokenEntityMapperInterface
   */
  protected $tokenEntityMapper;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->tokenService = $container->get('token');
    $instance->tokenEntityMapper = $container->get(
      'token.entity_mapper',
      ContainerInterface::NULL_ON_INVALID_REFERENCE
    );

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'template' => '',
      'tokens' => 'basic',
      'advanced_tokens' => [
        'recursion_limit' => 3,
        'global_types' => FALSE,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $form = parent::settingsForm($form, $form_state);
    $entity_type = $this->fieldDefinition->getTargetEntityTypeId();
    $custom_items = $this->getCustomFieldItems();
    $token_module_installed = $this->moduleHandler->moduleExists('token');
    $field_name = $this->fieldDefinition->getName();
    $is_views_form = $form_state->getFormObject()->getFormId() == 'views_ui_config_item_form';
    $visibility_path = 'fields[' . $field_name . '][settings_edit_form][settings]';
    if ($is_views_form) {
      $visibility_path = 'options[settings]';
    }
    // Remove field level settings as they are not applicable.
    unset($form['fields']);

    $form['tokens'] = [
      '#type' => 'radios',
      '#title' => $this->t('Tokens'),
      '#options' => [
        'basic' => $this->t('Basic'),
        'advanced' => $this->t('Advanced'),
      ],
      '#required' => TRUE,
      '#default_value' => $this->getSetting('tokens'),
    ];
    $form['template'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Template'),
      '#rows' => 5,
      '#default_value' => $this->getSetting('template'),
      '#attributes' => [
        'placeholder' => $this->t('Insert tokens here along with any desired html.'),
      ],
    ];
    // Build the basic token markup.
    $basic_tokens = [
      '#theme' => 'item_list',
      '#items' => [],
    ];
    foreach ($custom_items as $name => $custom_item) {
      $label = $custom_item->getLabel();
      $basic_tokens['#items'][] = "[$name]: $label value";
      $basic_tokens['#items'][] = "[$name:label]: $label label";
    }

    $form['basic_tokens'] = [
      '#type' => 'item',
      '#description' => $this->t('<p><strong>The following tokens are available for replacement.</strong></p>') . $this->renderer->renderInIsolation($basic_tokens),
      '#states' => [
        'visible' => [
          ':input[name="' . $visibility_path . '[tokens]"]' => ['value' => 'basic'],
        ],
      ],
    ];

    if (!$token_module_installed) {
      $form['advanced_tokens_notice'] = [
        '#theme_wrappers' => ['container'],
        '#type' => 'markup',
        '#markup' => $this->t('Advanced token replacement requires the <a href=":url" target="_blank">Token module</a>.', [
          ':url' => 'https://www.drupal.org/project/token',
        ]),
        '#states' => [
          'visible' => [
            ':input[name="' . $visibility_path . '[tokens]"]' => ['value' => 'advanced'],
          ],
        ],
      ];
    }
    else {
      $token_type = $this->tokenEntityMapper->getTokenTypeForEntityType($entity_type);
      // Use token_browser_link if available.
      if ($this->moduleHandler->moduleExists('token_browser')) {
        $form['advanced_tokens'] = [
          '#theme' => 'token_browser_link',
          '#theme_wrappers' => ['container'],
          '#token_types' => [$token_type],
          '#global_types' => TRUE,
          '#states' => [
            'visible' => [
              ':input[name="' . $visibility_path . '[tokens]"]' => ['value' => 'advanced'],
            ],
          ],
        ];
      }
      else {
        $form['advanced_tokens'] = [
          '#type' => 'token_browser',
          '#theme_wrappers' => ['fieldset'],
          '#title' => $this->t('Advanced Tokens'),
          '#token_types' => [$token_type],
          '#recursion_limit' => $this->getSetting('advanced_tokens')['recursion_limit'],
          '#recursion_limit_max' => 6,
          '#global_types' => $this->getSetting('advanced_tokens')['global_types'],
          '#show_settings' => TRUE,
          '#states' => [
            'visible' => [
              ':input[name="' . $visibility_path . '[tokens]"]' => ['value' => 'advanced'],
            ],
          ],
        ];
      }
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    $tokens_type = $this->getSetting('tokens');
    $summary[] = $this->t('Tokens: @tokens', ['@tokens' => $tokens_type]);
    if ($tokens_type === 'advanced') {
      $summary[] = $this->t('Recursion limit: @limit', ['@limit' => $this->getSetting('advanced_tokens')['recursion_limit']]);
      $summary[] = $this->t('Global types: @global', ['@global' => $this->getSetting('advanced_tokens')['global_types'] ? 'Yes' : 'No']);
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewValue(FieldItemInterface $item, string $langcode): array {
    $replacements = [];
    $entity = $item->getEntity();
    $entity_type = $entity->getEntityTypeId();
    $template = $this->getSetting('template');
    $bubbleable_metadata = new BubbleableMetadata();

    // Replace advanced tokens.
    if ($this->getSetting('tokens') === 'advanced' && $this->moduleHandler->moduleExists('token')) {
      $output = $this->tokenService->replace($template, [$entity_type => $entity], [
        'clear' => TRUE,
        'langcode' => $langcode,
      ], $bubbleable_metadata);
    }
    // Replace basic tokens.
    else {
      foreach ($this->getCustomFieldItems() as $name => $custom_item) {
        $markup = $custom_item->value($item) ?? '';
        // Convert map values to JSON.
        if (in_array($custom_item->getDataType(), ['map', 'map_string'])) {
          $markup = !empty($markup) ? Json::encode($markup) : NULL;
        }
        $replacements["[$name]"] = $markup;
        $replacements["[$name:label]"] = $custom_item->getLabel();
      }
      $output = strtr($template, $replacements);
    }

    $build['#markup'] = $output;
    $bubbleable_metadata->applyTo($build);

    return $build;
  }

}
