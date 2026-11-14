<?php

declare(strict_types=1);

namespace Drupal\modeler_api\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\modeler_api\Entity\DataModel;
use Drupal\modeler_api\Plugin\ModelerApiModelOwner\ModelOwnerInterface;
use Drupal\modeler_api\Plugin\ModelerApiModeler\ModelerInterface;
use Drupal\modeler_api\Plugin\ModelerPluginManager;
use Drupal\modeler_api\Plugin\ModelOwnerPluginManager;
use Drupal\modeler_api\Plugin\ThemePluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure Modeler API settings for this site.
 */
final class Settings extends ConfigFormBase {

  public const string STORAGE_OPTION_NONE = 'none';
  public const string STORAGE_OPTION_THIRD_PARTY = 'third-party';
  public const string STORAGE_OPTION_SEPARATE = 'separate';

  /**
   * Provides a list of valid storage options.
   *
   * @see modeler_api.schema.yml
   *
   * @return string[]
   *   All valid storage options.
   */
  public static function validStorageOptions(): array {
    return [
      self::STORAGE_OPTION_NONE,
      self::STORAGE_OPTION_THIRD_PARTY,
      self::STORAGE_OPTION_SEPARATE,
    ];
  }

  public const bool DEFAULT_PROPERTY_PANEL = TRUE;

  /**
   * The theme option that lets the Modeler API pick an applicable theme.
   *
   * This is also the behavior when nothing is stored at all, so that a theme
   * built for an owner-modeler combination takes effect as soon as its module
   * is installed.
   *
   * @see \Drupal\modeler_api\Plugin\ThemePluginManager::resolveTheme()
   */
  public const string THEME_OPTION_AUTO = 'auto';

  /**
   * The theme option that leaves the modeler's own look and feel untouched.
   */
  public const string THEME_OPTION_DEFAULT = 'default';

  /**
   * The model owner plugin manager.
   *
   * @var \Drupal\modeler_api\Plugin\ModelOwnerPluginManager
   */
  protected ModelOwnerPluginManager $ownerPluginManager;

  /**
   * The modeler plugin manager.
   *
   * @var \Drupal\modeler_api\Plugin\ModelerPluginManager
   */
  protected ModelerPluginManager $modelerPluginManager;

