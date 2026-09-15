<?php

namespace Drupal\ai_seo;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Url;

/**
 * Provides a listing of Report Type entities.
 */
class ReportTypeListBuilder extends ConfigEntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['label'] = $this->t('Label');
    $header['id'] = $this->t('Machine name');
    $header['description'] = $this->t('Description');
    $header['status'] = $this->t('Status');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    $row['label'] = $entity->label();
    $row['id'] = $entity->id();
    $row['description'] = $entity->getDescription();
    $row['status'] = $entity->status() ? $this->t('Enabled') : $this->t('Disabled');
    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    $build = parent::render();

    // Add the "Add Report Type" button at the top.
    if ($this->entityType->hasLinkTemplate('add-form')) {
      $build['#title'] = $this->t('Report Types');
      $build['#empty'] = $this->t('No report types available.');

      $build['add'] = [
        '#type' => 'link',
        '#title' => $this->t('Add Report Type'),
        '#url' => Url::fromRoute('entity.ai_seo_report_type.add_form'),
        '#attributes' => [
          'class' => ['button', 'button--primary', 'button--small'],
        ],
        '#weight' => -10,
      ];
    }

    return $build;
  }

}
