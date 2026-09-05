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
      new Token(name: 'target', description: 'The value of the HX-Target request header.'),
      new Token(name: 'trigger', description: 'The value of the HX-Trigger request header.'),
      new Token(name: 'trigger_name', description: 'The value of the HX-Trigger-Name request header.'),
      new Token(name: 'current_url', description: 'The value of the HX-Current-URL request header.'),
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
