<?php

declare(strict_types=1);

namespace Drupal\drupal_cms_helper;

use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;

/**
 * @internal
 *   This is an internal part of Drupal CMS and may be changed or removed at any
 *   time without warning. External code should not interact with this class.
 *
 * @todo Remove when the REJECT array is empty.
 */
final readonly class FilteringMessenger implements MessengerInterface {

  /**
   * Untranslated messages that should never be shown.
   *
   * @var string[]
   */
  private const array REJECT = [
    // @todo Remove when https://www.drupal.org/i/3563051 is released.
    // @see file_save_upload()
    'Your upload has been renamed to %filename.',
  ];

  public function __construct(
    #[AutowireDecorated] private MessengerInterface $decorated,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function addMessage($message, $type = self::TYPE_STATUS, $repeat = FALSE): self {
    $original_message = $message instanceof TranslatableMarkup ? $message->getUntranslatedString() : $message;

    if (!in_array($original_message, self::REJECT, TRUE)) {
      $this->decorated->addMessage($message, $type, $repeat);
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function addStatus($message, $repeat = FALSE): self {
    return $this->addMessage($message, self::TYPE_STATUS, $repeat);
  }

  /**
   * {@inheritdoc}
   */
  public function addError($message, $repeat = FALSE): self {
    return $this->addMessage($message, self::TYPE_ERROR, $repeat);
  }

  /**
   * {@inheritdoc}
   */
  public function addWarning($message, $repeat = FALSE): self {
    return $this->addMessage($message, self::TYPE_WARNING, $repeat);
  }

  /**
   * {@inheritdoc}
   */
  public function all(): array {
    return $this->decorated->all();
  }

  /**
   * {@inheritdoc}
   */
  public function messagesByType($type): array {
    return $this->decorated->messagesByType($type);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteAll(): array {
    return $this->decorated->deleteAll();
  }

  /**
   * {@inheritdoc}
   */
  public function deleteByType($type): array {
    return $this->decorated->deleteByType($type);
  }

}
