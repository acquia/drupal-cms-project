<?php

namespace Drupal\ai_seo\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\node\NodeInterface;

/**
 * Controller for the node SEO analysis page.
 */
class AnalyzeContentController extends ControllerBase {

  /**
   * Build the page.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node entity (upcasted from route parameter).
   *
   * @return array
   *   The form render array.
   */
  public function printReport(NodeInterface $node): array {
    return $this->formBuilder()->getForm('\Drupal\ai_seo\Form\AnalyzeNodeForm');
  }

  /**
   * Page title callback.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The page title.
   */
  public function getTitle() {
    return $this->t('Analyze SEO/GEO');
  }

}
