<?php

namespace Drupal\eca_file\Plugin\ECA\Event;

use Drupal\eca\Attribute\EcaEvent;
use Drupal\eca\Attribute\Token;
use Drupal\eca\Event\Tag;
use Drupal\eca\Plugin\DataType\DataTransferObject;
use Drupal\eca\Plugin\ECA\Event\EventBase;
use Drupal\eca\Plugin\ECA\Event\EventInterface;
use Drupal\eca\Plugin\ECA\PluginFormTrait;
use Drupal\eca_file\Event\FileDownload;
use Drupal\eca_file\FileEvents;

/**
 * Plugin implementation of ECA file events.
 */
#[EcaEvent(
  id: 'file',
  deriver: 'Drupal\eca_file\Plugin\ECA\Event\FileEventDeriver',
  version_introduced: '3.1.3',
)]
class FileEvent extends EventBase implements EventInterface {
  use PluginFormTrait;

  /**
   * {@inheritdoc}
   */
  public static function definitions(): array {
    return [
      'download' => [
        'label' => 'File download',
        'description' => 'When a private file is downloaded.',
        'event_name' => FileEvents::DOWNLOAD,
        'event_class' => FileDownload::class,
        'tags' => Tag::AFTER,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  #[Token(
    name: 'file',
    description: 'The downloaded file entity.',
  )]
  public function getData(string $key): mixed {
    $event = $this->event;

    if ($event instanceof FileDownload) {
      switch ($key) {
        case 'file':
          return DataTransferObject::create($event->getFile());
      }
    }

    return parent::getData($key);
  }

}
