<?php

namespace Drupal\ai_dashboard\Controller;

use Drupal\Component\Utility\Html;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Plugin\Context\EntityContext;
use Drupal\layout_builder\Context\LayoutBuilderContextTrait;
use Drupal\layout_builder\SectionStorage\SectionStorageManagerInterface;
use Drupal\system\SystemManager;

/**
 * Contains methods to override AI Config Menu page.
 */
class AiDashboard extends ControllerBase {

  use LayoutBuilderContextTrait;

  public function __construct(
    protected SystemManager $systemManager,
    protected SectionStorageManagerInterface $sectionStorageManager,
  ) {
  }

  /**
   * Display ai_dashboard instead of AI Config Menu.
   */
  public function index() {
    $build = [];
    $dashboard = $this->entityTypeManager()->getStorage('dashboard')->load('ai_dashboard');
    if (!empty($dashboard)) {
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
          'library' => [
            'dashboard/dashboard',
            'ai_dashboard/ai_dashboard',
          ],
        ],
      ];
      if ($dashboard->get('description')) {
        $build['dashboard']['description'] = [
          '#markup' => '<p class="description">' . $dashboard->get('description') . '</p>',
        ];
      }
      foreach ($section_storage->getSections() as $delta => $section) {
        $contexts = $this->getPopulatedContexts($section_storage);
        $build['dashboard'][$delta] = $section->toRenderArray($contexts);
      }
    }
    else {
      $build = $this->systemManager->getBlockContents();
    }
    return $build;
  }

}
