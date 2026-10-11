<?php

namespace Drupal\eca_form\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Entity\EntityFormInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eca\Attribute\EcaAction;
use Drupal\file\FileInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Get the uploaded files of a form field.
 */
#[Action(
  id: 'eca_form_field_get_files',
  label: new TranslatableMarkup('Form field: get uploaded files'),
  type: 'form',
)]
#[EcaAction(
  description: new TranslatableMarkup('Get the files uploaded into a file or image form field and store them as a token. Use the plain field name, for example <em>field_pdf</em>. The action resolves the file IDs from the widget input on its own, so neither a delta nor the widget internal <em>fids</em> key is required. On a single-value field the token holds the file entity, for example <em>[my_files:fid]</em>, on a multi-value field it holds the list of file entities, for example <em>[my_files:0:fid]</em>. Nothing is stored when no file was uploaded.'),
  version_introduced: '3.1.9',
)]
class FormFieldGetFiles extends FormFieldActionBase {

  /**
   * {@inheritdoc}
   */
  protected bool $useFilters = FALSE;

  /**
   * {@inheritdoc}
   */
  protected bool $supportsMultiple = FALSE;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected ModuleHandlerInterface $moduleHandler;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->moduleHandler = $container->get('module_handler');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'token_name' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['token_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name of token'),
      '#default_value' => $this->configuration['token_name'],
      '#description' => $this->t('The uploaded files will be loaded into this specified token. Example: when using <em>my_files</em> here, then <em>[my_files:fid]</em> holds the file ID on a single-value field, and <em>[my_files:0:fid]</em> holds the file ID of the first file on a multi-value field.'),
      '#required' => TRUE,
      '#weight' => -45,
      '#eca_token_reference' => TRUE,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['token_name'] = $form_state->getValue('token_name');
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   *
   * Directly calls the parent of the parent, because the submitted value of a
   * file field is available no matter whether the form element of the widget
   * can be found in the current form structure or not.
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    $result = FormActionBase::access($object, $account, TRUE);
    if ($result->isAllowed() && !$this->moduleHandler->moduleExists('file')) {
      // The File module is an optional dependency, without it there is no file
      // entity that could be loaded.
      $result = AccessResult::forbidden('The File module is not installed.');
    }
    return $return_as_object ? $result : $result->isAllowed();
  }

  /**
   * {@inheritdoc}
   */
  protected function doExecute(): void {
    if (!$this->moduleHandler->moduleExists('file')) {
      return;
    }

    $files = $this->loadFiles($this->extractFileIds($this->getSubmittedValue()));
    $token_data = NULL;
    if ($files) {
      // Single-value fields directly expose the file entity, so that the token
      // can be used as [my_files:fid] without an index in between.
      $cardinality = $this->getTargetFieldDefinition()?->getFieldStorageDefinition()->getCardinality();
      $is_single = $cardinality === NULL ? count($files) === 1 : $cardinality === 1;
      $token_data = $is_single ? reset($files) : $files;
    }
    $this->tokenService->addTokenData($this->configuration['token_name'], $token_data);
  }

  /**
   * Extracts all file IDs contained in a submitted form field value.
   *
   * File widgets keep their file IDs in a "fids" element, which may be nested
   * within a delta and within further widget containers. Therefore the whole
   * submitted structure gets traversed for "fids" elements, no matter whether
   * the configured field name targets the field, a single delta or the "fids"
   * element itself.
   *
   * @param mixed $value
   *   The submitted value.
   *
   * @return int[]
   *   The list of file IDs, in the order of their appearance.
   */
  protected function extractFileIds(mixed $value): array {
    if (!is_array($value)) {
      // The configured field name directly targets a "fids" element.
      return $this->parseFileIds($value);
    }

    $ids = [];
    foreach ($value as $key => $item) {
      if ($key === 'fids') {
        foreach ($this->parseFileIds($item) as $id) {
          $ids[] = $id;
        }
      }
      elseif (is_array($item)) {
        foreach ($this->extractFileIds($item) as $id) {
          $ids[] = $id;
        }
      }
    }
    return $ids;
  }

  /**
   * Parses the file IDs out of the value of a "fids" element.
   *
   * Processed form values hold the file IDs as an array of integers, while raw
   * user input holds them as a space-separated string.
   *
   * @param mixed $value
   *   The value of a "fids" element.
   *
   * @return int[]
   *   The list of file IDs.
   *
   * @see \Drupal\file\Element\ManagedFile::valueCallback()
   */
  protected function parseFileIds(mixed $value): array {
    $ids = [];
    if (is_array($value)) {
      foreach ($value as $item) {
        foreach ($this->parseFileIds($item) as $id) {
          $ids[] = $id;
        }
      }
      return $ids;
    }
    if (is_int($value)) {
      if ($value > 0) {
        $ids[] = $value;
      }
      return $ids;
    }
    if (is_string($value)) {
      foreach (preg_split('/\s+/', trim($value), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $part) {
        if (ctype_digit($part) && ((int) $part > 0)) {
          $ids[] = (int) $part;
        }
      }
    }
    return $ids;
  }

  /**
   * Loads the file entities of the given file IDs.
   *
   * @param int[] $ids
   *   The list of file IDs.
   *
   * @return \Drupal\file\FileInterface[]
   *   The list of loaded file entities. File IDs without an existing file are
   *   being skipped.
   */
  protected function loadFiles(array $ids): array {
    if (!$ids) {
      return [];
    }
    $storage = $this->entityTypeManager->getStorage('file');
    $files = [];
    foreach ($ids as $id) {
      $file = $storage->load($id);
      if ($file instanceof FileInterface) {
        $files[] = $file;
      }
    }
    return $files;
  }

  /**
   * Get the field definition of the targeted entity field.
   *
   * @return \Drupal\Core\Field\FieldDefinitionInterface|null
   *   The field definition, or NULL if the form does not build a fieldable
   *   entity or does not know the targeted field.
   */
  protected function getTargetFieldDefinition(): ?FieldDefinitionInterface {
    $form_state = $this->getCurrentFormState();
    $form_object = $form_state?->getFormObject();
    if (!($form_object instanceof EntityFormInterface)) {
      return NULL;
    }
    $entity = $form_object->getEntity();
    if (!($entity instanceof FieldableEntityInterface)) {
      return NULL;
    }
    // The field name may address a nested element, using any of the supported
    // separators. Only the first part of it can be an entity field.
    $name_array = array_filter(explode('[', str_replace([']', ':', '.'], '[', $this->configuration['field_name'])), static function (string $part): bool {
      return $part !== '';
    });
    $field_name = (string) reset($name_array);
    if ($field_name === '' || !$entity->hasField($field_name)) {
      return NULL;
    }
    return $entity->get($field_name)->getFieldDefinition();
  }

}
