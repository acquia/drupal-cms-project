<?php

namespace Drupal\modeler_api\Plugin\ModelerApiModeler;

use Drupal\Component\Utility\Random;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Extension\ExtensionPathResolver;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\modeler_api\Form\Settings;
use Drupal\modeler_api\Plugin\ModelerApiModelOwner\ModelOwnerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Base class modeler plugins.
 *
 * The constructor and the create method are declared final on purpose, as
 * implementing plugins should not use dependency injection, as that would lead
 * towards circular dependencies.
 */
abstract class ModelerBase extends PluginBase implements ModelerInterface {

  /**
   * Dependency Injection container.
   *
   * Used for getter injection.
   *
   * @var \Symfony\Component\DependencyInjection\ContainerInterface|null
   */
  protected ?ContainerInterface $container;

  /**
   * {@inheritdoc}
   */
  final public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected Request $request,
    protected UuidInterface $uuid,
    protected ExtensionPathResolver $extensionPathResolver,
    protected FormBuilderInterface $formBuilder,
    protected LoggerChannelInterface $logger,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   *
   * This method and the constructor is final as the modeler implementations
   * need to be forced to use lazy dependency injection.
   *
   * @see https://www.drupal.org/project/modeler_api/issues/3517655
   */
  final public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('request_stack')->getCurrentRequest(),
      $container->get('uuid'),
      $container->get('extension.path.resolver'),
      $container->get('form_builder'),
      $container->get('logger.channel.modeler_api'),
    );
  }

  /**
   * Get Dependency Injection container.
   *
   * @return \Symfony\Component\DependencyInjection\ContainerInterface
   *   Current Dependency Injection container.
   */
  protected function getContainer(): ContainerInterface {
    if (!isset($this->container)) {
      // @phpstan-ignore-next-line
      $this->container = \Drupal::getContainer();
    }
    return $this->container;
  }

  /**
   * {@inheritdoc}
   */
  final public function label(): string {
    return (string) $this->pluginDefinition['label'];
  }

  /**
   * {@inheritdoc}
   */
  final public function description(): string {
    return (string) $this->pluginDefinition['description'];
  }

  /**
   * {@inheritdoc}
   */
  public function getRawFileExtension(): ?string {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function isEditable(): bool {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function edit(ModelOwnerInterface $owner, string $id, string $data, bool $isNew = FALSE, bool $readOnly = FALSE): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function convert(ModelOwnerInterface $owner, ConfigEntityInterface $model, bool $readOnly = FALSE): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function prepareEmptyModelData(string &$id): string {
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function generateId(): string {
    $random = new Random();
    return $random->name(12);
  }

  /**
   * {@inheritdoc}
   */
  public function enable(ModelOwnerInterface $owner): ModelerInterface {
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function disable(ModelOwnerInterface $owner): ModelerInterface {
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function clone(ModelOwnerInterface $owner, string $id, string $label): ModelerInterface {
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getId(): string {
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function getLabel(): string {
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function getTags(): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getChangelog(): string {
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function getTemplate(): bool {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getStorage(): string {
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function getDocumentation(): string {
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function getStatus(): bool {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getVersion(): string {
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function readComponents(): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function updateComponents(ModelOwnerInterface $owner): bool {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function configForm(ModelOwnerInterface $owner): JsonResponse {
    return new AjaxResponse();
  }

  /**
   * Provides the default form definition for model configuration.
   *
   * @param \Drupal\modeler_api\Plugin\ModelerApiModelOwner\ModelOwnerInterface $owner
   *   The model owner.
   * @param array $config
   *   The config for the form fields.
   * @param bool $isNew
   *   TRUE, if the model is new, FALSE otherwise.
   *
   * @return array
   *   The form.
   */
  protected function defaultModelConfigForm(ModelOwnerInterface $owner, array $config, bool $isNew): array {
    $form['#title'] = $this->t('Model information');
    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#default_value' => $config['label'],
    ];
    $form['model_id'] = [
      '#type' => 'machine_name',
      '#default_value' => $isNew ? '' : $config['model_id'],
      '#disabled' => !$isNew,
      '#machine_name' => [
        'exists' => $owner->modelIdExistsCallback(),
        'source' => ['label'],
        'label' => $this->t('Model ID'),
      ],
    ];
    $form['version'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Version'),
      '#default_value' => $config['version'],
    ];
    if ($owner->supportsStatus()) {
      $form['executable'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Enabled'),
        '#default_value' => $config['executable'],
      ];
    }
    if ($owner->supportsTemplate()) {
      $form['template'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Template'),
        '#description' => $this->t('If checked, the model will be used as a template for new models.'),
        '#default_value' => $config['template'],
      ];
    }
    $form['storage'] = [
      '#type' => 'select',
      '#title' => $this->t('Storage of raw data'),
      '#default_value' => $config['storage'] ?? '',
      '#options' => [
        '' => $this->t('Default'),
        Settings::STORAGE_OPTION_NONE => $this->t('Do not store raw model data'),
        Settings::STORAGE_OPTION_SEPARATE => $this->t('Store raw data in separate config entity'),
        Settings::STORAGE_OPTION_THIRD_PARTY => $this->t('Store raw data with config as third-party setting'),
      ],
      '#disabled' => $owner->enforceDefaultStorageMethod(),
    ];
    $form['documentation'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Documentation'),
      '#default_value' => $config['documentation'],
    ];
    $form['tags'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Tags'),
      '#default_value' => $config['tags'],
      '#description' => $this->t('Comma-separated list of tags.'),
    ];
    $form['changelog'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Changelog'),
      '#default_value' => $config['changelog'],
    ];
    $owner->modelConfigFormAlter($form);
    return $form;
  }

}
