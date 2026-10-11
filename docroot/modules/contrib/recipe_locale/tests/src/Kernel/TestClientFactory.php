<?php

declare(strict_types=1);

namespace Drupal\Tests\recipe_locale\Kernel;

use Drupal\Core\Http\ClientFactory;
use GuzzleHttp\Client;

/**
 * Hands out clients that use a test handler instead of the network.
 *
 * Locale checks remote files with a client from this factory, and downloads
 * them with the http_client service. Tests replace both.
 */
final class TestClientFactory extends ClientFactory {

  /**
   * {@inheritdoc}
   */
  public function fromOptions(array $config = []): Client {
    return new Client(['handler' => $this->stack] + $config);
  }

}
