<?php

namespace Drupal\drupal_cms_helper;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\Crypt;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Recipe\RecipeRunner;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Site\Settings;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Utility\Error;
use DrupalEnvironment\Environment;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\RequestOptions;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Collects and sends telemetry events to Amplitude.
 *
 * @internal
 *   This is an internal part of Drupal CMS and may be changed or removed at
 *   any time without warning. External code should not interact with this
 *   class.
 */
final readonly class TelemetryManager {

  /**
   * The queue where telemetry events are stored.
   */
  public const string QUEUE_NAME = 'drupal_cms_telemetry';

  public function __construct(
    private StateInterface $state,
    private QueueFactory $queueFactory,
    private ClientInterface $client,
    private ConfigFactoryInterface $configFactory,
    private RouteMatchInterface $routeMatch,
    private LoggerInterface $logger = new NullLogger(),
  ) {}

  /**
   * Logs a telemetry event.
   *
   * @param \Drupal\drupal_cms_helper\Telemetry $event
   *   The event being logged.
   * @param array<mixed> $properties
   *   (optional) Additional information about the event.
   */
  public function log(Telemetry $event, array $properties = []): void {
    // Always log an event if installing from a site template. At install time,
    // there has been no explicit opt-in to telemetry yet so if we didn't log
    // this event, we would never know which site templates people use. In any
    // case, the event isn't sent until the site opts in.
    if ($this->status() || $event === Telemetry::SiteTemplateInstall) {
      // If installing a module or applying a recipe, find out if it was done
      // via Project Browser, so we can tell how popular Project Browser is.
      if ($event === Telemetry::ModuleInstall || $event === Telemetry::RecipeApplied) {
        $properties['project_browser'] ??= $this->routeMatch->getRouteName() === 'project_browser.activate';
      }
      if ($event === Telemetry::ModuleInstall) {
        $properties['via_recipe'] ??= RecipeRunner::isApplying();
      }

      $this->queueFactory->get(self::QUEUE_NAME)->createItem([
        'event_type' => $event->value,
        'event_properties' => $properties + [
          'is_production' => Environment::isProduction(),
        ],
      ]);
    }
  }

  /**
   * Returns, or sets, whether telemetry is enabled.
   *
   * @param bool|null $value
   *   (optional) The new status to set, or NULL to return the current status.
   *
   * @return bool|null
   *   Whether telemetry is enabled, or NULL if the status was changed or has
   *   not been set yet.
   */
  public function status(?bool $value = NULL): ?bool {
    $key = 'drupal_cms_telemetry';

    if (isset($value)) {
      $this->state->set($key, $value);

      if ($value) {
        $this->log(Telemetry::OptIn);
      }
      else {
        $this->queueFactory->get(self::QUEUE_NAME)->deleteQueue();
      }
      return NULL;
    }
    return $this->state->get($key);
  }

  /**
   * Sends collected events to Amplitude.
   *
   * @param int $limit
   *   (optional) The maximum number of events to send at once. Defaults to 500.
   */
  public function sendAll(int $limit = 500): void {
    // Only send telemetry if there has been explicit opt-in. Tests, previews,
    // and CI environments don't represent real-world usage.
    if (drupal_valid_test_ua() || Environment::isPreview() || Environment::isCi()) {
      return;
    }

    // Amplitude requires every event to have a device ID. This is unique to
    // the site, so it's not technically anonymous, but it isn't personally
    // identifiable at all.
    $device_id = Crypt::hmacBase64(
      $this->configFactory->get('system.site')->get('uuid'),
      Settings::getHashSalt(),
    );

    $queue = $this->queueFactory->get(self::QUEUE_NAME);

    // Try to send up to $limit events at once.
    $items = [];
    while (count($items) < $limit && $item = $queue->claimItem()) {
      assert(is_object($item) && isset($item->data) && is_array($item->data));
      $item->data['device_id'] = $device_id;
      $items[] = $item;
    }
    // There's nothing to send if no events are queued.
    if (count($items) === 0) {
      return;
    }

    try {
      // @phpstan-ignore-next-line method.notFound
      $this->client->post('https://api.eu.amplitude.com/2/httpapi', [
        RequestOptions::HEADERS => [
          'Content-Type' => 'application/json',
        ],
        RequestOptions::BODY => Json::encode([
          // The API key is public, per Amplitude's documentation.
          'api_key' => 'fea56af02c521a981fce191f0b29b87b',
          'events' => array_column($items, 'data'),
        ]),
      ]);
      array_walk($items, $queue->deleteItem(...));
    }
    catch (ClientExceptionInterface $e) {
      Error::logException($this->logger, $e);
      array_walk($items, $queue->releaseItem(...));
    }
  }

}
