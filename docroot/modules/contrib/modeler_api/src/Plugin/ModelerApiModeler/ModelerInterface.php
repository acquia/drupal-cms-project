<?php

namespace Drupal\modeler_api\Plugin\ModelerApiModeler;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\modeler_api\Plugin\ModelerApiModelOwner\ModelOwnerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Interface for modeler plugins.
 */
interface ModelerInterface extends PluginInspectionInterface, ContainerFactoryPluginInterface {

  /**
   * Provides the plugin label.
   *
   * @return string
   *   The plugin label.
   */
  public function label(): string;

  /**
   * Provides the plugin description.
   *
   * @return string
   *   The plugin description.
   */
  public function description(): string;

  /**
   * Provides the raw file extension.
   *
   * @return string|null
   *   The raw file extension, if the modeler even has that.
   */
  public function getRawFileExtension(): ?string;

  /**
   * Generate an ID for the model.
   *
   * @return string
   *   The ID of the model.
   */
  public function generateId(): string;

  /**
   * Set raw data to enabled.
   *
   * @param \Drupal\modeler_api\Plugin\ModelerApiModelOwner\ModelOwnerInterface $owner
   *   The model owner.
   *
   * @return $this
   *   This.
   */
  public function enable(ModelOwnerInterface $owner): ModelerInterface;

  /**
   * Set raw data to disabled.
   *
   * @param \Drupal\modeler_api\Plugin\ModelerApiModelOwner\ModelOwnerInterface $owner
   *   The model owner.
   *
   * @return $this
   *   This.
   */
  public function disable(ModelOwnerInterface $owner): ModelerInterface;

  /**
   * Prepare raw data for a cloned version of the model.
   *
   * @param \Drupal\modeler_api\Plugin\ModelerApiModelOwner\ModelOwnerInterface $owner
   *   The model owner.
   * @param string $id
   *   The new ID.
   * @param string $label
   *   The new label.
   *
   * @return $this
   *   This.
   */
  public function clone(ModelOwnerInterface $owner, string $id, string $label): ModelerInterface;

  /**
   * Gets the raw data of an empty model.
   *
   * @param string $id
   *   The id of the model.
   *
   * @return string
   *   The raw data.
   */
  public function prepareEmptyModelData(string &$id): string;

  /**
   * Determines if the modeler supports editing in Drupal's admin interface.
   *
   * @return bool
   *   TRUE, if the modelers supports editing inside Drupal's admin interface,
   *   FALSE otherwise.
   */
  public function isEditable(): bool;

  /**
   * Returns a render array with everything required for model editing.
   *
   * @param \Drupal\modeler_api\Plugin\ModelerApiModelOwner\ModelOwnerInterface $owner
   *   The model owner plugin.
   * @param string $id
   *   The model id.
   * @param string $data
   *   The raw model data.
   * @param bool $isNew
   *   TRUE, if this is a new model, FALSE otherwise (=default).
   * @param bool $readOnly
   *   TRUE, if the model should only be viewed, FALSE otherwise.
   *
   * @return array
   *   The render array.
   */
  public function edit(ModelOwnerInterface $owner, string $id, string $data, bool $isNew = FALSE, bool $readOnly = FALSE): array;

  /**
   * Returns a render array with everything required for model conversion.
   *
   * @param \Drupal\modeler_api\Plugin\ModelerApiModelOwner\ModelOwnerInterface $owner
   *   The model owner plugin.
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $model
   *   The model to convert.
   * @param bool $readOnly
   *   TRUE, if the model should only be viewed, FALSE otherwise.
   *
   * @return array
   *   The render array.
   */
  public function convert(ModelOwnerInterface $owner, ConfigEntityInterface $model, bool $readOnly = FALSE): array;

  /**
   * Get the model ID.
   *
   * @return string
   *   The model ID.
   */
  public function getId(): string;

  /**
   * Get the model's label.
   *
   * @return string
   *   The label.
   */
  public function getLabel(): string;

  /**
   * Get the model's tags.
   *
   * @return array
   *   The list of tags.
   */
  public function getTags(): array;

  /**
   * Get the model's changelog.
   *
   * @return string
   *   The changelog.
   */
  public function getChangelog(): string;

  /**
   * Get the model's template setting.
   *
   * @return bool
   *   The template setting.
   */
  public function getTemplate(): bool;

  /**
   * Get the model's storage setting.
   *
   * @return string
   *   The storage setting.
   */
  public function getStorage(): string;

  /**
   * Get the model's documentation.
   *
   * @return string
   *   The documentation.
   */
  public function getDocumentation(): string;

  /**
   * Get the model's status.
   *
   * @return bool
   *   TRUE, if the model is enabled, FALSE otherwise.
   */
  public function getStatus(): bool;

  /**
   * Get the model's version.
   *
   * @return string
   *   The version string.
   */
  public function getVersion(): string;

  /**
   * Parses the raw data and prepares it for processing.
   *
   * @param \Drupal\modeler_api\Plugin\ModelerApiModelOwner\ModelOwnerInterface $owner
   *   The model owner plugin.
   * @param string $data
   *   The raw model data.
   */
  public function parseData(ModelOwnerInterface $owner, string $data): void;

  /**
   * Returns all components from raw data.
   *
   * @return \Drupal\modeler_api\Component[]
   *   The list of components.
   */
  public function readComponents(): array;

  /**
   * Updates all components in raw data.
   *
   * This validates if any of the raw data of existing models needs to be
   * updated by verifying each of the available components with the current
   * owner component definitions.
   *
   * @param \Drupal\modeler_api\Plugin\ModelerApiModelOwner\ModelOwnerInterface $owner
   *   The model owner plugin.
   *
   * @return bool
   *   TRUE, if anything has been updated, FALSE otherwise.
   */
  public function updateComponents(ModelOwnerInterface $owner): bool;

  /**
   * Gets the raw data.
   *
   * @return string
   *   The raw data.
   */
  public function getRawData(): string;

  /**
   * Gets the requested config form.
   *
   * @param \Drupal\modeler_api\Plugin\ModelerApiModelOwner\ModelOwnerInterface $owner
   *   The model owner.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The response.
   */
  public function configForm(ModelOwnerInterface $owner): JsonResponse;

}
