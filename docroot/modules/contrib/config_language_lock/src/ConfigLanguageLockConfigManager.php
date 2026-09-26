<?php

namespace Drupal\config_language_lock;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\TraversableTypedDataInterface;
use Drupal\Core\TypedData\TypedDataInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Manages configuration language lock updates.
 */
class ConfigLanguageLockConfigManager {

  public function __construct(
    #[Autowire(service: 'config.storage')]
    protected StorageInterface $configStorage,
    protected ConfigFactoryInterface $configFactory,
    protected LanguageManagerInterface $languageManager,
    protected ModuleHandlerInterface $moduleHandler,
    protected ?object $localeConfigManager = NULL,
    protected ?TypedConfigManagerInterface $typedConfigManager = NULL,
  ) {
  }

  /**
   * Gets all active configuration names.
   *
   * The lock applies to all active configuration, not a per-extension subset,
   * because extension installs can pull config dependencies from already-
   * installed components.
   */
  public function getComponentNames(): array {
    return $this->configStorage->listAll();
  }

  /**
   * Updates config entries by combining langcode lock and translation swap.
   *
   * For each config object in $names, this method:
   * - Updates active langcode to the current locked langcode when present.
   * - If language config overrides are available and translatable data exists,
   *   deep-merges those translatable values into active config.
   * - Saves translatable parts of prior active config as override data in the
   *   old locked langcode.
   * - Deletes consumed override data in the new locked langcode.
   *
   * Works for both shipped config (via locale's default storage) and UI-created
   * config (via schema-based typed data extraction).
   */
  public function updateConfigForLockedLanguageSwitch(array $names): array {
    $stats = [
      'config_items_changed' => 0,
      'translations_updated' => 0,
      'translations_removed' => 0,
    ];
    $new_locked_langcode = $this->getLockedLangcode();
    if ($new_locked_langcode === NULL) {
      return $stats;
    }

    $default_langcode = $this->languageManager->getDefaultLanguage()->getId();
    $switching_to_default = $new_locked_langcode === $default_langcode;

    // Check for override storage capability (provided by language module).
    $new_override_storage = NULL;
    if (method_exists($this->languageManager, 'getLanguageConfigOverrideStorage')) {
      $new_override_storage = $this->languageManager->getLanguageConfigOverrideStorage($new_locked_langcode);
    }

    foreach ($names as $name) {
      $data = $this->configStorage->read($name);
      if (!is_array($data)) {
        continue;
      }

      $current_langcode = is_string($data['langcode'] ?? NULL) ? $data['langcode'] : NULL;

      $updated_data = $data;
      $changed = FALSE;

      if (array_key_exists('langcode', $updated_data) && $current_langcode !== $new_locked_langcode) {
        $updated_data['langcode'] = $new_locked_langcode;
        $changed = TRUE;
      }

      if ($new_override_storage && $current_langcode && $current_langcode !== $new_locked_langcode) {
        $translatable_map = $this->getTranslatableMap($name);
        if (!empty($translatable_map)) {
          $old_translation = $this->extractTranslatableData($data, $translatable_map);
          if (!empty($old_translation)) {
            $this->languageManager
              ->getLanguageConfigOverride($current_langcode, $name)
              ->setData($old_translation)
              ->save();
            $stats['translations_updated']++;
          }

          $incoming_override = $new_override_storage->read($name);
          $incoming_override = is_array($incoming_override) ? $incoming_override : [];
          if (empty($incoming_override) && $switching_to_default) {
            $incoming_override = $this->extractDefaultTranslatableData($translatable_map);
          }

          if (!empty($incoming_override)) {
            $merged = NestedArray::mergeDeepArray([$updated_data, $incoming_override], TRUE);
            if ($merged !== $updated_data) {
              $updated_data = $merged;
              $changed = TRUE;
            }

            if ($new_override_storage->exists($name)) {
              $new_override_storage->delete($name);
              $stats['translations_removed']++;
            }
          }
        }
      }

      if ($changed) {
        $this->configFactory->getEditable($name)
          ->setData($updated_data)
          ->save();
        $stats['config_items_changed']++;
      }
    }

    return $stats;
  }

  /**
   * Counts configuration objects that carry a langcode key.
   *
   * This is an estimate of how many items the batch would visit. It counts all
   * active config that has a langcode key, regardless of their current value.
   */
  public function countAffectedConfigItems(): int {
    $count = 0;
    foreach ($this->getComponentNames() as $name) {
      $data = $this->configStorage->read($name);
      if (!is_array($data) || !array_key_exists('langcode', $data)) {
        continue;
      }
      $count++;
    }
    return $count;
  }

  /**
   * Counts configuration objects by active langcode value.
   *
   * @return array<string, int>
   *   Keys are langcodes and values are number of config objects.
   */
  public function countConfigItemsByLangcode(): array {
    $counts = [];

    foreach ($this->getComponentNames() as $name) {
      $data = $this->configStorage->read($name);
      if (!is_array($data) || !array_key_exists('langcode', $data)) {
        continue;
      }

      $langcode = is_string($data['langcode']) ? $data['langcode'] : '';
      if ($langcode === '') {
        continue;
      }

      $counts[$langcode] = ($counts[$langcode] ?? 0) + 1;
    }

    return $counts;
  }

