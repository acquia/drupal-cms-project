<?php

namespace Drupal\ai_image_bulk_alt_text\Form;

use Drupal\Component\Utility\Unicode;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for bulk fixing alt text on images using AI.
 */
class AltTextFixer extends FormBase {

  /**
   * Maximum number of images listed at once.
   */
  const LIMIT = 100;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The bundle info interface.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $bundleInfo;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ai_image_bulk_alt_text_fixer';
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->bundleInfo = $container->get('entity_type.bundle.info');
    $instance->entityFieldManager = $container->get('entity_field.manager');
    $instance->setMessenger($container->get('messenger'));
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $image_fields = $this->getEntitiesAndFields(static::LIMIT);

    // If there are no image fields, return a message.
    if (empty($image_fields)) {
      $form['message'] = [
        '#markup' => $this->t('<strong>You are good!</strong> No image fields found with missing alt text.'),
      ];
      return $form;
    }

    $header = [
      'image' => $this->t('Image'),
      'entity_type' => $this->t('Entity'),
      'suggested_alt_text' => $this->t('New Alt Text'),
      'actions' => $this->t('Actions'),
    ];

    $form['suggest'] = [
      '#type' => 'button',
      '#value' => $this->t('Bulk Generate Alt Text with AI'),
      '#attributes' => [
        'class' => ['suggest-alt-text'],
      ],
    ];

    $form['table'] = [
      '#type' => 'table',
      '#header' => $header,
    ];

    // The mapping from a row to the entity, field and delta it updates lives
    // only on the server. It is rebuilt on submit, so a client can neither
    // change which entity a row writes to nor add rows of its own.
    $form['#ai_image_rows'] = [];

    foreach ($image_fields as $image) {
      $unique_id = $image['unique_id'];
      $form['#ai_image_rows'][$unique_id] = [
        'entity_type' => $image['type'],
        'entity_id' => $image['base_entity']->id(),
        'field' => $image['field'],
        'delta' => $image['delta'],
        'fid' => $image['entity']->id(),
      ];
      $form['table'][$unique_id] = [
        'image' => [
          '#theme' => 'image_style',
          '#style_name' => 'large',
          '#uri' => $image['entity']->getFileUri(),
          '#alt' => $image['entity']->getFilename(),
        ],
        'entity_type' => [
          '#plain_text' => Unicode::truncate((string) $image['base_entity']->label(), 30) . ' (' . $image['field'] . ')',
        ],
        'suggested_alt_text' => [
          '#type' => 'textarea',
          '#default_value' => '',
          '#attributes' => [
            'class' => ['alt-text-' . $unique_id, 'alt-text-textarea'],
          ],
          '#suffix' => '<div class="textarea-loader load-' . $unique_id . '"><div class="loader"></div></div>',
        ],
        'actions' => [
          '#type' => 'button',
          '#value' => $this->t('Generate with AI'),
          '#attributes' => [
            'class' => ['alt-text-item'],
            'data-unique-id' => $unique_id,
            'data-entity-language' => $image['base_entity']->language()->getId() ?? 'en',
            'data-file-id' => $image['entity']->id(),
          ],
        ],
      ];
    }

