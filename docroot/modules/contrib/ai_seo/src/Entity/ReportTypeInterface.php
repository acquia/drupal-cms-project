<?php

namespace Drupal\ai_seo\Entity;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Provides an interface for defining Report Type entities.
 */
interface ReportTypeInterface extends ConfigEntityInterface {

  /**
   * Gets the Report Type description.
   *
   * @return string
   *   Description of the Report Type.
   */
  public function getDescription();

  /**
   * Sets the Report Type description.
   *
   * @param string $description
   *   The Report Type description.
   *
   * @return \Drupal\ai_seo\Entity\ReportTypeInterface
   *   The called Report Type entity.
   */
  public function setDescription($description);

  /**
   * Gets the prompt text.
   *
   * @return string
   *   The prompt text for this report type.
   */
  public function getPrompt();

  /**
   * Sets the prompt text.
   *
   * @param string $prompt
   *   The prompt text.
   *
   * @return \Drupal\ai_seo\Entity\ReportTypeInterface
   *   The called Report Type entity.
   */
  public function setPrompt($prompt);

  /**
   * Gets the default prompt hash.
   *
   * Stores the MD5 of the shipped default prompt at the time this entity was
   * last initialised or auto-updated from a module default. Never changed by
   * the admin UI, so comparing md5(getPrompt()) against this value reveals
   * whether the admin has customised the prompt since the last default update.
   *
   * @return string|null
   *   MD5 hash, or NULL if never set (entity pre-dates this feature).
   */
  public function getDefaultPromptHash(): ?string;

  /**
   * Sets the default prompt hash.
   *
   * @param string $hash
   *   MD5 of the default prompt being applied.
   *
   * @return \Drupal\ai_seo\Entity\ReportTypeInterface
   *   The called Report Type entity.
   */
  public function setDefaultPromptHash(string $hash): static;

}
