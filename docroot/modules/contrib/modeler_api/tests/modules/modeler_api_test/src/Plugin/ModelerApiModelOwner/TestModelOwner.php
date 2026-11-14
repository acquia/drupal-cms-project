<?php

declare(strict_types=1);

namespace Drupal\modeler_api_test\Plugin\ModelerApiModelOwner;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\modeler_api\Attribute\ModelOwner;
use Drupal\modeler_api\Component;
use Drupal\modeler_api\Plugin\ModelerApiModelOwner\ModelOwnerBase;
use Drupal\modeler_api\Plugin\ModelerApiModelOwner\ModelOwnerInterface;

/**
 * Model owner for the test model config entity.
 *
 * Only the members that a model owner must provide are implemented, because
 * the tests using this plugin exercise the metadata accessors of the base
 * class rather than component handling.
 */
#[ModelOwner(
  id: 'modeler_api_test',
  label: new TranslatableMarkup('Modeler API test'),
  description: new TranslatableMarkup('Model owner for testing.'),
)]
final class TestModelOwner extends ModelOwnerBase {

  /**
   * {@inheritdoc}
   */
  public function modelIdExistsCallback(): array {
    return [self::class, 'modelIdExists'];
  }

  /**
   * Determines whether a model with the given ID already exists.
   *
   * @param string $id
   *   The model ID.
   *
   * @return bool
   *   TRUE if a model with that ID exists, FALSE otherwise.
   */
  public static function modelIdExists(string $id): bool {
    return \Drupal::entityTypeManager()
      ->getStorage('modeler_api_test_model')
      ->load($id) !== NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function configEntityProviderId(): string {
    return 'modeler_api_test';
  }

  /**
   * {@inheritdoc}
   */
  public function configEntityTypeId(): string {
    return 'modeler_api_test_model';
  }

  /**
   * {@inheritdoc}
   */
  public function configEntityBasePath(): ?string {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function usedComponents(ConfigEntityInterface $model): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function usedComponentsInfo(ConfigEntityInterface $model): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function supportedOwnerComponentTypes(): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function availableOwnerComponents(int $type): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function ownerComponentId(int $type): string {
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function ownerComponentDefaultConfig(int $type, string $id): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function ownerComponent(int $type, string $id, array $config = []): ?PluginInspectionInterface {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(PluginInspectionInterface $plugin, ?string $modelId = NULL, bool $modelIsNew = TRUE): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function resetComponents(ConfigEntityInterface $model): ModelOwnerInterface {
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function addComponent(ConfigEntityInterface $model, Component $component): bool {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function updateComponent(ConfigEntityInterface $model, Component $component): bool {
    return TRUE;
  }

}
