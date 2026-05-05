<?php

namespace Drupal\modeler_api\Plugin\ModelerApiModelOwner;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\modeler_api\Component;
use Drupal\modeler_api\Plugin\ModelerApiModeler\ModelerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * The model owner plugin interface.
 *
 * A model owner owns config entities which can be modeled by the modeler API.
 */
interface ModelOwnerInterface extends PluginInspectionInterface, ContainerFactoryPluginInterface {

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
   * A list of component labels for the supported types.
   *
   * @return array
   *   List of component labels key by component types start, element, link,
   *   gateway, and subprocess.
   */
  public function componentLabels(): array;

  /**
   * A list of plural component labels for the supported types.
   *
   * Used for grouping headings in the component panel and quick-add popups.
   *
   * @return array
   *   List of plural component labels keyed by component types start, element,
   *   link, gateway, and subprocess.
   */
  public function componentLabelsPlural(): array;

  /**
   * Provides a callback for validating the unique model ID.
   *
   * Example: "[MyEntity::class, 'load']"
   *
   * @return array
   *   The callback.
   */
  public function modelIdExistsCallback(): array;

  /**
   * Provides the provider id of the model.
   *
   * @return string
   *   The provider id.
   */
  public function configEntityProviderId(): string;

  /**
   * Provides the entity type id of the model.
   *
   * @return string
   *   The entity type id.
   */
  public function configEntityTypeId(): string;

  /**
   * Provides the base path without leading or trailing slash.
   *
   * @return string|null
   *   The base path, or NULL if the model owner controls routing itself.
   */
  public function configEntityBasePath(): ?string;

  /**
   * Provides the settings form class.
   *
   * @return string|null
   *   The settings form class, if this model owner support settings, NULL
   *   otherwise.
   */
  public function settingsForm(): ?string;

  /**
   * Allow model owner to alter the default config form for model's metadata.
   *
   * @param array $form
   *   The form.
   */
  public function modelConfigFormAlter(array &$form): void;

  /**
   * Determines, if the model is editable.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $model
   *   The model.
   *
   * @return bool
   *   TRUE, if the model is editable, FALSE otherwise.
   */
  public function isEditable(ConfigEntityInterface $model): bool;

  /**
   * Determines, if the model is exportable.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $model
   *   The model.
   *
   * @return bool
   *   TRUE, if the model is exportable, FALSE otherwise.
   */
  public function isExportable(ConfigEntityInterface $model): bool;

  /**
   * Enables the model.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $model
   *   The model.
   */
  public function enable(ConfigEntityInterface $model): void;

  /**
   * Disabled the model.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $model
   *   The model.
   */
  public function disable(ConfigEntityInterface $model): void;

  /**
   * Clones the model.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $model
   *   The model.
   * @param string|null $id
   *   (optional) The ID for the cloned model. If NULL, a new ID will be
   *   generated automatically.
   * @param string|null $label
   *   (optional) The label for the cloned model. If NULL, the label will be
   *   derived from the original model with a "(clone)" suffix.
   *
   * @return \Drupal\Core\Config\Entity\ConfigEntityInterface
   *   The cloned model.
   */
  public function clone(ConfigEntityInterface $model, ?string $id = NULL, ?string $label = NULL): ConfigEntityInterface;

  /**
   * Exports the model.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $model
   *   The model.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The exported model as the response.
   */
  public function export(ConfigEntityInterface $model): Response;

  /**
   * Set label of the model.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $model
   *   The model.
   * @param string $label
   *   The label.
   *
   * @return $this
   */
  public function setLabel(ConfigEntityInterface $model, string $label): ModelOwnerInterface;

  /**
   * Get label from the model.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $model
   *   The model.
   *
   * @return string
   *   The label.
   */
  public function getLabel(ConfigEntityInterface $model): string;

  /**
   * Set status of the model.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $model
   *   The model.
   * @param bool $status
   *   The status.
   *
   * @return $this
   */
  public function setStatus(ConfigEntityInterface $model, bool $status): ModelOwnerInterface;

  /**
   * Get status from the model.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $model
   *   The model.
   *
   * @return bool
   *   The status.
   */
  public function getStatus(ConfigEntityInterface $model): bool;

  /**
   * Set version of the model.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $model
   *   The model.
   * @param string $version
   *   The version.
   *
   * @return $this
   */
  public function setVersion(ConfigEntityInterface $model, string $version): ModelOwnerInterface;

