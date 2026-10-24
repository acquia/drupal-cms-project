<?php

declare(strict_types=1);

namespace Drupal\drupal_cms_helper\Hook;

use Composer\InstalledVersions;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Extension\Extension;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Installer\InstallerKernel;
use Drupal\Core\Recipe\RecipeRunner;
use Drupal\Core\State\StateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\drupal_cms_helper\ProjectResolver;
use Drupal\drupal_cms_helper\Telemetry;
use Drupal\drupal_cms_helper\TelemetryManager;

/**
 * Contains hooks related to telemetry collection.
 *
 * @internal
 *   This is an internal part of Drupal CMS and may be changed or removed at
 *   any time without warning. External code should not interact with this
 *   class.
 */
final class TelemetryHooks {

  use StringTranslationTrait;

  /**
   * How long to wait between pings.
   */
  public const int PING_INTERVAL = 86400 * 7;

  public function __construct(
    private readonly StateInterface $state,
    private readonly TelemetryManager $telemetry,
    public TimeInterface $time,
    private readonly ModuleExtensionList $moduleList,
  ) {}

  /**
   * Implements hook_modules_installed().
   *
   * @phpstan-param string[] $modules
   */
  #[Hook('modules_installed')]
  public function onModuleInstall(array $modules, bool $is_syncing): void {
    // If a recipe is applying, we are not actually syncing config; these things
    // NEVER happen simultaneously in real life.
    if (RecipeRunner::isApplying()) {
      $is_syncing = FALSE;
    }

    if ($is_syncing || InstallerKernel::installationAttempted()) {
      return;
    }

    foreach ($modules as $name) {
      $extension = $this->moduleList->get($name);

      // For privacy, do not send the names of custom modules.
      $this->telemetry->log(Telemetry::ModuleInstall, [
        'name' => $this->isCustom($extension) ? Telemetry::CUSTOM_MODULE : $name,
      ]);
    }
  }

  /**
   * Determines if an extension is custom.
   *
   * This isn't a surefire way to tell, but it's a reasonable heuristic. If the
   * extension's info has a `project` key, it has been packaged by drupal.org.
   * Otherwise, we have to assume it's custom.
   *
   * @param \Drupal\Core\Extension\Extension $extension
   *   An extension to examine.
   *
   * @return bool
   *   TRUE if the extension is (probably) custom, otherwise FALSE.
   */
  private function isCustom(Extension $extension): bool {
    if (str_starts_with($extension->getPath(), 'core/')) {
      return FALSE;
    }
    return empty($extension->info['project']);
  }

  /**
   * Implements hook_modules_uninstalled().
   *
   * @phpstan-param string[] $modules
   */
  #[Hook('modules_uninstalled')]
  public function onModuleUninstall(array $modules, bool $is_syncing): void {
    if ($is_syncing || InstallerKernel::installationAttempted()) {
      return;
    }
    foreach ($modules as $name) {
      if ($name === 'drupal_cms_installer') {
        continue;
      }
      $extension = $this->moduleList->get($name);

      // For privacy, do not send the names of custom modules.
      $this->telemetry->log(Telemetry::ModuleUninstall, [
        'name' => $this->isCustom($extension) ? Telemetry::CUSTOM_MODULE : $name,
      ]);
    }
  }

  /**
   * Implements hook_cron().
   */
  #[Hook('cron')]
  public function cron(): void {
    // Find out when we last sent a ping.
    $key = 'drupal_cms_telemetry.ping';
    $now = $this->time->getRequestTime();
    $last = $this->state->get($key, [0, 0]);
    // Adjust the value for backwards compatibility.
    if (is_numeric($last)) {
      $last = [$last, 0];
    }
    $last = array_map('intval', $last);

    // Send the number of pings we've done. This tells us how many weeks this
    // specific site has existed, which helps filter out tests, prototypes,
    // trials and demos.
    $extra = [
      'count' => ++$last[1],
    ];
    // If it's been long enough, send a ping to show that the site still exists.
    if ($now - $last[0] >= self::PING_INTERVAL) {
      ['name' => $project_name, 'install_path' => $project_root] = InstalledVersions::getRootPackage();

      if ($last[1] === 1) {
        $extra['opt_in'] = TRUE;

        // If the project was spun up from one of the official project
        // templates, send its package name so we have a relative sense of
        // which mechanisms are most often used to start projects.
        if ($project_name === 'drupal/cms' || $project_name === 'drupal/cms_cpanel') {
          $extra['project_name'] = $project_name;
        }
      }
      // Use a heuristic to determine if we are using the launcher app.
      $project_extra = ProjectResolver::readPackageInfo($project_root, 'extra');
      $extra['launcher'] = isset($project_extra['drupal-launcher']) && PHP_SAPI === 'cli-server';

      $this->telemetry->log(Telemetry::Ping, $extra);
      $last[0] = $now;
      $this->state->set($key, $last);
    }

    if ($this->telemetry->status()) {
      $this->telemetry->sendAll();
    }
  }

  /**
   * Implements hook_ENTITY_TYPE_insert().
   *
   * @phpstan-param \Drupal\Core\Config\Entity\ConfigEntityInterface $entity
   *   The entity being created.
   */
  #[Hook('js_component_insert')]
  public function onInsertCodeComponent(ConfigEntityInterface $entity): void {
    if (InstallerKernel::installationAttempted() || $entity->isSyncing()) {
      return;
    }
    $this->telemetry->log(Telemetry::CodeComponent);
  }

  /**
   * Implements hook_form_FORM_ID_alter().
   *
   * @phpstan-param array<mixed> $form
   */
  #[Hook('form_system_site_information_settings_alter')]
  public function alterSiteInformationForm(array &$form, FormStateInterface $form_state): void {
    $form['telemetry'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Send anonymous usage data'),
      '#default_value' => $this->telemetry->status(),
    ];
    if (TelemetryManager::isDisabled()) {
      $form['telemetry']['#disabled'] = TRUE;
      $form['telemetry']['#description'] = $this->t('Telemetry has been disabled in settings.php.');
    }
    else {
      $form['telemetry']['#description'] = $this->t('This helps improve Drupal CMS. We never collect personally identifiable information and you can change this at any time. <a href=":url">Read more about what we collect.</a>', [
        ':url' => 'https://project.pages.drupalcode.org/drupal_cms/updates/telemetry',
      ]);
      $form['#submit'][] = [$this, 'saveTelemetrySetting'];
    }
  }

  /**
   * Saves the telemetry opt-in setting.
   *
   * @param array<mixed> $form
   *   The fully built form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   */
  public function saveTelemetrySetting(array &$form, FormStateInterface $form_state): void {
    $this->telemetry->status((bool) $form_state->getValue('telemetry'));
  }

}
