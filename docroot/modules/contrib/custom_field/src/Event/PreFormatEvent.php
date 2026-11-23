<?php

declare(strict_types=1);

namespace Drupal\custom_field\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\Core\Field\FieldItemInterface;

/**
 * Event fired in BaseFormatter::getFormattedValues().
 *
 * Subscribers can alter the custom items that will be rendered.
 *
 * @see \Drupal\custom_field\Plugin\Field\FieldFormatter\BaseFormatter::getFormattedValues()
 */
final class PreFormatEvent extends Event {

  /**
   * Constructor.
   *
   * @param array<string, \Drupal\custom_field\Plugin\CustomFieldTypeInterface> $customItems
   *   The sorted custom items prior to formatting.
   * @param \Drupal\Core\Field\FieldItemInterface $item
   *   The field item.
   * @param string $langcode
   *   The language code.
   */
  public function __construct(
    private array $customItems,
    private readonly FieldItemInterface $item,
    private readonly string $langcode,
  ) {}

  /**
   * Get the custom items.
   *
   * @return array<string, \Drupal\custom_field\Plugin\CustomFieldTypeInterface>
   *   The sorted custom items prior to formatting.
   */
  public function getCustomItems(): array {
    return $this->customItems;
  }

  /**
   * Get the field item in process of being rendered.
   *
   * @return \Drupal\Core\Field\FieldItemInterface
   *   The field item.
   */
  public function getFieldItem(): FieldItemInterface {
    return $this->item;
  }

  /**
   * Get the language in which the field needs to be rendered.
   *
   * @return string
   *   The language code.
   */
  public function getLanguage(): string {
    return $this->langcode;
  }

  /**
   * Update the custom items.
   *
   * @param array<string, \Drupal\custom_field\Plugin\CustomFieldTypeInterface> $custom_items
   *   The updated custom items.
   */
  public function setCustomItems(array $custom_items): void {
    $this->customItems = $custom_items;
  }

}