  /**
   * Get version from the model.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $model
   *   The model.
   *
   * @return string
   *   The version.
   */
  public function getVersion(ConfigEntityInterface $model): string;

  /**
   * Set template setting of the model.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $model
   *   The model.
   * @param bool $template
   *   The template setting.
   *
   * @return $this
   */
  public function setTemplate(ConfigEntityInterface $model, bool $template): ModelOwnerInterface;

  /**
   * Get template setting from the model.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $model
   *   The model.
   *
   * @return bool
   *   The template setting.
   */
  public function getTemplate(ConfigEntityInterface $model): bool;

  /**
   * Set storage of the model.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $model
   *   The model.
   * @param string $storage
   *   The storage.
   *
   * @return $this
   */
  public function setStorage(ConfigEntityInterface $model, string $storage): ModelOwnerInterface;

  /**
   * Get storage setting from the model.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $model
   *   The model.
   *
   * @return string
   *   The storage setting.
   */
  public function getStorage(ConfigEntityInterface $model): string;

  /**
   * Set documentation of the model.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $model
   *   The model.
   * @param string $documentation
   *   The documentation.
   *
   * @return $this
   */
  public function setDocumentation(ConfigEntityInterface $model, string $documentation): ModelOwnerInterface;

  /**
   * Get documentation from the model.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $model
   *   The model.
   *
   * @return string
   *   The documentation.
   */
  public function getDocumentation(ConfigEntityInterface $model): string;

  /**
   * Set Tags of the model.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $model
   *   The model.
   * @param array $tags
   *   The tags.
   *
   * @return $this
   */
  public function setTags(ConfigEntityInterface $model, array $tags): ModelOwnerInterface;

  /**
   * Get Tags from the model.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $model
   *   The model.
   *
   * @return array
   *   The tags.
   */
  public function getTags(ConfigEntityInterface $model): array;

  /**
   * Set changelog of the model.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $model
   *   The model.
   * @param string $changelog
   *   The changelog.
   *
   * @return $this
   */
  public function setChangelog(ConfigEntityInterface $model, string $changelog): ModelOwnerInterface;

  /**
   * Get changelog from the model.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $model
   *   The model.
   *
   * @return string
   *   The changelog.
   */
  public function getChangelog(ConfigEntityInterface $model): string;

  /**
   * Set annotations of the model.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $model
   *   The model.
   * @param \Drupal\modeler_api\Component[] $annotations
   *   The annotations.
   *
   * @return $this
   */
  public function setAnnotations(ConfigEntityInterface $model, array $annotations): ModelOwnerInterface;

  /**
   * Get annotations from the model.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $model
   *   The model.
   *
   * @return \Drupal\modeler_api\Component[]
   *   The annotations.
   */
  public function getAnnotations(ConfigEntityInterface $model): array;

  /**
   * Set colors of the model.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $model
   *   The model.
   * @param \Drupal\modeler_api\ComponentColor[] $colors
   *   The annotations.
   *
   * @return $this
   */
  public function setColors(ConfigEntityInterface $model, array $colors): ModelOwnerInterface;

  /**
   * Get colors from the model.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $model
   *   The model.
   *
   * @return \Drupal\modeler_api\ComponentColor[]
   *   The colors.
   */
  public function getColors(ConfigEntityInterface $model): array;

  /**
   * Set swimlanes of the model.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $model
   *   The model.
   * @param array $swimlanes
   *   The swimlanes.
   *
   * @return $this
   */
  public function setSwimlanes(ConfigEntityInterface $model, array $swimlanes): ModelOwnerInterface;

  /**
   * Get swimlanes from the model.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $model
   *   The model.
   *
   * @return array
   *   The swimlanes.
   */
  public function getSwimlanes(ConfigEntityInterface $model): array;

  /**
   * Set raw data of the model.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $model
   *   The model.
   * @param string $data
   *   The model data.
   *
   * @return $this
   */
  public function setModelData(ConfigEntityInterface $model, string $data): ModelOwnerInterface;

  /**
   * Get raw data from the model.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $model
   *   The model.
   *
   * @return string
   *   The raw data.
   */
  public function getModelData(ConfigEntityInterface $model): string;

