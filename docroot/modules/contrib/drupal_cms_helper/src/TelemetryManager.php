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

  /**
   * The name of the kill switch setting.
   */
  public const string KILL_SWITCH = self::QUEUE_NAME;

  public function __construct(
    private StateInterface $state,
    private QueueFactory $queueFactory,
    private ClientInterface $client,
    private ConfigFactoryInterface $configFactory,
    private RouteMatchInterface $routeMatch,
    private LoggerInterface $logger = new NullLogger(),
  ) {}

  /**
   * Determines if telemetry is disabled by settings.
   *
   * @return bool
   *   TRUE if telemetry is disabled by settings, FALSE otherwise.
   */
  public static function isDisabled(): bool {
    return Settings::get(self::KILL_SWITCH) === FALSE;
  }

  /**
   * Logs a telemetry event.
   *
   * @param \Drupal\drupal_cms_helper\Telemetry $event
   *   The event being logged.
   * @param array<mixed> $properties
   *   (optional) Additional information about the event.
   */
  public function log(Telemetry $event, array $properties = []): void {
    // In a CI or preview environment, we don't want to log any events. We DO,
    // however, want to log events if we're in a test that's running in CI.
    if ((getenv('CI') && !drupal_valid_test_ua()) || getenv('TUGBOAT_PREVIEW_ID')) {
      return;
    }
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

      // @see https://docs.ddev.com/en/stable/users/extend/custom-commands/#environment-variables-provided
      if (getenv('IS_DDEV_PROJECT')) {
        $properties['environment'] ??= 'ddev';
      }
      // @see https://docs.acquia.com/acquia-cloud-platform/using-environment-variables?v=2
      // @see https://docs.pantheon.io/guides/environment-configuration/read-environment-config
      // @see https://docs.railway.com/variables/reference
      // @see https://developer.upsun.com/docs/development/variables/use-variables#use-provided-variables
      elseif (getenv('AH_SITE_ENVIRONMENT') || defined('PANTHEON_ENVIRONMENT') || getenv('RAILWAY_SERVICE_ID') || getenv('PLATFORM_ENVIRONMENT') || getenv('IRONSTAR_ENV_NAME')) {
        $properties['environment'] ??= 'cloud host';
      }
      $this->queueFactory->get(self::QUEUE_NAME)->createItem([
        'event_type' => $event->value,
        'event_properties' => $properties,
      ]);
    }
  }

  /**
   * Returns, or sets, whether telemetry is enabled.
   *
   * @param bool|null $value
   *   (optional) The new status to set, or NULL to return the current status.
   *
   * @return bool
   *   Whether telemetry is enabled.
   */
  public function status(?bool $value = NULL): bool {
    // The settings.php kill switch cannot be overridden.
    if (self::isDisabled()) {
      return FALSE;
    }
    $key = 'drupal_cms_telemetry';

    if (isset($value)) {
      $this->state->set($key, $value);

      if ($value === FALSE) {
        $this->queueFactory->get(self::QUEUE_NAME)->deleteQueue();
      }
      return $value;
    }
    return $this->state->get($key, TRUE);
  }

  /**
   * Sends collected events to Amplitude.
   *
   * @param int $limit
   *   (optional) The maximum number of events to send at once. Defaults to 500.
   */
  public function sendAll(int $limit = 500): void {
    // This method shouldn't be called if telemetry is disabled.
    assert($this->status());

    if (drupal_valid_test_ua()) {
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
