<?php

namespace Drupal\dashboard;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\Entity\DraggableListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Provides a listing of dashboards.
 */
class DashboardListBuilder extends DraggableListBuilder {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'admin_dashboard_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['label'] = $this->t('Label');
    $header['id'] = $this->t('Machine name');
    $header['status'] = $this->t('Status');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\dashboard\DashboardInterface $entity */
    $row['label'] = $entity->label();
    $row['machine_name']['#markup'] = $entity->id();
    $row['status']['#markup'] = $entity->status() ? $this->t('Enabled') : $this->t('Disabled');
    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultOperations(EntityInterface $entity, ?CacheableMetadata $cacheability = NULL) {
    /** @var \Drupal\dashboard\Entity\Dashboard $entity */
    $operations = [];
    $operations['edit_layout'] = [
      'title' => $this->t('Edit layout'),
      'weight' => 0,
      'url' => $this->getLayoutBuilderUrl($entity),
    ];
    $operations['preview'] = [
      'title' => $this->t('Preview'),
      'weight' => 0,
      'url' => $this->getPreviewUrl($entity),
    ];
    // The permissions form route is gated on the core 'administer permissions'
    // permission, which is not implied by 'administer dashboard'. Only offer
    // the operation when the current user can actually reach it, and record the
    // access result so the listing is cached per that permission.
    $manage_permission_url = Url::fromRoute('entity.dashboard.permissions_form', ['dashboard' => $entity->id()]);
    $manage_permission_access = $manage_permission_url->access(return_as_object: TRUE);
    $cacheability?->addCacheableDependency($manage_permission_access);
    if ($manage_permission_access->isAllowed()) {
      $operations['manage_permission'] = [
        'title' => $this->t('Manage permissions'),
        'weight' => 25,
        'url' => $manage_permission_url,
      ];
    }

    return $operations + parent::getDefaultOperations($entity, $cacheability);
  }

  /**
   * Retrieve the layout builder URL for the given dashboard entity.
   *
   * @param \Drupal\dashboard\DashboardInterface $dashboard
   *   The dashboard entity.
   */
  protected function getLayoutBuilderUrl(DashboardInterface $dashboard) {
    return Url::fromRoute("layout_builder.dashboard.view", $this->getRouteParameters($dashboard));
  }

  /**
   * Retrieve the preview URL for the given dashboard entity.
   *
   * @param \Drupal\dashboard\DashboardInterface $dashboard
   *   The dashboard entity.
   */
  protected function getPreviewUrl(DashboardInterface $dashboard) {
    return Url::fromRoute("entity.dashboard.preview", $this->getRouteParameters($dashboard));
  }

  /**
   * Retrieve the route parameters from the given dashboard entity.
   *
   * @param \Drupal\dashboard\DashboardInterface $dashboard
   *   The dashboard entity.
   */
  protected function getRouteParameters(DashboardInterface $dashboard) {
    $route_parameters = [];
    $route_parameters['dashboard'] = $dashboard->id();
    return $route_parameters;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $this->messenger()->addStatus($this->t('The dashboard settings have been updated.'));
  }

}