  /**
   * Sets the modeler plugin ID to the model.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $model
   *   The model.
   * @param string $id
   *   The modeler ID.
   *
   * @return $this
   */
  public function setModelerId(ConfigEntityInterface $model, string $id): ModelOwnerInterface;

  /**
   * Gets the modeler plugin ID that edited the model.
   *
   * @return string
   *   The modeler plugin ID.
   */
  public function getModelerId(ConfigEntityInterface $model): string;

  /**
   * Gets the modeler plugin that edited the model.
   *
   * @return \Drupal\modeler_api\Plugin\ModelerApiModeler\ModelerInterface|null
   *   The modeler plugin, if it can be found, NULL otherwise.
   */
  public function getModeler(ConfigEntityInterface $model): ?ModelerInterface;

  /**
   * Gets the list of used components in the model.
   *
   * This method should be called by modelers to receive all the used
   * components. The base model owner class implements this as final. Model
   * owners should instead implement the self::usedComponents() method.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $model
   *   The model.
   *
   * @return \Drupal\modeler_api\Component[]
   *   The list of used components.
   */
  public function getUsedComponents(ConfigEntityInterface $model): array;

  /**
   * Gets the list of used components in the model.
   *
   * This needs to be implemented by model owners, but it shouldn't be called
   * by modelers. Instead they should call self::getUsedComponents().
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $model
   *   The model.
   *
   * @return \Drupal\modeler_api\Component[]
   *   The list of used components.
   */
  public function usedComponents(ConfigEntityInterface $model): array;

  /**
   * Gets the list of supported component types.
   *
   * @return array
   *   The list of supported component types with the modeler API component
   *   type as they key and a unique name as the value.
   */
  public function supportedOwnerComponentTypes(): array;

  /**
   * Returns cardinality constraints for component types.
   *
   * Model owners can declare minimum and maximum counts for each component
   * type. These constraints are enforced both server-side during save and
   * client-side before the save request is sent.
   *
   * Example return value:
   * @code
   * [
   *   Api::COMPONENT_TYPE_START   => ['min' => 1, 'max' => 1, 'successors' => ['min' => 1, 'max' => 1]],
   *   Api::COMPONENT_TYPE_ELEMENT => ['min' => 1, 'max' => 1, 'successors' => ['max' => 0]],
   *   Api::COMPONENT_TYPE_GATEWAY => ['successors' => ['min' => 1, 'max' => 1]],
   * ]
   * @endcode
   *
   * @return array<int, array{min?: int, max?: int, successors?: array{min?: int, max?: int}}>
   *   An associative array keyed by component type constant. Each value is
   *   an array with optional 'min' and 'max' keys for component count, and
   *   an optional 'successors' key with its own 'min'/'max' for the number
   *   of outgoing connections per component of that type. An empty array
   *   means no constraints (the default).
   */
  public function modelConstraints(): array;

  /**
   * Provides a list of available plugins for a given type.
   *
   * @param int $type
   *   The component type.
   *
   * @return \Drupal\Component\Plugin\PluginInspectionInterface[]
   *   The list of plugins.
   */
  public function availableOwnerComponents(int $type): array;

  /**
   * Provides a list of favorite plugin IDs grouped by type.
   *
   * @return array|array[]
   *   The list of favorite plugins grouped by type.
   */
  public function favoriteOwnerComponents(): array;

  /**
   * Provides an ID of an owner component for a given type.
   *
   * @param int $type
   *   The component type.
   *
   * @return string
   *   The owner component's ID.
   */
  public function ownerComponentId(int $type): string;

  /**
   * Provides the default config of an owner component for a given type and ID.
   *
   * @param int $type
   *   The component type.
   * @param string $id
   *   The component ID.
   *
   * @return array
   *   The default config.
   */
  public function ownerComponentDefaultConfig(int $type, string $id): array;

  /**
   * Tells the modeler if the given plugin can be edited in the UI.
   *
   * @param \Drupal\Component\Plugin\PluginInspectionInterface $plugin
   *   The plugin.
   *
   * @return bool
   *   TRUE, if the plugin is editable, FALSE otherwise.
   */
  public function ownerComponentEditable(PluginInspectionInterface $plugin): bool;

  /**
   * Tells the modeler if the given plugin can be removed.
   *
   * @param \Drupal\Component\Plugin\PluginInspectionInterface $plugin
   *   The plugin.
   *
   * @return bool
   *   TRUE, if the plugin can be removed and then replaced by another one,
   *   FALSE otherwise.
   */
  public function ownerComponentPluginChangeable(PluginInspectionInterface $plugin): bool;

