<?php

declare(strict_types=1);

namespace Drupal\eca_htmx;

use Drupal\Core\Htmx\HtmxRequestInfoTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Reads HTMX request information from the current request.
 *
 * This is a thin, reusable wrapper around the Drupal core
 * \Drupal\Core\Htmx\HtmxRequestInfoTrait. The trait declares an abstract
 * getRequest() method; this class satisfies it from the injected request
 * stack so that ECA conditions and the token data provider can share a single
 * implementation instead of each re-implementing the trait.
 *
 * The trait's helper methods are protected, so this class re-exposes the ones
 * ECA needs as public methods. We deliberately route everything through core's
 * trait rather than reading the HX-* headers by hand.
 */
class HtmxRequestInfo {

  use HtmxRequestInfoTrait;

  /**
   * Constructs a new HtmxRequestInfo object.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The request stack.
   */
  public function __construct(
    protected RequestStack $requestStack,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  protected function getRequest(): Request {
    return $this->requestStack->getCurrentRequest() ?? new Request();
  }

  /**
   * Determines whether the current request was sent by HTMX.
   *
   * @return bool
   *   TRUE if the 'HX-Request' header is present.
   */
  public function isRequest(): bool {
    return $this->isHtmxRequest();
  }

  /**
   * Determines whether the current request was boosted by HTMX.
   *
   * @return bool
   *   TRUE if the 'HX-Boosted' header is present.
   */
  public function isBoosted(): bool {
    return $this->isHtmxBoosted();
  }

  /**
   * Determines whether the current request is for history restoration.
   *
   * @return bool
   *   TRUE if the 'HX-History-Restore-Request' header is present.
   */
  public function isHistoryRestore(): bool {
    return $this->isHtmxHistoryRestoration();
  }

  /**
   * Retrieves the target identifier from the HTMX request header.
   *
   * @return string
   *   The value of the 'HX-Target' header, or an empty string if not set.
   */
  public function target(): string {
    return $this->getHtmxTarget();
  }

  /**
   * Retrieves the trigger identifier from the HTMX request header.
   *
   * @return string
   *   The value of the 'HX-Trigger' header, or an empty string if not set.
   */
  public function trigger(): string {
    // getHtmxTrigger() is deprecated in drupal:11.5.0 and removed in
    // drupal:12.0.0 in favour of getHtmxSource(). Call the new method when it
    // is available and fall back to the deprecated one otherwise.
    // @see https://www.drupal.org/node/3583674
    if (method_exists($this, 'getHtmxSource')) {
      return $this->getHtmxSource();
    }
    return $this->getRequest()->headers->get('HX-Trigger', '');
  }

  /**
   * Retrieves the trigger name from the HTMX request header.
   *
   * @return string
   *   The value of the 'HX-Trigger-Name' header, or an empty string if not set.
   */
  public function triggerName(): string {
    return $this->getHtmxTriggerName();
  }

  /**
   * Retrieves the URL of the requesting page from the HTMX request header.
   *
   * @return string
   *   The value of the 'HX-Current-URL' header, or an empty string if not set.
   */
  public function currentUrl(): string {
    return $this->getHtmxCurrentUrl();
  }

}
