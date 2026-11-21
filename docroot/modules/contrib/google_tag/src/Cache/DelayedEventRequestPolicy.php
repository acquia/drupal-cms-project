<?php

declare(strict_types=1);

namespace Drupal\google_tag\Cache;

use Drupal\Core\PageCache\RequestPolicyInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * Keeps pages with pending delayed events out of the Dynamic Page Cache.
 *
 * Delayed events are stored in the session and added to the page by
 * google_tag_page_attachments(). A cached page skips that hook, so the events
 * would not be sent, and caching a page that has them would send them to
 * every later visitor of that page.
 */
final class DelayedEventRequestPolicy implements RequestPolicyInterface {

  /**
   * Constructs a DelayedEventRequestPolicy object.
   *
   * @param \Symfony\Component\HttpFoundation\Session\SessionInterface $session
   *   The session that the event collector stores delayed events in.
   */
  public function __construct(
    private readonly SessionInterface $session,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function check(Request $request) {
    if ($this->session->get('google_tag_events', []) !== []) {
      return RequestPolicyInterface::DENY;
    }
    return NULL;
  }

}