  /**
   * The theme plugin manager.
   *
   * @var \Drupal\modeler_api\Plugin\ThemePluginManager
   */
  protected ThemePluginManager $themePluginManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    $instance = parent::create($container);
    $instance->ownerPluginManager = $container->get('plugin.manager.modeler_api.model_owner');
    $instance->modelerPluginManager = $container->get('plugin.manager.modeler_api.modeler');
    $instance->themePluginManager = $container->get('plugin.manager.modeler_api.theme');
    return $instance;
  }

  /**
   * Gets the config key for a given property.
   *
   * @param \Drupal\modeler_api\Plugin\ModelerApiModelOwner\ModelOwnerInterface $owner
   *   The model owner.
   * @param \Drupal\modeler_api\Plugin\ModelerApiModeler\ModelerInterface $modeler
   *   The modeler.
   * @param string $property
   *   The property.
   * @param bool $asArray
   *   Whether the key should be returned as a string or an array.
   *
   * @return array|string
   *   The config key as array or string.
   */
  public static function key(ModelOwnerInterface $owner, ModelerInterface $modeler, string $property, bool $asArray = FALSE): array|string {
    $parts = [
      'owner_modeler',
      $owner->getPluginId(),
      $modeler->getPluginId(),
      $property,
    ];
    return $asArray ? $parts : implode('.', $parts);
  }

  /**
   * Gets the config value for a given property.
   *
   * @param \Drupal\modeler_api\Plugin\ModelerApiModelOwner\ModelOwnerInterface $owner
   *   The model owner.
   * @param \Drupal\modeler_api\Plugin\ModelerApiModeler\ModelerInterface $modeler
   *   The modeler.
   * @param string $property
   *   The property.
   * @param bool|int|string $default
   *   The default value if no setting is being found.
   *
   * @return bool|int|string
   *   The config value.
   */
  public static function value(ModelOwnerInterface $owner, ModelerInterface $modeler, string $property, bool|int|string $default): bool|int|string {
    return \Drupal::configFactory()->get('modeler_api.settings')->get(self::key($owner, $modeler, $property)) ?? $default;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'modeler_api_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['modeler_api.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $storageOptions = [
      self::STORAGE_OPTION_NONE => $this->t('Do not store raw model data'),
      self::STORAGE_OPTION_SEPARATE => $this->t('Store raw data in separate config entity'),
      self::STORAGE_OPTION_THIRD_PARTY => $this->t('Store raw data with config as third-party setting'),
    ];
    $form['owner_modeler'] = [
      '#type' => 'details',
      '#title' => $this->t('Owner and modeler specific settings'),
      '#tree' => TRUE,
      '#open' => TRUE,
    ];
    foreach ($this->ownerPluginManager->getAllInstances() as $owner) {
      $form['owner_modeler'][$owner->getPluginId()] = [
        '#type' => 'details',
        '#title' => $this->t(':label', [':label' => $owner->label()]),
        '#open' => TRUE,
      ];
      foreach ($this->modelerPluginManager->getAllInstances() as $modeler) {
        if ($modeler->getPluginId() === 'fallback') {
          continue;
        }
        $form['owner_modeler'][$owner->getPluginId()][$modeler->getPluginId()] = [
          '#type' => 'details',
          '#title' => $this->t(':label', [':label' => $modeler->label()]),
          '#open' => TRUE,
        ];
        $themeOptions = [
          self::THEME_OPTION_AUTO => $this->t('Automatic'),
          self::THEME_OPTION_DEFAULT => $this->t('Default'),
        ];
        foreach ($this->themePluginManager->getThemesFor($owner->getPluginId(), $modeler->getPluginId()) as $themeId => $theme) {
          $themeOptions[$themeId] = $theme->getLabel();
        }
        // A previously selected theme may no longer be discoverable, either
        // because its module got uninstalled or because its restrictions
        // changed. Fall back to the default so that the select does not
        // produce an illegal choice error. The default is deliberately not
        // the automatic option here: a stale theme should stop applying, not
        // be replaced by a different one.
        $selectedTheme = (string) self::value($owner, $modeler, 'theme', self::THEME_OPTION_AUTO);
        if (!isset($themeOptions[$selectedTheme])) {
          $selectedTheme = self::THEME_OPTION_DEFAULT;
        }
        $form['owner_modeler'][$owner->getPluginId()][$modeler->getPluginId()]['theme'] = [
          '#type' => 'select',
          '#title' => $this->t('Theme'),
          '#default_value' => $selectedTheme,
          '#options' => $themeOptions,
          '#description' => $this->t('<em>Automatic</em> applies the theme that best matches this owner and modeler, and nothing at all if no module provides one. <em>Default</em> keeps the look and feel of the modeler itself. Selecting a theme pins that theme explicitly.'),
        ];
        $form['owner_modeler'][$owner->getPluginId()][$modeler->getPluginId()]['storage'] = [
          '#type' => 'select',
          '#title' => $this->t('Storage'),
          '#default_value' => self::value($owner, $modeler, 'storage', $owner->defaultStorageMethod()),
          '#options' => $storageOptions,
          '#disabled' => $owner->enforceDefaultStorageMethod(),
        ];
      }
    }
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $config = $this->config('modeler_api.settings');
    $ownersWithoutSeparateStorage = [];
    foreach ($this->ownerPluginManager->getAllInstances() as $owner) {
      foreach ($this->modelerPluginManager->getAllInstances() as $modeler) {
        if ($modeler->getPluginId() === 'fallback') {
          continue;
        }
        $config->set(self::key($owner, $modeler, 'theme'), $form_state->getValue(self::key($owner, $modeler, 'theme', TRUE)));
        $storage = $form_state->getValue(self::key($owner, $modeler, 'storage', TRUE));
        $config->set(self::key($owner, $modeler, 'storage'), $storage);
        if ($storage !== self::STORAGE_OPTION_SEPARATE) {
          // This needs to be the same as in the storage ID with empty model ID.
          // @see \Drupal\modeler_api\Plugin\ModelerApiModelOwner\ModelOwnerBase::storageId
          $ownersWithoutSeparateStorage[] = implode('_', [
            $owner->configEntityTypeId(),
            $modeler->getPluginId(),
            '',
          ]);
        }
      }
    }
    $config->save();
    parent::submitForm($form, $form_state);
    if ($ownersWithoutSeparateStorage) {
      foreach (DataModel::loadMultiple() as $model) {
        foreach ($ownersWithoutSeparateStorage as $idPrefix) {
          if (str_starts_with($model->id(), $idPrefix)) {
            $model->delete();
            break;
          }
        }
      }
    }
  }

}
