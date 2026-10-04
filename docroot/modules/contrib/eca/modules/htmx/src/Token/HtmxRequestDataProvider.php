<?php

declare(strict_types=1);

namespace Drupal\eca_htmx\Token;

use Drupal\eca\Attribute\Token;
use Drupal\eca\Plugin\DataType\DataTransferObject;
use Drupal\eca\Token\DataProviderInterface;
use Drupal\eca_htmx\HtmxRequestInfo;

/**
 * Provides information about the current HTMX request as [htmx:*] tokens.
 *
 * The values are read exclusively through the core HtmxRequestInfoTrait (via
 * the HtmxRequestInfo wrapper); this provider never inspects HX-* headers
 * directly.
 */
class HtmxRequestDataProvider implements DataProviderInterface {

  /**
   * Constructs a new HtmxRequestDataProvider object.
   *
   * @param \Drupal\eca_htmx\HtmxRequestInfo $htmxRequestInfo
   *   The HTMX request info service.
   */
  public function __construct(
    protected HtmxRequestInfo $htmxRequestInfo,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  #[Token(
    name: 'htmx',
    description: 'Information about the current HTMX request.',
    properties: [
      new Token(name: 'is_request', description: 'Whether the current request was sent by HTMX ("1" or "0").'),
      new Token(name: 'boosted', description: 'Whether the current request was boosted by HTMX ("1" or "0").'),
      new Token(name: 'history_restore', description: 'Whether the current request is for HTMX history restoration ("1" or "0").'),
      new Token(name: 'target', description: 'The identifier of the element the response is targeted at.'),
      new Token(name: 'trigger', description: 'The identifier of the element that triggered the request. On Drupal 11 this is the element id, on Drupal 12 and later it is the CSS selector that htmx 4 sends. Prefer "trigger_name", which means the same thing on both.'),
      new Token(name: 'trigger_name', description: 'The name attribute of the element that triggered the request, or empty if it has none. This has the same meaning on all supported Drupal versions.'),
      new Token(name: 'source', description: 'The CSS selector of the element that triggered the request, for example button[name="first_item"]. Empty on Drupal 11, which does not send this information.'),
      new Token(name: 'request_type', description: 'Whether the request targets a full page or a fragment: "full" or "partial". Empty on Drupal 11, which does not send this information.'),
      new Token(name: 'current_url', description: 'The URL of the page the request was sent from.'),
    ],
  )]
  public function getData(string $key): mixed {
    if ($key !== 'htmx') {
      return NULL;
    }
    return DataTransferObject::create([
      'is_request' => $this->htmxRequestInfo->isRequest() ? '1' : '0',
      'boosted' => $this->htmxRequestInfo->isBoosted() ? '1' : '0',
      'history_restore' => $this->htmxRequestInfo->isHistoryRestore() ? '1' : '0',
      'target' => $this->htmxRequestInfo->target(),
      'trigger' => $this->htmxRequestInfo->trigger(),
      'trigger_name' => $this->htmxRequestInfo->triggerName(),
      'source' => $this->htmxRequestInfo->source(),
      'request_type' => $this->htmxRequestInfo->requestType(),
      'current_url' => $this->htmxRequestInfo->currentUrl(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function hasData(string $key): bool {
    return $key === 'htmx';
  }

}
