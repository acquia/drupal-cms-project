<?php

namespace Drupal\eca_misc;

use Drupal\Core\Cache\CacheableMetadata;

/**
 * Request-scoped store for an ECA-defined page title.
 *
 * ECA actions write the desired page title into this store during request
 * processing (e.g. on a kernel or form event). The title is then applied by
 * the late preprocess hooks in
 * \Drupal\eca_misc\Hook\PageTitleHooks, which is the only reliable place to
 * override both the HTML <title> element and the on-page heading for all page
 * types.
 *
 * Drupal core freezes the page title into a scalar value inside
 * \Drupal\Core\Render\MainContent\HtmlRenderer::prepare(), after the main
 * content has been pre-rendered. Therefore neither a route title override nor a
 * render-array "#title" set on a kernel VIEW event wins reliably across entity
 * view pages, views pages and controller results. Storing the desired title
 * here and applying it in preprocess_html / preprocess_page_title is the
 * robust approach.
 */
class PageTitleStore {

  /**
   * The page title to apply, or NULL when no ECA title was set.
   *
   * @var string|null
   */
  protected ?string $title = NULL;

  /**
   * Cacheability metadata associated with the title.
   *
   * @var \Drupal\Core\Cache\CacheableMetadata
   */
  protected CacheableMetadata $cacheableMetadata;

  /**
   * Constructs a new PageTitleStore object.
   */
  public function __construct() {
    $this->cacheableMetadata = new CacheableMetadata();
  }

  /**
   * Sets the desired page title.
   *
   * @param string $title
   *   The page title. This value is treated as already prepared markup, so
   *   callers must ensure it is safe (the ECA action resolves tokens and uses
   *   it as the rendered title, consistent with core title handling).
   * @param \Drupal\Core\Cache\CacheableMetadata|null $cacheable_metadata
   *   Optional cacheability metadata to merge in, so the override does not get
   *   cached for the wrong context.
   *
   * @return $this
   */
  public function setTitle(string $title, ?CacheableMetadata $cacheable_metadata = NULL): static {
    $this->title = $title;
    if ($cacheable_metadata !== NULL) {
      $this->cacheableMetadata = $this->cacheableMetadata->merge($cacheable_metadata);
    }
    return $this;
  }

  /**
   * Gets the desired page title.
   *
   * @return string|null
   *   The page title, or NULL when no ECA title was set.
   */
  public function getTitle(): ?string {
    return $this->title;
  }

  /**
   * Whether an ECA page title has been set.
   *
   * @return bool
   *   TRUE if a title was set, FALSE otherwise.
   */
  public function hasTitle(): bool {
    return $this->title !== NULL;
  }

  /**
   * Gets the cacheability metadata associated with the title.
   *
   * @return \Drupal\Core\Cache\CacheableMetadata
   *   The cacheability metadata.
   */
  public function getCacheableMetadata(): CacheableMetadata {
    return $this->cacheableMetadata;
  }

}
