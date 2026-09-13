<?php

namespace Drupal\bpmn_io\Plugin\ModelerApiModeler;

use Drupal\bpmn_io\Service\Parser;
use Drupal\bpmn_io\Service\PrepareComponents;
use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Component\Utility\Random;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenOffCanvasDialogCommand;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\bpmn_io\Form\Modeler as ModelerForm;
use Drupal\modeler_api\Api;
use Drupal\modeler_api\Attribute\Modeler;
use Drupal\modeler_api\Form\Wrapper;
use Drupal\modeler_api\Plugin\ModelerApiModelOwner\ModelOwnerInterface;
use Drupal\modeler_api\Plugin\ModelerApiModeler\ModelerBase;
use Drupal\modeler_api\Plugin\ModelerApiModeler\ModelerInterface;

/**
 * Plugin implementation of the Modeler API.
 */
#[Modeler(
  id: "bpmn_io",
  label: new TranslatableMarkup("BPMN.iO"),
  description: new TranslatableMarkup("BPMN modeler with a feature-rich UI.")
)]
class BpmnIo extends ModelerBase {

  public const array SUPPORTED_COMPONENT_TYPES = [
    Api::COMPONENT_TYPE_START => 'bpmn:StartEvent',
    Api::COMPONENT_TYPE_LINK => 'bpmn:SequenceFlow',
    Api::COMPONENT_TYPE_ELEMENT => 'bpmn:Task',
    Api::COMPONENT_TYPE_GATEWAY => 'bpmn:Gateway',
    Api::COMPONENT_TYPE_SUBPROCESS => 'bpmn:SubProcess',
    Api::COMPONENT_TYPE_ANNOTATION => 'bpmn:TextAnnotation',
  ];

  public const array MAP_COMPONENT_TYPES = [
    'bpmn:StartEvent' => 'StartEvent',
    'bpmn:SequenceFlow' => 'SequenceFlow',
    'bpmn:Task' => 'Task',
    'bpmn:Gateway' => 'ExclusiveGateway',
    'bpmn:SubProcess' => 'SubProcess',
    'bpmn:Participant' => 'Participant',
    'bpmn:TextAnnotation' => 'TextAnnotation',
  ];

  /**
   * The prepare components service.
   *
   * @var \Drupal\bpmn_io\Service\PrepareComponents
   */
  protected PrepareComponents $prepareComponents;

  /**
   * The BPMN parser.
   *
   * @var \Drupal\bpmn_io\Service\Parser
   */
  protected Parser $parser;

  /**
   * Get the prepare components service.
   *
   * @return \Drupal\bpmn_io\Service\PrepareComponents
   *   The prepare components service.
   */
  protected function prepareComponents(): PrepareComponents {
    if (!isset($this->prepareComponents)) {
      $this->prepareComponents = $this->getContainer()->get('bpmn_io.prepare_components');
    }
    return $this->prepareComponents;
  }

  /**
   * Get the BPMN parser.
   *
   * @return \Drupal\bpmn_io\Service\Parser
   *   The BPMN parser.
   */
  protected function parser(): Parser {
    if (!isset($this->parser)) {
      $this->parser = $this->getContainer()->get('bpmn_io.parser');
    }
    return $this->parser;
  }

  /**
   * {@inheritdoc}
   */
  public function getRawFileExtension(): ?string {
    return 'xml';
  }

  /**
   * {@inheritdoc}
   */
  public function isEditable(): bool {
    return TRUE;
  }

