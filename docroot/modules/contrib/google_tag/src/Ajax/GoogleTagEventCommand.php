<?php

declare(strict_types=1);

namespace Drupal\google_tag\Ajax;

use Drupal\Core\Ajax\CommandInterface;
use Drupal\Core\Ajax\CommandWithAttachedAssetsInterface;
use Drupal\Core\Asset\AttachedAssets;

/**
 * Ajax command for sending events to GA for ajax responses.
 */
final class GoogleTagEventCommand implements CommandInterface, CommandWithAttachedAssetsInterface {

  /**
   * The Event Name.
   *
   * @var string
   */
  private string $eventName;

  /**
   * Event Data.
   *
   * @var array
   */
  private array $data;

  /**
   * GoogleTagEventCommand constructor.
   *
   * @param string $event_name
   *   Event name.
   * @param array $data
   *   Event data.
   */
  public function __construct(string $event_name, array $data) {
    $this->eventName = $event_name;
    $this->data = $data;
  }

  /**
   * {@inheritDoc}
   */
  public function render() {
    return [
      'command' => 'gtagEvent',
      'event_name' => $this->eventName,
      'data' => $this->data,
    ];
  }

  /**
   * {@inheritDoc}
   */
  public function getAttachedAssets() {
    // The gtagEvent command is handled by gtag.ajax.js, so the library is only
    // needed when such a command is actually dispatched.
    $assets = new AttachedAssets();
    $assets->setLibraries(['google_tag/gtag.ajax']);
    return $assets;
  }

}
