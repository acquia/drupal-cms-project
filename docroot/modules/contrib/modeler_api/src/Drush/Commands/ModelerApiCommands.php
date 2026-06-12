<?php

namespace Drupal\modeler_api\Drush\Commands;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\modeler_api\ExportRecipe;
use Drupal\modeler_api\Plugin\ModelOwnerPluginManager;
use Drupal\modeler_api\Update;
use Drush\Attributes\Argument;
use Drush\Attributes\Command;
use Drush\Attributes\Option;
use Drush\Attributes\Usage;
use Drush\Commands\DrushCommands;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Modeler API Drush command file.
 */
final class ModelerApiCommands extends DrushCommands {

  /**
   * Constructs an ModelerApiCommands object.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly ModelOwnerPluginManager $modelOwnerPluginManager,
    private readonly ExportRecipe $exportRecipe,
    private readonly Update $update,
  ) {
    parent::__construct();
  }

  /**
   * Return an instance of these Drush commands.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The container.
   *
   * @return \Drupal\modeler_api\Drush\Commands\ModelerApiCommands
   *   The instance of Drush commands.
   */
  public static function create(ContainerInterface $container): ModelerApiCommands {
    return new ModelerApiCommands(
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.modeler_api.model_owner'),
      $container->get('modeler_api.export.recipe'),
      $container->get('modeler_api.update'),
    );
  }

  /**
   * Updates all existing models calling ::updateModel in their modeler.
   *
   * It is the modeler's responsibility to load all existing plugins and find
   * out if the model data, which is proprietary to them, needs to be updated.
   */
  #[Command(name: 'modeler_api:update', aliases: [])]
  #[Usage(name: 'modeler_api:update', description: 'Update all models if plugins got changed.')]
  public function updateAllModels(): void {
    $this->update->updateAllModels();
    $infos = $this->update->getInfos();
    $errors = $this->update->getErrors();
    $warnings = $this->update->getWarnings();
    if ($infos) {
      $this->io()->info(implode(PHP_EOL, $infos));
    }
    if ($errors) {
      $this->io()->error(implode(PHP_EOL, $errors));
    }
    if ($warnings) {
      $this->io()->warning(implode(PHP_EOL, $warnings));
    }
    $this->io()->info("Models not requiring updates: " . count($infos));
    $this->io()->info("Models updated: " . count($warnings));
    $this->io()->info("Errors reported: " . count($errors));
  }

  /**
   * Disable all existing models.
   */
  #[Command(name: 'modeler_api:disable', aliases: [])]
  #[Argument(name: 'owner_id', description: 'The owner of the models.')]
  #[Usage(name: 'modeler_api:disable', description: 'Disable all models of a owner plugin.')]
  public function disableAllModels(string $owner_id): void {
    $owner_id = mb_strtolower($owner_id);
    /** @var \Drupal\modeler_api\Plugin\ModelerApiModelOwner\ModelOwnerInterface $owner */
    $owner = $this->modelOwnerPluginManager->createInstance($owner_id);
    /** @var \Drupal\Core\Config\Entity\ConfigEntityInterface $model */
    foreach ($this->entityTypeManager->getStorage($owner->configEntityTypeId())->loadMultiple() as $model) {
      $owner->disable($model);
    }
  }

  /**
   * Enable all existing models.
   */
  #[Command(name: 'modeler_api:enable', aliases: [])]
  #[Argument(name: 'owner_id', description: 'The owner of the models.')]
  #[Usage(name: 'modeler_api:enable', description: 'Enable all models.')]
  public function enableAllModels(string $owner_id): void {
    $owner_id = mb_strtolower($owner_id);
    /** @var \Drupal\modeler_api\Plugin\ModelerApiModelOwner\ModelOwnerInterface $owner */
    $owner = $this->modelOwnerPluginManager->createInstance($owner_id);
    /** @var \Drupal\Core\Config\Entity\ConfigEntityInterface $model */
    foreach ($this->entityTypeManager->getStorage($owner->configEntityTypeId())->loadMultiple() as $model) {
      $owner->enable($model);
    }
  }

  /**
   * Export a model as a recipe.
   */
  #[Command(name: 'modeler_api:model:export', aliases: [])]
  #[Argument(name: 'owner_id', description: 'The owner of the models.')]
  #[Argument(name: 'id', description: 'The ID of the model.')]
  #[Option(name: 'namespace', description: 'The namespace of the composer package.')]
  #[Option(name: 'destination', description: 'The directory where to store the recipe.')]
  #[Usage(name: 'modeler_api:model:export OWNER_ID MODEL_ID', description: 'Export the model with the given ID as a recipe.')]
  #[Usage(name: 'modeler_api:model:export OWNER_ID MODEL_ID --namespace=your-vendor', description: 'Customize the recipe namespace (name prefix in composer.json).')]
  #[Usage(name: 'modeler_api:model:export OWNER_ID MODEL_ID --destination=../recipes/process_abc', description: 'Output the recipe at a custom relative path.')]
  public function exportModel(
    string $owner_id,
    string $id,
    array $options = [
      'namespace' => self::OPT,
      'destination' => self::OPT,
    ],
  ): void {
    $owner_id = mb_strtolower($owner_id);
    $id = mb_strtolower($id);
    /** @var \Drupal\modeler_api\Plugin\ModelerApiModelOwner\ModelOwnerInterface $owner */
    $owner = $this->modelOwnerPluginManager->createInstance($owner_id);
    /** @var \Drupal\Core\Config\Entity\ConfigEntityInterface|null $model */
    $model = $this->entityTypeManager->getStorage($owner->configEntityTypeId())->load($id);
    if ($model === NULL) {
      $this->io()->error('The given model does not exist!');
      return;
    }
    $namespace = $options['namespace'] ?? ExportRecipe::DEFAULT_NAMESPACE;
    $destination = $options['destination'] ?? ExportRecipe::DEFAULT_DESTINATION;
    $this->exportRecipe->doExport($owner, $model, NULL, $namespace, $destination);
  }

}
