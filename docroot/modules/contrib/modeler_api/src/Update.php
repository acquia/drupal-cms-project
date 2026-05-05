<?php

declare(strict_types=1);

namespace Drupal\modeler_api;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\modeler_api\Plugin\ModelOwnerPluginManager;

/**
 * Provides methods to update all existing models and to output messages.
 */
final class Update {

  /**
   * List of info messages.
   *
   * @var array
   */
  protected array $infos;

  /**
   * List of warning messages.
   *
   * @var array
   */
  protected array $warnings;

  /**
   * List of errors.
   *
   * @var array
   */
  protected array $errors;

  /**
   * Constructs an Update object.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly MessengerInterface $messenger,
    private readonly ModelOwnerPluginManager $modelOwnerPluginManager,
  ) {}

  /**
   * Updates all existing models calling ::updateModel in their modeler.
   */
  public function updateAllModels(): void {
    $this->errors = [];
    $this->warnings = [];
    $this->infos = [];
    foreach ($this->modelOwnerPluginManager->getAllInstances() as $owner) {
      $ownerPlugins = [];
      foreach ($owner->supportedOwnerComponentTypes() as $supportedOwnerComponentType) {
        $ownerPlugins[$supportedOwnerComponentType] = [];
      }
      /** @var \Drupal\Core\Config\Entity\ConfigEntityInterface $model */
      foreach ($this->entityTypeManager->getStorage($owner->configEntityTypeId())->loadMultiple() as $model) {
        $data = $owner->getModelData($model);
        if ($data === '') {
          // No raw data is stored, let's just update the model itself.
          $modelChanged = FALSE;
          foreach ($owner->usedComponents($model) as $usedComponent) {
            if (!isset($ownerPlugins[$usedComponent->getType()][$usedComponent->getPluginId()])) {
              $ownerPlugins[$usedComponent->getType()][$usedComponent->getPluginId()] = $owner->ownerComponentDefaultConfig($usedComponent->getType(), $usedComponent->getPluginId());
            }
            $defaultConfig = $ownerPlugins[$usedComponent->getType()][$usedComponent->getPluginId()];
            $config = $usedComponent->getConfiguration();
            $componentChanged = FALSE;
            foreach ($defaultConfig as $key => $value) {
              if (!isset($config['configuration'][$key])) {
                $config['configuration'][$key] = $value;
                $componentChanged = TRUE;
              }
            }
            if ($componentChanged) {
              $modelChanged = TRUE;
              $usedComponent->setConfiguration($config);
              $owner->updateComponent($model, $usedComponent);
            }
          }
          if ($modelChanged) {
            $this->warnings[] = '[' . $owner->label() . ' / ' . $model->label() . '] Model has been updated.';
            $model->save();
          }
          else {
            $this->infos[] = '[' . $owner->label() . ' / ' . $model->label() . '] Model does not require any updates.';
          }
        }
        else {
          $modeler = $owner->getModeler($model);
          $modeler->parseData($owner, $data);
          if ($modeler->updateComponents($owner)) {
            $modeler->parseData($owner, $data);
            $owner->resetComponents($model);
            $owner->setModelData($model, $modeler->getRawData());
            try {
              $hasError = FALSE;
              foreach ($modeler->readComponents() as $component) {
                if (!$owner->addComponent($model, $component)) {
                  $hasError = TRUE;
                }
              }
              if ($hasError) {
                $this->errors[] = '[' . $owner->label() . ' / ' . $model->label() . '] Unknown error while updating this model.';
              }
              else {
                $model->save();
                $this->infos[] = '[' . $owner->label() . ' / ' . $model->label() . '] Model has been updated.';
              }
            }
            catch (\Exception $e) {
              $this->errors[] = '[' . $owner->label() . ' / ' . $model->label() . '] Error while updating this model: ' . $e->getMessage();
            }
          }
          else {
            $this->infos[] = '[' . $owner->label() . ' / ' . $model->label() . '] Model does not require any updates.';
          }
        }
      }
    }
  }

  /**
   * Gets the list of all collected error messages.
   *
   * @return array
   *   The list of all collected error messages.
   */
  public function getErrors(): array {
    return $this->errors;
  }

  /**
   * Gets the list of all collected warning messages.
   *
   * @return array
   *   The list of all collected warning messages.
   */
  public function getWarnings(): array {
    return $this->warnings;
  }

  /**
   * Gets the list of all collected info messages.
   *
   * @return array
   *   The list of all collected info messages.
   */
  public function getInfos(): array {
    return $this->infos;
  }

  /**
   * Outputs all warning and error messages to user, plus a summary.
   */
  public function displayMessages(): void {
    foreach ($this->infos ?? [] as $info) {
      $this->messenger->addMessage($info);
    }
    foreach ($this->warnings ?? [] as $warning) {
      $this->messenger->addWarning($warning);
    }
    foreach ($this->errors ?? [] as $error) {
      $this->messenger->addError($error);
    }
    $this->messenger->addMessage("Models not requiring updates: " . count($this->infos ?? []));
    $this->messenger->addMessage("Models updated: " . count($this->warnings ?? []));
    $this->messenger->addMessage("Errors reported: " . count($this->errors ?? []));
  }

}