  /**
   * Provides the owner component for a given type and ID.
   *
   * @param int $type
   *   The component type.
   * @param string $id
   *   The component ID.
   * @param array $config
   *   The plugin configuration.
   *
   * @return \Drupal\Component\Plugin\PluginInspectionInterface|null
   *   The owner component, or NULL if it can't be found.
   */
  public function ownerComponent(int $type, string $id, array $config = []): ?PluginInspectionInterface;

  /**
   * Prepares the plugin's configuration form and catches errors.
   *
   * @param \Drupal\Component\Plugin\PluginInspectionInterface $plugin
   *   The plugin.
   * @param string|null $modelId
   *   (Optional) The ID of the model entity for which the plugin config form
   *   should be built.
   * @param bool $modelIsNew
   *   (Optional) Flag to indicate if the model entity for which the plugin
   *   config form should be built is new or not.
   *
   * @return array
   *   The configuration form.
   */
  public function buildConfigurationForm(PluginInspectionInterface $plugin, ?string $modelId = NULL, bool $modelIsNew = TRUE): array;

  /**
   * Provides the owner component for a given type and ID.
   *
   * @param int $type
   *   The component type.
   * @param string $id
   *   The component ID.
   *
   * @return bool
   *   TRUE, if the plugin's configuration should not be validated, FALSE
   *   otherwise.
   */
  public function skipConfigurationValidation(int $type, string $id): bool;

  /**
   * Derives the config schema key for a plugin.
   *
   * This is used by the modeler for YAML schema discovery on textarea fields.
   *
   * @param \Drupal\Component\Plugin\PluginInspectionInterface $plugin
   *   The plugin instance.
   *
   * @return string
   *   The config schema key prefix (e.g. 'eca.event.plugin.eca_base:eca_tool'),
   *   or an empty string if no schema key is available.
   */
  public function getPluginSchemaKey(PluginInspectionInterface $plugin): string;

  /**
   * Provides the optional base URL to the offsite documentation.
   *
   * @return string|null
   *   The base URL to the offsite documentation, or NULL if no URL is
   *   configured.
   */
  public function docBaseUrl(): ?string;

  /**
   * Builds the URL to the offsite documentation for the given plugin.
   *
   * @param \Drupal\Component\Plugin\PluginInspectionInterface $plugin
   *   The plugin for which the documentation URL should be build.
   * @param string $pluginType
   *   The string identifying the plugin type, which is one of event, condition
   *   or action.
   *
   * @return string|null
   *   The URL to the offsite documentation, or NULL if no URL was generated.
   */
  public function pluginDocUrl(PluginInspectionInterface $plugin, string $pluginType): ?string;

  /**
   * Get the storage method from settings.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $model
   *   The model.
   *
   * @return string|null
   *   The storage method, if the required modeler is not "fallback", NULL
   *   otherwise.
   */
  public function storageMethod(ConfigEntityInterface $model): ?string;

  /**
   * Get the storage ID for separate storage.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $model
   *   The model.
   *
   * @return string
   *   The storage ID.
   */
  public function storageId(ConfigEntityInterface $model): string;

  /**
   * Allows model owner to prepare submitted config values.
   *
   * @param string|null $value
   *   The submitted config value.
   * @param string|null $replacement
   *   Variable may receive a replacement value which will only be used during
   *   validation and replaced back to the original value after validation.
   * @param array $element
   *   The form element.
   *
   * @return string|null
   *   If the preparation found an error before form validation, an error
   *   message should be return, NULL otherwise.
   */
  public function prepareFormFieldForValidation(?string &$value, ?string &$replacement, array $element): ?string;

  /**
   * Reset components in a model to add all current components afterwards.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $model
   *   The model.
   *
   * @return \Drupal\modeler_api\Plugin\ModelerApiModelOwner\ModelOwnerInterface
   *   $this
   */
  public function resetComponents(ConfigEntityInterface $model): ModelOwnerInterface;

  /**
   * Add a component to the model.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $model
   *   The model.
   * @param \Drupal\modeler_api\Component $component
   *   The component.
   *
   * @return bool
   *   TRUE, if the components was added successfully, FALSE otherwise.
   */
  public function addComponent(ConfigEntityInterface $model, Component $component): bool;

