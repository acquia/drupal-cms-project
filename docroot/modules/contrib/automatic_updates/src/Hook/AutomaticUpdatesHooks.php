<?php

declare(strict_types=1);

namespace Drupal\automatic_updates\Hook;

use Drupal\automatic_updates\BatchProcessor;
use Drupal\automatic_updates\CronUpdateRunner;
use Drupal\automatic_updates\Validation\AdminStatusCheckMessages;
use Drupal\automatic_updates\Validation\StatusChecker;
use Drupal\Core\DependencyInjection\ClassResolverInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Hook\Attribute\RemoveHook;
use Drupal\Core\Installer\InstallerKernel;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\package_manager\ComposerInspector;
use Drupal\system\Controller\DbUpdateController;
use Drupal\update\Hook\UpdateHooks;

/**
 * Contains hook implementations for Automatic Updates.
 */
final class AutomaticUpdatesHooks {

  use StringTranslationTrait;

  /**
   * The service to handle admin-facing status check messages.
   */
  private readonly AdminStatusCheckMessages $adminStatusCheckMessages;

  public function __construct(
    private readonly RouteMatchInterface $routeMatch,
    ClassResolverInterface $classResolver,
    private readonly StatusChecker $statusChecker,
    private readonly CronUpdateRunner $cronUpdateRunner,
    private readonly UpdateHooks $updateHooks,
  ) {
    $this->adminStatusCheckMessages = $classResolver->getInstanceFromDefinition(AdminStatusCheckMessages::class);
  }

  /**
   * Implements hook_page_top().
   *
   * This overrides and wraps the Update module's hook_page_top()
   * implementation, only calling it in certain situations.
   */
  #[Hook('page_top')]
  #[RemoveHook('page_top', UpdateHooks::class, 'pageTop')]
  public function pageTop(): void {
    $this->adminStatusCheckMessages->displayAdminPageMessages();

    // @todo Rely on the route option after https://www.drupal.org/i/3236497 is
    //   committed.
    $skip_routes = [
      'automatic_updates.confirmation_page',
      'automatic_updates.update_form',
      'automatic_updates.module_update',
    ];
    if (!in_array($this->routeMatch->getRouteName(), $skip_routes, TRUE)) {
      $this->updateHooks->pageTop();
    }
  }

  /**
   * Implements hook_help().
   */
  #[Hook('help')]
  public function help(string $route_name): ?string {
    switch ($route_name) {
      case 'help.page.automatic_updates':
        $output = '<h3>' . $this->t('About') . '</h3>';
        $output .= '<p>' . $this->t('Automatic Updates lets you update Drupal core.') . '</p>';
        $output .= '<p>';
        $output .= $this->t('Automatic Updates will keep Drupal secure and up-to-date by automatically installing new patch-level updates, if available, when cron runs. It also provides a user interface to check if any updates are available and install them. You can <a href=":configure-form">configure Automatic Updates</a> to install all patch-level updates, only security updates, or no updates at all, during cron. By default, only security updates are installed during cron; this requires that you <a href=":update-form">install non-security updates through the user interface</a>.', [
          ':configure-form' => Url::fromRoute('update.settings')->toString(),
          ':update-form' => Url::fromRoute('automatic_updates.update_form')->toString(),
        ]);
        $output .= '</p>';
        $output .= '<p>' . $this->t('Additionally, Automatic Updates periodically runs checks to ensure that updates can be installed, and will warn site administrators if problems are detected.') . '</p>';
        $output .= '<h3>' . $this->t('Requirements') . '</h3>';
        $output .= '<p>' . $this->t('Automatic Updates requires a Composer executable whose version satisfies <code>@version</code>, and PHP must have permission to run it. The path to the executable may be set in the <code>package_manager.settings:executables.composer</code> config setting, or it will be automatically detected.', ['@version' => ComposerInspector::SUPPORTED_VERSION]) . '</p>';
        $output .= '<p>' . $this->t('For more information, see the <a href=":automatic-updates-documentation">online documentation for the Automatic Updates module</a>.', [':automatic-updates-documentation' => 'https://www.drupal.org/docs/8/update/automatic-updates']) . '</p>';
        $output .= '<h3 id="minor-update">' . $this->t('Updating to another minor version of Drupal') . '</h3>';
        $output .= '<p>';
        $output .= $this->t('Automatic Updates supports updating from one minor version of Drupal core to another; for example, from Drupal 9.4.8 to Drupal 9.5.0. This is only allowed when updating via <a href=":url">the user interface</a>. Unattended background updates can only update <em>within</em> the currently installed minor version (for example, Drupal 9.4.6 to 9.4.8).', [
          ':url' => Url::fromRoute('automatic_updates.update_form')->toString(),
        ]);
        $output .= '</p>';
        $output .= '<p>' . $this->t('This is because updating from one minor version of Drupal to another is riskier than staying within the current minor version. New minor versions of Drupal introduce changes that can, in some situations, be incompatible with installed modules and themes.') . '</p>';
        $output .= '<p>' . $this->t('Therefore, if you want to use Automatic Updates to update to another minor version of Drupal, it is strongly recommended to do a test update first, ideally on an isolated development copy of your site, before updating your production site.') . '</p>';
        return $output;
    }
    return NULL;
  }