  /**
   * Gets the configured lock language with safety fallback.
   */
  public function getLockedLangcode(): ?string {
    $this->configFactory->reset('config_language_lock.settings');
    $configured = $this->configFactory->get('config_language_lock.settings')->get('locked_langcode');
    $languages = $this->languageManager->getLanguages(LanguageInterface::STATE_ALL);
    if (is_string($configured) && isset($languages[$configured])) {
      return $configured;
    }

    return NULL;
  }

  /**
   * Extracts translatable subset of config based on locale translatable map.
   */
  protected function extractTranslatableData(array $data, array $translatable_map): array {
    $extracted = [];
    foreach ($translatable_map as $key => $item) {
      if (!array_key_exists($key, $data)) {
        continue;
      }
      if (is_array($item) && is_array($data[$key])) {
        $nested = $this->extractTranslatableData($data[$key], $item);
        if (!empty($nested)) {
          $extracted[$key] = $nested;
        }
      }
      elseif (!is_array($item)) {
        $extracted[$key] = $data[$key];
      }
    }
    return $extracted;
  }

  /**
   * Extracts default source strings from locale translatable-map structure.
   */
  protected function extractDefaultTranslatableData(array $translatable_map): array {
    $defaults = [];
    foreach ($translatable_map as $key => $item) {
      if (is_array($item)) {
        $nested = $this->extractDefaultTranslatableData($item);
        if (!empty($nested)) {
          $defaults[$key] = $nested;
        }
      }
      elseif (is_object($item) && method_exists($item, 'getUntranslatedString')) {
        $defaults[$key] = $item->getUntranslatedString();
      }
    }

    return $defaults;
  }

  /**
   * Gets locale config manager service when available.
   */
  protected function getLocaleConfigManager(): ?object {
    return $this->moduleHandler->moduleExists('locale') ? $this->localeConfigManager : NULL;
  }

  /**
   * Gets translatable map for a config name.
   *
   * Tries locale's default config translatable map first (for shipped config),
   * then falls back to schema-based typed data extraction (for UI-created
   * config).
   *
   * @param string $name
   *   Config name.
   *
   * @return array
   *   Translatable map structure, or empty array if not available.
   */
  protected function getTranslatableMap(string $name): array {
    $locale_config_manager = $this->getLocaleConfigManager();

    // Try locale first (for shipped config with default storage).
    if ($locale_config_manager && method_exists($locale_config_manager, 'getTranslatableDefaultConfig')) {
      $translatable_map = $locale_config_manager->getTranslatableDefaultConfig($name);
      if (!empty($translatable_map)) {
        return $translatable_map;
      }
    }

    // Fall back to schema-based typed data (works for all config).
    if ($this->typedConfigManager && method_exists($this->typedConfigManager, 'createFromNameAndData')) {
      $data = $this->configStorage->read($name);
      if (is_array($data)) {
        try {
          $typed_config = $this->typedConfigManager->createFromNameAndData($name, $data);
          if ($typed_config instanceof TypedDataInterface) {
            // Use the same extraction logic as locale's
            // LocaleConfigManager::getTranslatableData().
            return $this->getTranslatableDataFromTypedConfig($typed_config);
          }
        }
        catch (\Exception $e) {
          // If typed data creation fails, return empty map.
          return [];
        }
      }
    }

    return [];
  }

  /**
   * Extracts translatable structure from config schema via typed data.
   *
   * This is a fallback for when locale's translatable-map is not available.
   * Copied from Drupal\locale\LocaleConfigManager::getTranslatableData()
   * (see core/modules/locale/src/LocaleConfigManager.php).
   * We copy rather than call that method because:
   * - It's protected and not accessible from outside locale module
   * - It's unavailable when locale module is not enabled
   * - This fallback enables translation swapping for manually-created config
   *   that has no entry in locale's default config storage.
   *
   * @param \Drupal\Core\TypedData\TypedDataInterface $element
   *   A typed configuration element.
   *
   * @return array
   *   A nested array structure matching translatable elements, with values
   *   wrapped in TranslatableMarkup for consistency with locale's format.
   */
  protected function getTranslatableDataFromTypedConfig(TypedDataInterface $element): array|TranslatableMarkup {
    $translatable = [];
    if ($element instanceof TraversableTypedDataInterface) {
      foreach ($element as $key => $property) {
        $value = $this->getTranslatableDataFromTypedConfig($property);
        if (!empty($value)) {
          $translatable[$key] = $value;
        }
      }
    }
    else {
      // Something is only translatable if there is a string in the first place.
      $value = $element->getValue();
      $definition = $element->getDataDefinition();
      if (!empty($definition['translatable']) && $value !== '' && $value !== NULL) {
        $options = [];
        if (isset($definition['translation context'])) {
          $options['context'] = $definition['translation context'];
        }
        // phpcs:ignore Drupal.Semantics.FunctionT.NotLiteralString
        return new TranslatableMarkup($value, [], $options);
      }
    }
    return $translatable;
  }

}
