<?php

declare(strict_types=1);

namespace Drupal\drupal_cms_helper\Hook;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Utility\Crypt;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Installer\InstallerKernel;
use Drupal\Core\PrivateKey;
use Drupal\Core\Recipe\RecipeRunner;
use Drupal\Core\Routing\AdminContext;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\drupal_cms_helper\Form\TelemetryOptInForm;
use Drupal\drupal_cms_helper\Telemetry;
use Drupal\drupal_cms_helper\TelemetryManager;
use Drupal\user\UserInterface;

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
  public const int PING_INTERVAL = 86400 * 30;

  public function __construct(
    private readonly StateInterface $state,
    private readonly TelemetryManager $telemetry,
    public TimeInterface $time,
    private readonly PrivateKey $privateKey,
    private readonly AdminContext $adminContext,
    private readonly AccountInterface $currentUser,
    private readonly FormBuilderInterface $formBuilder,
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
    $is_syncing ^= RecipeRunner::isApplying();

    if ($is_syncing || InstallerKernel::installationAttempted()) {
      return;
    }
    foreach ($modules as $name) {
      $this->telemetry->log(Telemetry::ModuleInstall, ['name' => $name]);
    }
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
      $this->telemetry->log(Telemetry::ModuleUninstall, ['name' => $name]);
    }
  }

  /**
   * Implements hook_cron().
   */
  #[Hook('cron')]
  public function cron(): void {
    if ($this->telemetry->status()) {
      // If it's been long enough, send a ping to show that the site still
      // exists.
      $key = 'drupal_cms_telemetry.ping';
      $now = $this->time->getRequestTime();
      $last = (int) $this->state->get($key);
      if ($now - $last >= self::PING_INTERVAL) {
        $this->telemetry->log(Telemetry::Ping);
        $this->state->set($key, $now);
      }

      $this->telemetry->sendAll();
    }
  }

  /**
   * Implements hook_user_login().
   */
  #[Hook('user_login')]
  public function onLogin(UserInterface $account): void {
    // We need to know how many unique users log in, but we want to know as
    // little as possible about those users. Therefore, use the user's numeric
    // ID, HMACed with the site's private key, which is never exposed and does
    // not travel between environments. There's no way to personally identify
    // users from this.
    $this->telemetry->log(Telemetry::Login, [
      'id' => Crypt::hmacBase64($account->id(), $this->privateKey->get()),
    ]);
  }

  /**
   * Implements hook_ENTITY_TYPE_insert().
   */
  #[Hook('js_component_insert')]
  public function onInsertCodeComponent(): void {
    $this->telemetry->log(Telemetry::CodeComponent);
  }

  /**
   * Implements hook_gin_ignore_sticky_form_actions().
   *
   * @phpstan-return string[]
   */
  #[Hook('gin_ignore_sticky_form_actions')]
  public function ignoreStickyFormActions(): array {
    // The opt-in form is shown in a modal dialog, so Gin must not move its
    // submit button into the page header, where the user would never see it.
    // @see \Drupal\gin\GinContentFormHelper::stickyActionButtons()
    return ['drupal_cms_telemetry_opt_in_form'];
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
      '#default_value' => $this->telemetry->status() ?? TRUE,
      '#description' => $this->t('This helps improve Drupal CMS. We never collect personally identifiable information and you can change this at any time. <a href=":url">Read more about what we collect.</a>', [
        ':url' => 'https://project.pages.drupalcode.org/drupal_cms/updates/telemetry',
      ]),
    ];
    $form['actions']['submit']['#submit'][] = [$this, 'saveTelemetrySetting'];
  }

  /**
   * Implements hook_page_bottom().
   *
   * @param array<mixed> $page
   *   The page being rendered.
   */
  #[Hook('page_bottom')]
  public function addOptInDialog(array &$page): void {
    // Only build the form if the user has not yet chosen whether to opt in,
    // and has permission to make that choice. Building the form anyway would
    // waste resources and, worse, interfere with other forms on the page: the
    // Gin theme collects submit buttons from every form on the current route
    // and moves them into the page header, which steals the main form's Save
    // button if this dialog's buttons are rendered after it.
    // @see \Drupal\gin\GinContentFormHelper::formAfterBuild()
    if (
      $this->telemetry->status() === NULL &&
      $this->currentUser->hasPermission('administer site configuration') &&
      $this->adminContext->isAdminRoute()
    ) {
      $page['telemetry'] = [
        '#type' => 'html_tag',
        '#tag' => 'dialog',
        'form' => $this->formBuilder->getForm(TelemetryOptInForm::class),
        '#attached' => [
          'library' => ['drupal_cms_helper/telemetry'],
        ],
      ];
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
