<?php

namespace Drupal\ai_dashboard\Plugin\AiDocumentation;

/**
 * Defines the interface for ai documentation.
 */
interface AiDocumentationInterface {

  /**
   * Gets the documentation ID.
   *
   * @return string
   *   The documentation ID.
   */
  public function getId();

  /**
   * Gets the translated label.
   *
   * @return string
   *   The translated label.
   */
  public function getLabel();

  /**
   * Gets the translated description.
   *
   * @return string
   *   The translated description.
   */
  public function getDescription();

  /**
   * Gets the url to documentation page.
   *
   * @return string
   *   The url to documentation page.
   */
  public function getUrl();

}