  /**
   * Implements hook_mail().
   */
  #[Hook('mail')]
  public function mail(string $key, array &$message, array $params): void {
    // Explicitly pass the language code to all translated strings.
    $options = [
      'langcode' => $message['langcode'],
    ];
    if ($key === 'cron_successful') {
      $message['subject'] = $this->t("Drupal core was successfully updated", [], $options);
      $message['body'][] = $this->t('Congratulations!', [], $options);
      $message['body'][] = $this->t('Drupal core was automatically updated from @previous_version to @updated_version.', [
        '@previous_version' => $params['previous_version'],
        '@updated_version' => $params['updated_version'],
      ], $options);
    }
    elseif (str_starts_with($key, 'cron_failed')) {
      $message['subject'] = $this->t("Drupal core update failed", [], $options);

      // If this is considered urgent, prefix the subject line with a call to
      // action.
      if ($params['urgent']) {
        $message['subject'] = $this->t('URGENT: @subject', [
          '@subject' => $message['subject'],
        ], $options);
      }

      $message['body'][] = $this->t('Drupal core failed to update automatically from @previous_version to @target_version. The following error was logged:', [
        '@previous_version' => $params['previous_version'],
        '@target_version' => $params['target_version'],
      ], $options);
      $message['body'][] = $params['error_message'];

      // If the problem was not due to a failed apply, provide a link for the
      // site owner to do the update.
      if ($key !== 'cron_failed_apply') {
        $url = Url::fromRoute('automatic_updates.update_form')
          ->setAbsolute()
          ->toString();

        if ($key === 'cron_failed_insecure') {
          $message['body'][] = $this->t('Your site is running an insecure version of Drupal and should be updated as soon as possible. Visit @url to perform the update.', ['@url' => $url], $options);
        }
        else {
          $message['body'][] = $this->t('No immediate action is needed, but it is recommended that you visit @url to perform the update, or at least check that everything still looks good.', ['@url' => $url], $options);
        }
      }
    }
    elseif ($key === 'status_check_failed') {
      $message['subject'] = $this->t('Automatic updates readiness checks failed', [], $options);

      $url = Url::fromRoute('system.status')
        ->setAbsolute()
        ->toString();
      $message['body'][] = $this->t('Your site has failed some readiness checks for automatic updates and may not be able to receive automatic updates until further action is taken. Visit @url for more information.', [
        '@url' => $url,
      ], $options);
    }

    // If this email was related to an unattended update, explicitly state that
    // this isn't supported yet.
    if (str_starts_with($key, 'cron_')) {
      $message['body'][] = $this->t('This email was sent by the Automatic Updates module. Unattended updates are not yet fully supported.', [], $options);
      $message['body'][] = $this->t('If you are using this feature in production, it is strongly recommended for you to visit your site and ensure that everything still looks good.', [], $options);
    }
  }

  /**
   * Implements hook_modules_installed().
   */
  #[Hook('modules_installed')]
  public function modulesInstalled(array $modules, bool $is_syncing): void {
    // Invalidate stored status check results, in case the new modules provide
    // status checkers.
    $this->statusChecker->clearStoredResults();
    // If we're not in the middle of installing Drupal, or syncing config, go
    // ahead and run the status checks.
    if (!InstallerKernel::installationAttempted() && !$is_syncing) {
      $this->statusChecker->run();
    }

    // If cron updates are disabled status check messages will not be displayed
    // on admin pages. Therefore, after installing the module the user will not
    // be alerted to any problems until they access the status report page.
    if ($this->cronUpdateRunner->getMode() === CronUpdateRunner::DISABLED) {
      $this->adminStatusCheckMessages->displayResultSummary();
    }
  }

  /**
   * Implements hook_modules_uninstalled().
   */
  #[Hook('modules_uninstalled')]
  public function modulesUninstalled(): void {
    // Run the status checkers if needed when any modules are uninstalled in
    // case they provided status checkers.
    $this->statusChecker->run();
  }

  /**
   * Implements hook_batch_alter().
   *
   * @todo Remove this in https://www.drupal.org/i/3267817.
   */
  #[Hook('batch_alter')]
  public function batchAlter(array &$batch): void {
    foreach ($batch['sets'] as &$batch_set) {
      if (!empty($batch_set['finished']) && $batch_set['finished'] === [DbUpdateController::class, 'batchFinished']) {
        $batch_set['finished'] = [BatchProcessor::class, 'dbUpdateBatchFinished'];
      }
    }
  }

}
