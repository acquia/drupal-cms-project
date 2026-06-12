<?php

namespace Drupal\dashboard\Controller;

use Drupal\Component\Utility\Html;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\Context\EntityContext;
use Drupal\dashboard\DashboardInterface;
use Drupal\dashboard\DashboardManager;
use Drupal\layout_builder\Context\LayoutBuilderContextTrait;
use Drupal\layout_builder\SectionStorage\SectionStorageManagerInterface;

/**
 * Returns responses for Dashboard routes.
 */
class DashboardController extends ControllerBase {

  use AutowireTrait;
  use LayoutBuilderContextTrait;

  /**
   * Constructs a new DashboardController instance.
   *
   * @param \Drupal\dashboard\DashboardManager $dashboardManager
   *   The dashboard manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\layout_builder\SectionStorage\SectionStorageManagerInterface $sectionStorageManager
   *   The section storage manager.
   */
  public function __construct(
    protected DashboardManager $dashboardManager,
    EntityTypeManagerInterface $entityTypeManager,
    protected SectionStorageManagerInterface $sectionStorageManager,
  ) {
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * Access callback for the Dashboard page.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   Whether the user is allowed to access or not.
   */
  public function access() {
    $dashboard_exists = $this->dashboardManager->getDefaultDashboard() !== NULL;
    return AccessResult::allowedIf($dashboard_exists)
      ->addCacheTags($this->entityTypeManager()->getDefinition('dashboard')->getListCacheTags())
      ->cachePerPermissions();
  }

  /**
   * Builds the response.
   */
  public function build(?DashboardInterface $dashboard) {
    $build = [];
    /** @var \Drupal\layout_builder\SectionStorage\SectionStorageManagerInterface $sectionStorageManager */
    if ($dashboard === NULL) {
      $dashboard = $this->dashboardManager->getDefaultDashboard();
    }

    if ($dashboard !== NULL) {
      $contexts = [];
      $contexts['dashboard'] = EntityContext::fromEntity($dashboard);

      $section_storage = $this->sectionStorageManager->load('dashboard', $contexts);

      $build['dashboard'] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => [
            'dashboard',
            Html::getClass('dashboard--' . $dashboard->id()),
          ],
        ],
        '#attached' => [
          'library' => ['dashboard/dashboard'],
        ],
      ];

      foreach ($section_storage->getSections() as $delta => $section) {
        $contexts = $this->getPopulatedContexts($section_storage);
        $build['dashboard'][$delta] = $section->toRenderArray($contexts);
      }
    }
    else {
      $build['dashboard'] = [
        '#type' => 'item',
        '#markup' => $this->t('There is no dashboard to show.'),
      ];
    }
    $cacheability = (new CacheableMetadata())
      ->addCacheTags($this->entityTypeManager()->getDefinition('dashboard')->getListCacheTags())
      ->addCacheContexts(['user.permissions']);
    if ($dashboard) {
      $cacheability->addCacheableDependency($dashboard);
    }
    $cacheability->applyTo($build);
    return $build;
  }

}