  /**
   * Returns a render array with everything required for model editing.
   *
   * @return array
   *   The render array.
   */
  public function edit(ModelOwnerInterface $owner, string $id, string $data, bool $isNew = FALSE, bool $readOnly = FALSE): array {
    $this->sanityCheckTheme();
    $form = $this->formBuilder->getForm(ModelerForm::class, $owner, $id, $readOnly);
    if (isset($form['gin_sidebar'])) {
      $form['gin_sidebar']['property_panel'] = ['#markup' => '<div class="property-panel"></div>'];
      $extras = '';
    }
    else {
      $extras = '<div class="property-panel in-canvas"></div>';
    }
    $mutuallySupported = array_intersect_key(self::SUPPORTED_COMPONENT_TYPES, $owner->supportedOwnerComponentTypes() + [
      Api::COMPONENT_TYPE_ANNOTATION => 'always',
    ]);
    $supportedTypes = [];
    foreach ($mutuallySupported as $item => $value) {
      $supportedTypes[] = match ($item) {
        Api::COMPONENT_TYPE_START => 'events',
        Api::COMPONENT_TYPE_SUBPROCESS => 'subprocesses',
        Api::COMPONENT_TYPE_ELEMENT => 'tasks',
        Api::COMPONENT_TYPE_LINK => 'links',
        Api::COMPONENT_TYPE_GATEWAY => 'gateways',
        // @phpstan-ignore-next-line
        Api::COMPONENT_TYPE_ANNOTATION => 'annotations',
        default => '',
      };
    }
    return [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'bpmn-io',
        'class' => $supportedTypes,
      ],
      'canvas' => [
        '#prefix' => '<div class="canvas" role="application" aria-label="BPMN Canvas"></div>' . $extras,
        'widgets' => [
          '#type' => 'container',
          '#attributes' => [
            'id' => 'bpmn-io-widgets',
          ],
          'info' => [
            '#type' => 'html_tag',
            '#tag' => 'button',
            '#attributes' => [
              'class' => ['widget', 'info'],
              'title' => $this->t('Model information'),
            ],
            'icon' => [
              '#type' => 'icon',
              '#pack_id' => 'bpmn_io',
              '#icon_id' => 'information',
            ],
          ],
          'layout' => [
            '#type' => 'html_tag',
            '#tag' => 'button',
            '#attributes' => [
              'class' => ['widget', 'layout'],
              'title' => $this->t('Auto-Layout'),
            ],
            'icon' => [
              '#type' => 'icon',
              '#pack_id' => 'bpmn_io',
              '#icon_id' => 'layout',
            ],
          ],
          'svg' => [
            '#type' => 'html_tag',
            '#tag' => 'button',
            '#attributes' => [
              'class' => ['widget', 'svg'],
              'title' => $this->t('Download SVG'),
            ],
            'icon' => [
              '#type' => 'icon',
              '#pack_id' => 'bpmn_io',
              '#icon_id' => 'download',
            ],
          ],
          'copy' => [
            '#type' => 'html_tag',
            '#tag' => 'button',
            '#attributes' => [
              'class' => ['widget', 'copy'],
              'title' => $this->t('Copy selected elements to clipboard'),
            ],
            'icon' => [
              '#type' => 'icon',
              '#pack_id' => 'bpmn_io',
              '#icon_id' => 'copy',
            ],
          ],
          'paste' => [
            '#type' => 'html_tag',
            '#tag' => 'button',
            '#attributes' => [
              'class' => ['widget', 'paste'],
              'title' => $this->t('Paste elements from the clipboard'),
            ],
            'icon' => [
              '#type' => 'icon',
              '#pack_id' => 'bpmn_io',
              '#icon_id' => 'paste',
            ],
          ],
          'zoom_in' => [
            '#type' => 'html_tag',
            '#tag' => 'button',
            '#attributes' => [
              'class' => ['widget', 'zoom-in'],
              'title' => $this->t('Zoom In'),
            ],
            'icon' => [
              '#type' => 'icon',
              '#pack_id' => 'bpmn_io',
              '#icon_id' => 'zoom-in',
            ],
          ],
          'zoom_out' => [
            '#type' => 'html_tag',
            '#tag' => 'button',
            '#attributes' => [
              'class' => ['widget', 'zoom-out'],
              'title' => $this->t('Zoom Out'),
            ],
            'icon' => [
              '#type' => 'icon',
              '#pack_id' => 'bpmn_io',
              '#icon_id' => 'zoom-out',
            ],
          ],
          'zoom_fit' => [
            '#type' => 'html_tag',
            '#tag' => 'button',
            '#attributes' => [
              'class' => ['widget', 'zoom-fit'],
              'title' => $this->t('Zoom to fit model in canvas'),
            ],
            'icon' => [
              '#type' => 'icon',
              '#pack_id' => 'bpmn_io',
              '#icon_id' => 'zoom-fit',
            ],
          ],
          'search' => [
            '#type' => 'html_tag',
            '#tag' => 'button',
            '#attributes' => [
              'class' => ['widget', 'search'],
              'title' => $this->t('Search for elements'),
            ],
            'icon' => [
              '#type' => 'icon',
              '#pack_id' => 'bpmn_io',
              '#icon_id' => 'search',
            ],
          ],
          'minimap' => [
            '#type' => 'html_tag',
            '#tag' => 'button',
            '#attributes' => [
              'class' => ['widget', 'minimap'],
              'title' => $this->t('Display a minimized map of the full model canvas'),
            ],
            'icon' => [
              '#type' => 'icon',
              '#pack_id' => 'bpmn_io',
              '#icon_id' => 'minimap',
            ],
          ],
        ],
      ],
      'form' => $form,
      '#attached' => [
        'library' => [
          'bpmn_io/ui',
        ],
        'drupalSettings' => [
          'bpmn_io' => [
            'id' => $id,
            'is_new' => $isNew,
            'modeler' => 'bpmn_io',
            'owner' => $owner->getPluginId(),
            'bpmn' => $data,
            'templates' => $this->prepareComponents()->getTemplates($owner),
            'supportedTypes' => $supportedTypes,
          ],
        ],
      ],
    ];
  }

  /**
   * Returns a render array with everything required for model editing.
   *
   * @return array
   *   The render array.
   */
  public function convert(ModelOwnerInterface $owner, ConfigEntityInterface $model, bool $readOnly = FALSE): array {
    $owner->setModelerId($model, 'bpmn_io');
    $type = $model->getEntityTypeId();

    $id = '';
    $emptyModelData = $this->prepareEmptyModelData($id);
    if ($model->isNew()) {
      $model->set('id', $id);
    }
    else {
      $emptyModelData = str_replace($id, $model->id(), $emptyModelData);
    }

    // Provide enough mappings so that the Javascript-class can do its thing.
    $build = $this->edit($owner, $model->id(), $emptyModelData, $model->isNew(), $readOnly);
    $build['canvas']['#suffix'] = '<div class="convert-overlay">' . $this->t('Conversion in progress ...') . '</div>';
    $build['#attached']['library'][] = 'bpmn_io/convert';
    $build['#attached']['drupalSettings']['bpmn_io_convert'] = [
      'reload' => FALSE,
      'metadata' => [
        'label' => $owner->getLabel($model),
        'version' => $owner->getVersion($model),
        'executable' => $owner->getStatus($model),
        'template' => method_exists($owner, 'getTemplate') && $owner->getTemplate($model),
        'storage' => $owner->getStorage($model),
        'documentation' => $owner->getDocumentation($model),
        'tags' => $owner->getTags($model),
        'changelog' => $owner->getChangelog($model),
      ],
    ];
    if ($owner->configEntityBasePath() !== NULL) {
      $build['#attached']['drupalSettings']['bpmn_io_convert']['metadata']['redirect_url'] = $this->getContainer()->get('modeler_api.service')->editUrl($type, $model->id())->toString();
    }

    $components = $owner->usedComponents($model);
    $supportedOwnerComponentTypes = $owner->supportedOwnerComponentTypes();
    $elements = $bpmnMapping = $templateMapping = [];
    foreach ($components as $component) {
      if (!isset(self::SUPPORTED_COMPONENT_TYPES[$component->getType()])) {
        $this->logger->error('Unsupported component type %type in model %id for component %component', [
          '%type' => $component->getType(),
          '%id' => $model->id(),
          '%component' => $component->getId(),
        ]);
        continue;
      }
      $successors = [];
      foreach ($component->getSuccessors() as $successor) {
        $successors[] = [
          'id' => $successor->getId(),
          'condition' => $successor->getConditionId(),
        ];
      }
      $configuration = $component->getConfiguration();
      array_walk($configuration, function (&$value, $key) use ($component) {
        $value = $this->prepareComponents()->sanitizeConfigValue($component->getId(), $key, $value);
      });
      $elements[$component->getId()] = [
        'plugin' => $component->getPluginId(),
        'label' => $component->getLabel(),
        'configuration' => $configuration,
        'successors' => $successors,
        'parentId' => $component->getParentId(),
      ];
      $bpmnMapping[$component->getId()] = self::MAP_COMPONENT_TYPES[self::SUPPORTED_COMPONENT_TYPES[$component->getType()]];
      $templateMapping[$component->getId()] = $supportedOwnerComponentTypes[$component->getType()];
    }
    $annotations = [];
    foreach ($owner->getAnnotations($model) as $annotation) {
      $associations = [];
      foreach ($annotation->getSuccessors() as $association) {
        $associations[$association->getId()] = $association->getConditionId();
      }
      $annotations[$annotation->getId()] = [
        'text' => $annotation->getLabel(),
        'sources' => $associations,
      ];
    }
    $colors = [];
    foreach ($owner->getColors($model) as $id => $color) {
      $colors[$id] = [
        'fill' => $color->getFill(),
        'stroke' => $color->getStroke(),
      ];
    }
    $build['#attached']['drupalSettings']['bpmn_io_convert']['elements'] = $elements;
    $build['#attached']['drupalSettings']['bpmn_io_convert']['annotations'] = $annotations;
    $build['#attached']['drupalSettings']['bpmn_io_convert']['colors'] = $colors;
    $build['#attached']['drupalSettings']['bpmn_io_convert']['bpmn_mapping'] = $bpmnMapping;
    $build['#attached']['drupalSettings']['bpmn_io_convert']['template_mapping'] = $templateMapping;

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function generateId(): string {
    $random = new Random();
    return 'Process_' . $random->name(7);
  }

  /**
   * {@inheritdoc}
   */
  public function enable(ModelOwnerInterface $owner): ModelerInterface {
    $this->parser()->enable($owner);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function disable(ModelOwnerInterface $owner): ModelerInterface {
    $this->parser()->disable($owner);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function clone(ModelOwnerInterface $owner, string $id, string $label): ModelerInterface {
    $this->parser()->clone($owner, $id, $label);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function prepareEmptyModelData(string &$id): string {
    $id = $this->generateId();
    $emptyBpmn = file_get_contents($this->extensionPathResolver->getPath('module', 'bpmn_io') . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'empty.bpmn');
    return str_replace([
      'SIDPLACEHOLDER1',
      'SIDPLACEHOLDER2',
      'IDPLACEHOLDER',
    ], [
      'sid-' . $this->uuid->generate(),
      'sid-' . $this->uuid->generate(),
      $id,
    ], $emptyBpmn);
  }

  /**
   * {@inheritdoc}
   */
  public function getId(): string {
    return $this->parser()->getId();
  }

  /**
   * {@inheritdoc}
   */
  public function getLabel(): string {
    return $this->parser()->getLabel();
  }

  /**
   * {@inheritdoc}
   */
  public function getTags(): array {
    return $this->parser()->getTags();
  }

  /**
   * {@inheritdoc}
   */
  public function getChangelog(): string {
    return $this->parser()->getChangelog();
  }

  /**
   * {@inheritdoc}
   */
  public function getTemplate(): bool {
    return $this->parser()->getTemplate();
  }

  /**
   * {@inheritdoc}
   */
  public function getStorage(): string {
    return $this->parser()->getStorage();
  }

  /**
   * {@inheritdoc}
   */
  public function getDocumentation(): string {
    return $this->parser()->getDocumentation();
  }

  /**
   * {@inheritdoc}
   */
  public function getStatus(): bool {
    return $this->parser()->getStatus();
  }

  /**
   * {@inheritdoc}
   */
  public function getVersion(): string {
    return $this->parser()->getVersion();
  }

  /**
   * {@inheritdoc}
   */
  final public function getRawData(): string {
    return $this->parser()->getData();
  }

  /**
   * {@inheritdoc}
   */
  public function parseData(ModelOwnerInterface $owner, string $data): void {
    $this->parser()->setData($owner, $data);
  }

  /**
   * {@inheritdoc}
   */
  public function readComponents(): array {
    return $this->parser()->getComponents();
  }

  /**
   * {@inheritdoc}
   */
  public function updateComponents(ModelOwnerInterface $owner): bool {
    $changed = $this->parser()->updateComponents($owner, $this->prepareComponents()->getTemplates($owner));
    $this->parseData($owner, $this->parser()->getData());
    return $changed;
  }

  /**
   * {@inheritdoc}
   */
  public function configForm(ModelOwnerInterface $owner): AjaxResponse {
    $response = new AjaxResponse();
    parse_str($this->request->getContent(), $data);
    if (!isset($data['type'])) {
      return $response;
    }
    $key = array_search($data['type'], self::SUPPORTED_COMPONENT_TYPES);
    $config = $data['config'] ?? [];
    if ($key === FALSE) {
      if ($data['type'] === 'bpmn:Process' || $data['type'] === 'bpmn:Collaboration') {
        $config['executable'] = $config['executable'] === 'true';
        $config['template'] = $config['template'] === 'true';
        $form = $this->defaultModelConfigForm($owner, $config, $data['isNew'] === 'true');
      }
      else {
        $form = [
          '#title' => $this->t('Unsupported component type'),
        ];
      }
    }
    elseif ($plugin = $owner->ownerComponent($key, $data['pluginId'])) {
      if ($owner->ownerComponentEditable($plugin)) {
        if ($plugin instanceof ConfigurableInterface) {
          $this->prepareComponents()
            ->upcastConfiguration($config, $plugin->defaultConfiguration(), FALSE);
          $plugin->setConfiguration($config);
        }
        $form = $owner->buildConfigurationForm($plugin, $data['entityId'], $data['isNew'] === 'true');
        // The model owner may have added configuration properties to the plugin
        // although it's not configurable. This e.g. happens in ECA for action
        // plugins that have a type property. Let's add the default values
        // separately for those.
        if (!($plugin instanceof ConfigurableInterface)) {
          foreach ($config as $configKey => $configValue) {
            if (isset($form[$configKey])) {
              $form[$configKey]['#default_value'] = $configValue;
            }
          }
        }
        $widgets = [];
        $docUrl = $owner->pluginDocUrl($plugin, $owner->ownerComponentId($key));
        if ($docUrl !== NULL) {
          $widgets['help'] = [
            '#type' => 'html_tag',
            '#tag' => 'a',
            '#attributes' => [
              'class' => ['widget', 'help'],
              'title' => $this->t('Open documentation for this component'),
              'href' => $docUrl,
              'target' => '_blank',
            ],
            'icon' => [
              '#type' => 'icon',
              '#pack_id' => 'bpmn_io',
              '#icon_id' => 'information',
            ],
          ];
        }
        if ($owner->ownerComponentPluginChangeable($plugin)) {
          $widgets['remove_template'] = [
            '#type' => 'html_tag',
            '#tag' => 'span',
            '#attributes' => [
              'class' => ['widget', 'remove-template'],
              'title' => $this->t('Remove template from element'),
            ],
            'icon' => [
              '#type' => 'icon',
              '#pack_id' => 'bpmn_io',
              '#icon_id' => 'delete',
            ],
          ];
        }
        if ($widgets) {
          $form['widgets'] = [
            '#type' => 'container',
            '#id' => 'bpmn-io-form-widgets',
            '#weight' => 9000,
            'widgets' => $widgets,
          ];
        }
      }
      else {
        $form = [];
      }
      if (!isset($form['#title'])) {
        if (($config['label'] ?? '') !== '') {
          $form['#title'] = $config['label'];
        }
        else {
          $form['#title'] = $plugin->getPluginDefinition()['label'] ?? $plugin->getPluginDefinition()['name'] ?? $plugin->getPluginId();
        }
      }
    }
    else {
      $form = [
        '#title' => $this->t('Unknown component'),
      ];
    }
    foreach (Element::children($form) as $child) {
      if (
        isset($form[$child]['#type']) &&
        in_array($form[$child]['#type'], ['entity_autocomplete', 'number'], TRUE)
      ) {
        $form[$child]['#type'] = 'textfield';
      }
    }
    if ($data['readOnly'] === 'true') {
      // @todo Make this recursive.
      foreach (Element::children($form) as $child) {
        $form[$child]['#disabled'] = TRUE;
      }
    }
    $form = $this->formBuilder->getForm(Wrapper::class, $form);
    $form['#attached']['library'][] = 'core/drupal.dialog.off_canvas';

    $options['width'] = $data['width'] === '' ? OpenOffCanvasDialogCommand::DEFAULT_DIALOG_WIDTH : $data['width'];
    $response->addCommand(new OpenOffCanvasDialogCommand($form['#title'], $form, $options));
    return $response;
  }

  /**
   * Checks whether the current theme is supported and outputs a warning if not.
   */
  protected function sanityCheckTheme(): void {
    $supportedThemes = ['claro', 'gin'];
    $this->getContainer()->get('module_handler')->alter('bpmn_io_supported_themes', $supportedThemes);
    $active_theme = $this->getContainer()->get('theme.manager')->getActiveTheme();
    if (!in_array($active_theme->getName(), $supportedThemes)) {
      $theme = $this->getContainer()->get('theme_handler')->listInfo()[$active_theme->getName()] ?? NULL;
      if ($theme === NULL || !isset($theme->base_theme) || !in_array($theme->base_theme, $supportedThemes, TRUE)) {
        $this->messenger()->addWarning($this->t('The BPMN.iO modeler is not supported in the current theme.'));
      }
    }
  }

}