    $form['#attached']['library'][] = 'ai_image_bulk_alt_text/suggest';

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save changes'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $count = 0;
    foreach ($form['#ai_image_rows'] ?? [] as $unique_id => $row) {
      $alt = trim((string) $form_state->getValue(['table', $unique_id, 'suggested_alt_text'], ''));
      // Meaning we want to save.
      if ($alt === '') {
        continue;
      }
      $entity = $this->entityTypeManager->getStorage($row['entity_type'])->load($row['entity_id']);
      if (!$entity instanceof FieldableEntityInterface || !$entity->hasField($row['field'])) {
        continue;
      }
      // Re-check access at save time, the listing may be stale.
      $items = $entity->get($row['field']);
      if (!$entity->access('update') || !$items->access('edit')) {
        continue;
      }
      $item = $items->get($row['delta']);
      if (!$item || (int) $item->target_id !== (int) $row['fid']) {
        continue;
      }
      $item->alt = Unicode::truncate($alt, 512, TRUE, TRUE);
      $entity->save();
      $count++;
    }
    $this->messenger()->addMessage($this->t('@count alt text fields have been updated.', ['@count' => $count]));
  }

  /**
   * Gets a list of images with missing alt text the user may update.
   *
   * @param int $limit
   *   Maximum number of images to return.
   *
   * @return array
   *   An array of entities and fields.
   */
  protected function getEntitiesAndFields($limit = self::LIMIT) {
    $missing_alt_text = [];
    foreach ($this->getImageFields() as $field) {
      $storage = $this->entityTypeManager->getStorage($field['entity_type']);
      $entity_type = $storage->getEntityType();
      $field_name = $field['field'];

      // Only load entities that have at least one image without alt text,
      // instead of loading every entity of the type.
      $query = $storage->getQuery()
        ->accessCheck(TRUE)
        ->exists($field_name . '.target_id');
      $alt_group = $query->orConditionGroup()
        ->notExists($field_name . '.alt')
        ->condition($field_name . '.alt', '');
      $query->condition($alt_group);
      if ($bundle_key = $entity_type->getKey('bundle')) {
        $query->condition($bundle_key, $field['bundle']);
      }
      $query->range(0, $limit);
      $ids = $query->execute();

      foreach ($storage->loadMultiple($ids) as $entity) {
        if (!$entity instanceof ContentEntityInterface || !$entity->hasField($field_name)) {
          continue;
        }
        $items = $entity->get($field_name);
        // Only list images the user is allowed to fix.
        if (!$entity->access('update') || !$items->access('edit')) {
          continue;
        }
        foreach ($items as $delta => $item) {
          if (!empty($item->alt) || empty($item->entity) || !$item->entity->access('view')) {
            continue;
          }
          $missing_alt_text[] = [
            'entity' => $item->entity,
            'field' => $field_name,
            'type' => $field['entity_type'],
            'base_entity' => $entity,
            'bundle' => $field['bundle'],
            'item' => $item,
            'delta' => $delta,
            'unique_id' => md5($field['entity_type'] . '-' . $entity->id() . '-' . $field_name . '-' . $delta),
          ];
          if (count($missing_alt_text) >= $limit) {
            return $missing_alt_text;
          }
        }
      }
    }
    return $missing_alt_text;
  }

  /**
   * Get the list of all the image fields for all entity types.
   *
   * @return array
   *   An array of image fields.
   */
  protected function getImageFields() {
    $fields = [];
    $entity_types = $this->getEntityTypes();
    foreach ($entity_types as $entity_type_id => $bundles) {
      foreach ($bundles as $bundle_id => $bundle) {
        foreach ($this->entityFieldManager->getFieldDefinitions($entity_type_id, $bundle_id) as $field_name => $field_definition) {
          if ($field_definition->getType() == 'image') {
            // Check if alt text is enabled.
            if (!$field_definition->getSetting('alt_field')) {
              continue;
            }
            $fields[$entity_type_id . '.' . $bundle_id . '.' . $field_name] = [
              'entity_type' => $entity_type_id,
              'bundle' => $bundle_id,
              'field' => $field_name,
            ];
          }
        }
      }
    }
    return $fields;
  }

  /**
   * Get a list of all content entities and bundles.
   *
   * @return array
   *   An array of entity types and bundles.
   */
  protected function getEntityTypes() {
    $entity_types = $this->entityTypeManager->getDefinitions();
    $bundles = [];
    foreach ($entity_types as $entity_type_id => $entity_type) {
      // Check if its a content entity type.
      if (!$entity_type->entityClassImplements(ContentEntityInterface::class)) {
        continue;
      }
      $bundles[$entity_type_id] = $this->bundleInfo->getBundleInfo($entity_type_id);
    }
    return $bundles;
  }

}