  /**
   * Helper function called after the last component has been added.
   *
   * This is only being called, if adding component happened without any errors
   * and if Api::prepareModelFromData() wasn't called in dry run mode.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $model
   *   The model.
   */
  public function finalizeAddingComponents(ConfigEntityInterface $model): void;

  /**
   * Update a component in the model.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $model
   *   The model.
   * @param \Drupal\modeler_api\Component $component
   *   The component.
   *
   * @return bool
   *   TRUE, if the components was updated successfully, FALSE otherwise.
   */
  public function updateComponent(ConfigEntityInterface $model, Component $component): bool;

  /**
   * Provides a list of strings containing infos about used components.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $model
   *   The model.
   *
   * @return string[]
   *   The list of infos.
   */
  public function usedComponentsInfo(ConfigEntityInterface $model): array;

  /**
   * Gets the default storage method for this model owner.
   *
   * @return string
   *   The default storage method for this model owner.
   */
  public function defaultStorageMethod(): string;

  /**
   * Flag whether the default storage is enforced.
   *
   * @return bool
   *   TRUE, if the storage method should not be changed for this model owner.
   *   Defaults to FALSE, so the user can decide.
   */
  public function enforceDefaultStorageMethod(): bool;

  /**
   * Determines if the model supports status.
   *
   * @return bool
   *   TRUE, if the model supports status, FALSE otherwise.
   */
  public function supportsStatus(): bool;

  /**
   * Determines if the model supports templates.
   *
   * @return bool
   *   TRUE, if the model supports templates, FALSE otherwise.
   */
  public function supportsTemplate(): bool;

  /**
   * Applies the template to a model, creates a new one if it doesn't exist.
   *
   * @param string $templateId
   *   The ID of the template entity.
   * @param string $componentId
   *   The component ID within the template.
   * @param string $target
   *   The target element.
   * @param array $hiddenConfig
   *   The hidden config.
   * @param array $config
   *   The config for the applied template.
   */
  public function applyTemplate(string $templateId, string $componentId, string $target, array $hiddenConfig = [], array $config = []): void;

  /**
   * Determines if the model supports replay data.
   *
   * @return bool
   *   TRUE, if the model supports replay data, FALSE otherwise.
   */
  public function supportsReplayData(): bool;

  /**
   * Provides replay data for a given hash.
   *
   * @param string $hash
   *   The hash representing the replay data.
   *
   * @return array
   *   An array with all the replay data.
   */
  public function getReplayData(string $hash): array;

  /**
   * Provides replay data for a given component in a model.
   *
   * @param string $modelId
   *   The model ID.
   * @param string $componentId
   *   The component ID.
   *
   * @return array
   *   An array with all the replay data.
   */
  public function getReplayDataByComponent(string $modelId, string $componentId): array;

  /**
   * Determines if the model supports testing.
   *
   * @return bool
   *   TRUE, if the model supports testing, FALSE otherwise.
   */
  public function supportsTesting(): bool;

  /**
   * Starts a test job for the given model and component.
   *
   * @param string $modelId
   *   The model ID.
   * @param string $componentId
   *   The component ID.
   *
   * @return string|\Drupal\Core\StringTranslation\TranslatableMarkup
   *   The job ID as a string, or an error message as a translatable string if
   *   the job could not be started.
   */
  public function startTestJob(string $modelId, string $componentId): string|TranslatableMarkup;

  /**
   * Polls the status of a test job.
   *
   * @param string $jobId
   *   The job ID.
   *
   * @return array|\Drupal\Core\StringTranslation\TranslatableMarkup|null
   *   Returns NULL if the job is still running, an array with the replay data
   *   if the job finished successfully, or an error message as a translatable
   *   string if the job failed.
   */
  public function pollTestJob(string $jobId): array|null|TranslatableMarkup;

  /**
   * Cancels a running test job and cleans up associated resources.
   *
   * Called when the user cancels a test in the modeler UI. This allows the
   * model owner to clean up any state that was set up for the test job (e.g.
   * reset debug mode if it was automatically enabled).
   *
   * @param string $jobId
   *   The job ID.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup|null
   *   NULL on success, or an error message as a translatable string if the
   *   cancellation failed.
   */
  public function cancelTestJob(string $jobId): null|TranslatableMarkup;

}
