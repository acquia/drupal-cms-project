<?php

namespace Drupal\eca_file\Hook;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Hook\Order\Order;
use Drupal\Core\PageCache\ResponsePolicy\KillSwitch;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\eca\Event\TriggerEvent;
use Drupal\eca_file\Event\FileDownload;
use Drupal\file\FileInterface;

/**
 * Implements file hooks for the ECA File submodule.
 */
class FileHooks {

  /**
   * Constructs a new FileHooks object.
   */
  public function __construct(
    protected TriggerEvent $triggerEvent,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected AccountProxyInterface $currentUser,
    protected KillSwitch $killSwitch,
  ) {}

  /**
   * Implements hook_file_download().
   */
  #[Hook('file_download', order: Order::Last)]
  public function fileDownload(string $uri): array|int|null {
    $files = $this->entityTypeManager->getStorage('file')->loadByProperties(['uri' => $uri]);
    $file = reset($files);

    if (!($file instanceof FileInterface)) {
      return NULL;
    }

    $account = $this->currentUser->getAccount();
    /** @var \Drupal\eca_file\Event\FileDownload|null $event */
    $event = $this->triggerEvent->dispatchFromPlugin('file:download', $file, $account);
    if (!($event instanceof FileDownload)) {
      return NULL;
    }
    // An ECA file:download event participated in handling this request.
    // Trigger the page cache kill switch so the resulting response is not
    // stored by the Internal Page Cache. Core's
    // \Drupal\page_cache\StackMiddleware\PageCache::storeResponse() ignores
    // the no-cache/no-store Cache-Control directives and only refuses to
    // store when the response policy returns DENY; the KillSwitch policy
    // does exactly that once triggered. This prevents a stale ECA-controlled
    // decision (e.g. a cached 403) from being served to anonymous users
    // after the ECA model changes its decision.
    $this->killSwitch->trigger();

    if ($event->getAccessResult()?->isForbidden()) {
      return -1;
    }

    // Headers are only meaningful when access has been explicitly allowed.
    // When the access result is neutral or unset, defer to other
    // implementations and default handling by returning NULL.
    if ($event->getAccessResult()?->isAllowed()) {
      // Core's \Drupal\system\FileDownloadController::download() only serves
      // the file when at least one hook_file_download() implementation returns
      // a non-empty headers array; otherwise it throws a 403. Start from the
      // file's default download headers so an "allowed" decision actually
      // serves the file, then let any ECA-provided custom headers override the
      // defaults.
      $headers = $file->getDownloadHeaders();
      $custom = $event->getHeaders();
      if (!empty($custom)) {
        $headers = $custom + $headers;
      }
      return $headers;
    }

    return NULL;
  }

}
