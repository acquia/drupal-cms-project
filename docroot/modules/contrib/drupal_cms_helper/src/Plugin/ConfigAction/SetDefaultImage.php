<?php

declare(strict_types=1);

namespace Drupal\drupal_cms_helper\Plugin\ConfigAction;

use Drupal\Core\Config\Action\Attribute\ConfigAction;
use Drupal\Core\Config\Action\ConfigActionPluginInterface;
use Drupal\Core\Config\ConfigManagerInterface;
use Drupal\Core\DependencyInjection\AutowiredInstanceTrait;
use Drupal\Core\Field\FieldConfigBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\field\FieldStorageConfigInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Sets the default image of an image field.
 *
 * This is needed because, by default, core will invalidate the default image
 * of an image field if it refers to a file entity that doesn't exist. However,
 * this situation can easily be encountered while applying a recipe, since
 * you could use a config action to set the default image to a file entity
 * that the recipe ships, but doesn't exist when config actions are applied.
 *
 * An example of using this in a recipe:
 * @code
 * field.field.node.article.field_image:
 *   # This is the UUID of a file entity that may or may not exist yet.
 *   setDefaultImage: 059dcb1d-3f0d-4390-89d7-6ebe2bc0d833
 * @endcode
 *
 * @api
 *   This is part of Drupal CMS's developer-facing API and may be relied upon.
 */
#[ConfigAction(
  id: 'setDefaultImage',
  admin_label: new TranslatableMarkup('Set default image'),
  entity_types: [
    'base_field_override',
    'field_config',
    'field_storage_config',
  ],
)]
final readonly class SetDefaultImage implements ConfigActionPluginInterface, ContainerFactoryPluginInterface {

  use AutowiredInstanceTrait;

  public function __construct(
    private ConfigManagerInterface $configManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return self::createInstanceAutowired($container);
  }

  /**
   * {@inheritdoc}
   */
  public function apply(string $configName, mixed $value): void {
    $field = $this->configManager->loadConfigEntityByName($configName);

    // Check assumptions...
    assert(
      // We need to be working on an entity that supports changing settings.
      ($field instanceof FieldConfigBase || $field instanceof FieldStorageConfigInterface) &&
      // And it must be an image field.
      $field->getType() === 'image' &&
      // The value is the UUID of a file entity, but it need not exist yet.
      is_string($value)
    );
    $field->setSetting('default_image', ['uuid' => $value] + $field->getSetting('default_image'))
      // We need to mark the field as syncing so that the default image won't
      // be changed to NULL if the file doesn't exist yet.
      // @see \Drupal\image\Hook\ImageHooks::entityPresave()
      ->setSyncing(TRUE)
      ->save();
  }

}
