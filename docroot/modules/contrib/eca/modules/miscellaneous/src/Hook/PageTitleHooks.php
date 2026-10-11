<?php

namespace Drupal\eca_misc\Hook;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Hook\Order\Order;
use Drupal\Core\Render\Markup;
use Drupal\eca_misc\PageTitleStore;

/**
 * Applies the ECA-defined page title at the latest possible moment.
 *
 * Drupal core resolves the page title to a scalar value inside
 * \Drupal\Core\Render\MainContent\HtmlRenderer::prepare(), after the main
 * content has been pre-rendered. Therefore the only reliable place to override
 * both the HTML <title> element and the on-page heading for all page types
 * (entity view pages, views pages and plain controller results) is in the
 * preprocess hooks that run at the very end of the rendering pipeline.
 *
 * Both hooks run with Order::Last so that the ECA title wins over titles set by
 * other modules. They are a no-op when no ECA title was set, so pages that do
 * not run a "Set page title" action are unaffected.
 *
 * @see \Drupal\eca_misc\Plugin\Action\SetPageTitle
 */
class PageTitleHooks {

  /**
   * Constructs a new PageTitleHooks object.
   */
  public function __construct(
    protected PageTitleStore $pageTitleStore,
  ) {}

  /**
   * Implements hook_preprocess_html().
   *
   * Overrides the HTML <title> element (head_title).
   */
  #[Hook('preprocess_html', order: Order::Last)]
  public function preprocessHtml(array &$variables): void {
    if (!$this->pageTitleStore->hasTitle()) {
      return;
    }
    $title = (string) $this->pageTitleStore->getTitle();

    // Keep the "name" (and optional "slogan") parts that core assembled, but
    // replace the title part. Core marks the title as safe after stripping
    // tags, so do the same here.
    if (!isset($variables['head_title']) || !is_array($variables['head_title'])) {
      $variables['head_title'] = [];
    }
    $variables['head_title']['title'] = Markup::create(trim(strip_tags($title)));

    $this->pageTitleStore->getCacheableMetadata()->applyTo($variables);
  }

  /**
   * Implements hook_preprocess_page_title().
   *
   * Overrides the on-page heading (the H1 rendered by the page_title element).
   */
  #[Hook('preprocess_page_title', order: Order::Last)]
  public function preprocessPageTitle(array &$variables): void {
    if (!$this->pageTitleStore->hasTitle()) {
      return;
    }
    $title = (string) $this->pageTitleStore->getTitle();

    // The title variable is rendered inside an <h1> tag, so allow only a safe
    // subset of markup, consistent with how core treats formatted titles.
    $variables['title'] = Markup::create(Xss::filterAdmin($title));

    $this->pageTitleStore->getCacheableMetadata()->applyTo($variables);
  }

}
