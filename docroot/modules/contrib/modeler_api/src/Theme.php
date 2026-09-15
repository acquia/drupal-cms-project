<?php

namespace Drupal\modeler_api;

use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Contains a theme definition for styling a modeler canvas.
 *
 * A theme bundles one or more Drupal asset libraries whose CSS overrides the
 * look and feel a modeler ships by default. Themes can be restricted to a list
 * of model owners and a list of modelers, so that a theme built for one
 * owner-modeler combination is not offered for another. An empty list means
 * "all", so a theme without any restriction is available everywhere.
 *
 * Themes are defined in YAML files named MODULE.modeler_api.themes.yml and are
 * discovered by the ThemePluginManager. Which theme is used for each
 * owner-modeler combination is selected in the Modeler API settings form.
 *
 * A theme that restricts itself to at least one owner or at least one modeler
 * can also be selected automatically, without anybody configuring it, unless
 * the settings form pins a different choice for the combination. The optional
 * weight orders the candidates in that case.
 *
 * Example YAML:
 * @code
 * my_dark_theme:
 *   label: 'Dark'
 *   description: 'A dark canvas with light strokes.'
 *   libraries:
 *     - my_module/dark_theme
 *   owners:
 *     - eca
 *   modelers:
 *     - workflow_modeler
 *   weight: -10
 * @endcode
 *
 * @see \Drupal\modeler_api\Plugin\ThemePluginManager
 * @see \Drupal\modeler_api\Plugin\ThemePluginManager::resolveTheme()
 */
readonly class Theme {

  /**
   * Instantiates a new theme definition.
   *
   * @param string $id
   *   The theme ID.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|string $label
   *   The human-readable label of the theme, shown in the settings form.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|string $description
   *   The optional human-readable description of the theme.
   * @param string $provider
   *   The module that provides this theme.
   * @param string[] $libraries
   *   A list of Drupal asset library names, e.g. 'my_module/my_theme'. They
   *   are attached to the render array of the modeler in the given order,
   *   after the libraries of the modeler itself.
   * @param string[] $owners
   *   A list of model owner plugin IDs this theme is limited to. An empty list
   *   means the theme applies to all model owners.
   * @param string[] $modelers
   *   A list of modeler plugin IDs this theme is limited to. An empty list
   *   means the theme applies to all modelers.
   * @param int $weight
   *   The weight of this theme, used to order the candidates when a theme is
   *   selected automatically. Lower weights are considered first.
   */
  public function __construct(
    protected string $id,
    protected TranslatableMarkup|string $label,
    protected TranslatableMarkup|string $description,
    protected string $provider,
    protected array $libraries,
    protected array $owners,
    protected array $modelers,
    protected int $weight = 0,
  ) {}

  /**
   * Gets the theme ID.
   *
   * @return string
   *   The theme ID.
   */
  public function getId(): string {
    return $this->id;
  }

  /**
   * Gets the human-readable label.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup|string
   *   The label.
   */
  public function getLabel(): TranslatableMarkup|string {
    return $this->label;
  }

  /**
   * Gets the human-readable description.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup|string
   *   The description, or an empty string if none was provided.
   */
  public function getDescription(): TranslatableMarkup|string {
    return $this->description;
  }

  /**
   * Gets the provider module name.
   *
   * @return string
   *   The module that provides this theme.
   */
  public function getProvider(): string {
    return $this->provider;
  }

  /**
   * Gets the asset libraries of this theme.
   *
   * @return string[]
   *   A list of Drupal asset library names.
   */
  public function getLibraries(): array {
    return $this->libraries;
  }

  /**
   * Gets the model owner plugin IDs this theme is limited to.
   *
   * @return string[]
   *   The list of model owner plugin IDs. An empty list means all owners.
   */
  public function getOwners(): array {
    return $this->owners;
  }

  /**
   * Gets the modeler plugin IDs this theme is limited to.
   *
   * @return string[]
   *   The list of modeler plugin IDs. An empty list means all modelers.
   */
  public function getModelers(): array {
    return $this->modelers;
  }

  /**
   * Gets the weight of this theme.
   *
   * The weight only matters when a theme is selected automatically: among the
   * themes that qualify, the lowest weight wins. It has no effect on a theme
   * that an administrator pinned explicitly.
   *
   * @return int
   *   The weight, 0 if the definition did not declare one.
   *
   * @see \Drupal\modeler_api\Plugin\ThemePluginManager::resolveTheme()
   */
  public function getWeight(): int {
    return $this->weight;
  }

  /**
   * Determines whether this theme applies to an owner-modeler combination.
   *
   * An empty owner or modeler list means the theme is not restricted in that
   * dimension, so a theme that declares neither applies to every combination.
   *
   * @param string $ownerId
   *   The model owner plugin ID.
   * @param string $modelerId
   *   The modeler plugin ID.
   *
   * @return bool
   *   TRUE if the theme can be used for the given combination, FALSE
   *   otherwise.
   */
  public function appliesTo(string $ownerId, string $modelerId): bool {
    if ($this->owners !== [] && !in_array($ownerId, $this->owners, TRUE)) {
      return FALSE;
    }
    if ($this->modelers !== [] && !in_array($modelerId, $this->modelers, TRUE)) {
      return FALSE;
    }
    return TRUE;
  }

}
