<?php

declare(strict_types=1);

namespace Drupal\recipe_locale;

use Drupal\Core\Config\StorageInterface;
use Drupal\language\ConfigurableLanguageManagerInterface;
use Drupal\locale\LocaleDefaultConfigStorage;

/**
 * Lets Locale see the configuration that recorded recipes shipped.
 *
 * Locale only translates configuration it can compare with the original,
 * English copy that was shipped. It looks for that copy in the config
 * directories of installed modules, themes and the profile. Config that came
 * from a recipe is not there, so Locale skips it, even when the strings are
 * translated in the database. This storage adds the copies that this module
 * stored when the recipes were applied.
 *
 * @see \Drupal\locale\LocaleConfigManager::getDefaultConfigLangcode()
 */
final class RecipeDefaultConfigStorage extends LocaleDefaultConfigStorage {

  public function __construct(
    StorageInterface $config_storage,
    ConfigurableLanguageManagerInterface $language_manager,
    $install_profile,
    private readonly RecipeTracker $tracker,
  ) {
    parent::__construct($config_storage, $language_manager, $install_profile);
  }

  /**
   * {@inheritdoc}
   */
  public function read($name): array {
    $data = parent::read($name);
    if (!empty($data)) {
      return $data;
    }
    return $this->tracker->readShippedConfig($name) ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function listAll(): array {
    return array_values(array_unique(array_merge(parent::listAll(), $this->tracker->getShippedConfigNames())));
  }

}
