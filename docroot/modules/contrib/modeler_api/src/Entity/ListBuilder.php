<?php

namespace Drupal\modeler_api\Entity;

use Drupal\Core\Config\Entity\DraggableListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\modeler_api\Plugin\ModelerApiModelOwner\ModelOwnerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a class to build a listing of model config entities.
 */
class ListBuilder extends DraggableListBuilder {

  /**
   * This flag stores the calculated result for ::showModeler().
   *
   * @var bool|null
   */
  protected ?bool $showModeler;

  /**
   * The model owner plugin.
   *
   * @var \Drupal\modeler_api\Plugin\ModelerApiModelOwner\ModelOwnerInterface
   */
  protected ModelOwnerInterface $owner;

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type): static {
    $instance = parent::createInstance($container, $entity_type);
    $instance->owner = $container->get('modeler_api.service')->findOwner($instance->entityTypeId);
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return $this->entityTypeId . '_collection';
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader(): array {
    $header['model'] = $this->t('Model');
    if ($this->showModeler()) {
      $header['modeler'] = $this->t('modeler');
    }
    $header['main_components'] = '';
    $header['version'] = $this->t('Version');
    if ($this->owner->supportsStatus()) {
      $header['status'] = $this->t('Enabled');
    }
    if ($this->owner->supportsTemplate()) {
      $header['template'] = $this->t('Template');
    }
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity): array {
    /** @var \Drupal\Core\Config\Entity\ConfigEntityInterface $model */
    $model = $entity;

    $row['model'] = ['#markup' => $this->owner->getLabel($model)];
    if ($this->showModeler()) {
      $row['modeler'] = ['#markup' => $this->owner->getModeler($model)->label()];
    }
    $row['main_components'] = [
      '#theme' => 'item_list',
      '#items' => $this->owner->usedComponentsInfo($model),
      '#attributes' => [
        'class' => ['modeler-api-main-component-list'],
      ],
    ];
    $row['version'] = ['#markup' => $this->owner->getVersion($model)];
    if ($this->owner->supportsStatus()) {
      $row['status'] = [
        '#markup' => $this->owner->getStatus($model) ? $this->t('yes') : $this->t('no'),
        '#wrapper_attributes' => [
          'data-drupal-selector' => 'models-table-filter-text-source',
        ],
      ];
    }
    if ($this->owner->supportsTemplate()) {
      $row['template'] = [
        '#markup' => $this->owner->getTemplate($model) ? $this->t('yes') : $this->t('no'),
        '#wrapper_attributes' => [
          'data-drupal-selector' => 'models-table-filter-text-source',
        ],
      ];
    }

    foreach (['model', 'main_components', 'version'] as $cell) {
      $row[$cell]['#wrapper_attributes'] = [
        'data-drupal-selector' => 'models-table-filter-text-source',
      ];
    }

    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildForm($form, $form_state);
    $form['actions']['submit']['#value'] = $this->t('Save');
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    parent::submitForm($form, $form_state);
    $this->messenger()->addStatus($this->t('The ordering has been saved.'));
  }

  /**
   * {@inheritdoc}
   */
  public function render(): array {
    $list = parent::render();
    $list['#attached']['library'][] = 'modeler_api/listing';
    $list['filters'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => [
          'table-filter',
          'js-show',
        ],
      ],
      '#weight' => -1,
    ];
    $list['filters']['text'] = [
      '#type' => 'search',
      '#title' => $this
        ->t('Filter'),
      '#title_display' => 'invisible',
      '#size' => 60,
      '#placeholder' => $this
        ->t('Filter by model name, components or version'),
      '#attributes' => [
        'class' => [
          'models-filter-text',
        ],
        'data-table' => '#edit-entities',
        'autocomplete' => 'off',
        'title' => $this
          ->t('Enter a part of the model name, component or version to filter by.'),
      ],
    ];
    return $list;
  }

  /**
   * Determines whether the modeler info should be displayed or not.
   *
   * @return bool
   *   Returns TRUE if the modeler info should be displayed, FALSE otherwise.
   */
  protected function showModeler(): bool {
    if (!isset($this->showModeler)) {
      $modelers = [];
      /** @var \Drupal\Core\Config\Entity\ConfigEntityInterface $model */
      foreach ($this->storage->loadMultiple() as $model) {
        $modelerId = $this->owner->getModeler($model)?->getPluginId() ?? 'fallback';
        $modelers[$modelerId] = TRUE;
      }
      $this->showModeler = count($modelers) > 1;
    }
    return $this->showModeler;
  }

}
