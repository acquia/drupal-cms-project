<?php

declare(strict_types=1);

namespace Drupal\config_language_lock;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Path\PathValidatorInterface;

/**
 * Checks Canvas module integration state for config language lock enforcement.
 */
class CanvasIntegrationChecker {

  /**
   * The Canvas module machine name.
   */
  protected const CANVAS_MODULE = 'canvas';

  /**
   * The Canvas page entity type ID.
   */
  protected const CANVAS_PAGE_ENTITY_TYPE = 'canvas_page';

  public function __construct(
    protected readonly ModuleHandlerInterface $moduleHandler,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly ConfigFactoryInterface $configFactory,
    protected readonly PathValidatorInterface $pathValidator,
  ) {
  }

  /**
   * Returns whether the Canvas module is installed.
   */
  public function isCanvasInstalled(): bool {
    return $this->moduleHandler->moduleExists(static::CANVAS_MODULE);
  }

  /**
   * Returns the count of existing Canvas page entities.
   */
  public function canvasPageCount(): int {
    if (!$this->isCanvasInstalled()) {
      return 0;
    }
    if (!$this->entityTypeManager->hasDefinition(static::CANVAS_PAGE_ENTITY_TYPE)) {
      return 0;
    }
    return (int) $this->entityTypeManager
      ->getStorage(static::CANVAS_PAGE_ENTITY_TYPE)
      ->getQuery()
      ->accessCheck(FALSE)
      ->count()
      ->execute();
  }

  /**
   * Returns whether the site front page is a Canvas page.
   *
   * Such a page cannot be deleted until the front page is pointed elsewhere.
   *
   * @see \Drupal\canvas\Hook\PageHooks::preventHomepageDeletion()
   */
  public function frontPageIsCanvasPage(): bool {
    if (!$this->isCanvasInstalled()) {
      return FALSE;
    }
    $front = $this->configFactory->get('system.site')->get('page.front');
    if (!is_string($front) || $front === '') {
      return FALSE;
    }
    $url = $this->pathValidator->getUrlIfValidWithoutAccessCheck($front);
    if ($url === FALSE || !$url->isRouted()) {
      return FALSE;
    }
    return $url->getRouteName() === 'entity.' . static::CANVAS_PAGE_ENTITY_TYPE . '.canonical';
  }

  /**
   * Returns the only Canvas page, when exactly one exists and is deletable.
   */
  public function singleDeletablePage(): ?EntityInterface {
    if (!$this->isCanvasInstalled() || $this->canvasPageCount() !== 1) {
      return NULL;
    }
    $storage = $this->entityTypeManager->getStorage(static::CANVAS_PAGE_ENTITY_TYPE);
    $page = current($storage->loadMultiple());
    if (!$page instanceof EntityInterface || !$page->getEntityType()->hasLinkTemplate('delete-form')) {
      return NULL;
    }
    return $page->access('delete') ? $page : NULL;
  }

}
