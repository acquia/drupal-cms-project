<?php

namespace Drupal\eca_render\Event;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Url;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched when a link gets altered.
 *
 * @internal
 *   This class is not meant to be used as a public API. It is subject for name
 *   change or may be removed completely, also on minor version updates.
 *
 * @package Drupal\eca_render\Event
 */
class EcaRenderAlterLinkEvent extends Event {

  /**
   * The link variables.
   *
   * @var array
   */
  protected array $variables;

  /**
   * Constructs a new EcaRenderContextualLinksEvent object.
   *
   * @param array &$variables
   *   The link variables.
   */
  public function __construct(array &$variables) {
    $this->variables = &$variables;
  }

  /**
   * Gets the current variables of the link.
   *
   * @return array
   *   The current variables.
   */
  public function getVariables(): array {
    return $this->variables;
  }

  /**
   * Add an attribute to the link.
   *
   * @param string $key
   *   The attribute key.
   * @param string $value
   *   The attribute value.
   * @param bool $reset
   *   If TRUE, attributes will be reset before adding the new attribute. Note
   *   that the class and the title attributes will not be reset.
   */
  public function addAttribute(string $key, string $value, bool $reset = FALSE): void {
    if ($reset) {
      $class = $this->variables['options']['attributes']['class'] ?? NULL;
      $title = $this->variables['options']['attributes']['title'] ?? NULL;
      $this->variables['options']['attributes'] = [];
      if ($class !== NULL) {
        $this->variables['options']['attributes']['class'] = $class;
      }
      if ($title !== NULL) {
        $this->variables['options']['attributes']['title'] = $title;
      }
    }
    if ($key !== '' && $value !== '') {
      $this->variables['options']['attributes'][$key] = $value;
    }
  }

  /**
   * Add a class to the link.
   *
   * @param string $class
   *   The class to add.
   * @param bool $reset
   *   If TRUE, the class will be reset before adding the new class.
   */
  public function addClass(string $class, bool $reset = FALSE): void {
    if ($reset) {
      $this->variables['options']['attributes']['class'] = [];
    }
    if ($class !== '') {
      $this->variables['options']['attributes']['class'][] = $class;
    }
  }

  /**
   * Add a query argument to the link.
   *
   * @param string $key
   *   The query name.
   * @param string $value
   *   The query value.
   * @param bool $reset
   *   If TRUE, query arguments will be reset before adding the new query
   *   attribute.
   */
  public function addQuery(string $key, string $value, bool $reset = FALSE): void {
    if ($reset) {
      $this->variables['options']['query'] = [];
    }
    if ($key !== '' && $value !== '') {
      $this->variables['options']['query'][$key] = $value;
    }
  }

  /**
   * Set the absolute option of the link.
   *
   * @param bool $absolute
   *   TRUE if the link should be absolute, FALSE otherwise.
   */
  public function setAbsolute(bool $absolute): void {
    $this->variables['options']['absolute'] = $absolute;
  }

  /**
   * Set the language of the link.
   *
   * @param \Drupal\Core\Language\LanguageInterface|null $language
   *   The language. If NULL, the language will be unset.
   */
  public function setLanguage(?LanguageInterface $language = NULL): void {
    if ($language === NULL) {
      unset($this->variables['options']['language']);
    }
    else {
      $this->variables['options']['language'] = $language;
    }
  }

  /**
   * Set the visible text of the link.
   *
   * @param \Drupal\Component\Render\MarkupInterface $text
   *   The visible text.
   */
  public function setText(MarkupInterface $text): void {
    $this->variables['text'] = $text;
  }

  /**
   * Set the title of the link.
   *
   * @param string $title
   *   The title. If an empty string is provided, the title will be unset.
   */
  public function setTitle(string $title): void {
    if ($title === '') {
      unset($this->variables['options']['attributes']['title']);
    }
    else {
      $this->variables['options']['attributes']['title'] = $title;
    }
  }

  /**
   * Set the URL of the link.
   *
   * @param \Drupal\Core\Url $url
   *   The url.
   */
  public function setUrl(Url $url): void {
    $this->variables['url'] = $url;
  }

}
