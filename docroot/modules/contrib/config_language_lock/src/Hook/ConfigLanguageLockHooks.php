<?php

namespace Drupal\config_language_lock\Hook;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ConfigInstallerInterface;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\EntityFormInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Hook\Attribute\RemoveHook;
use Drupal\Core\Installer\InstallerKernel;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\config_language_lock\CanvasIntegrationChecker;
use Drupal\config_language_lock\ConfigLanguageLockBatch;

/**
 * Hook implementations for config_language_lock.
 *
 * Removes locale's extension install hooks so this module can take ownership
 * of config translation rewrites when a lock language is configured, and
 * delegate back to locale when no lock language is set.
 */
// Drupal 11.5.x onwards has these hook implementations.
// phpcs:ignore Drupal.Arrays.Array.LongLineDeclaration
#[RemoveHook('modules_installed', 'Drupal\locale\Hook\LocaleExtensionHooks', 'extensionsInstalled')]
// phpcs:ignore Drupal.Arrays.Array.LongLineDeclaration
#[RemoveHook('themes_installed', 'Drupal\locale\Hook\LocaleExtensionHooks', 'extensionsInstalled')]
// Drupal 11.1.x to 11.4.x had these hook implementations.
// phpcs:ignore Drupal.Arrays.Array.LongLineDeclaration
#[RemoveHook('modules_installed', 'Drupal\locale\Hook\LocaleHooks', 'modulesInstalled')]
// phpcs:ignore Drupal.Arrays.Array.LongLineDeclaration
#[RemoveHook('themes_installed', 'Drupal\locale\Hook\LocaleHooks', 'themesInstalled')]
class ConfigLanguageLockHooks {

  use StringTranslationTrait;

  /**
   * Constructs hook implementations.
   */
  public function __construct(
    protected readonly ConfigLanguageLockBatch $batch,
    protected readonly ConfigFactoryInterface $configFactory,
    protected readonly LanguageManagerInterface $languageManager,
    protected readonly ModuleHandlerInterface $moduleHandler,
    protected readonly ConfigInstallerInterface $configInstaller,
    protected readonly CanvasIntegrationChecker $canvasChecker,
  ) {
  }

  /**
   * Returns the notice explaining why the site default language is locked.
   *
   * @return array|null
   *   A render array, or NULL when the site default language can be changed.
   */
  protected function canvasDefaultLanguageNotice(): ?array {
    if (!$this->canvasChecker->isCanvasInstalled()) {
      return NULL;
    }
    $canvas_page_count = $this->canvasChecker->canvasPageCount();
    if ($canvas_page_count === 0) {
      return NULL;
    }

    // The wording depends on the pages, the front page and delete access.
    $cache = [
      'tags' => ['canvas_page_list', 'config:system.site'],
      'contexts' => ['user.permissions'],
    ];
    $pages_url = Url::fromUri('internal:/admin/content/pages')->toString();

    // The front page cannot be deleted, so it has to be pointed elsewhere
    // before the pages can go.
    // @see \Drupal\canvas\Hook\PageHooks::preventHomepageDeletion()
    if ($this->canvasChecker->frontPageIsCanvasPage()) {
      return [
        '#cache' => $cache,
        'reason' => [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#value' => $this->formatPlural(
            $canvas_page_count,
            'The site default language cannot be changed because Drupal Canvas requires all Canvas pages to be in the site default language, and there is currently 1 Canvas page. It is the front page, so it cannot be deleted yet. To change the site default language:',
            'The site default language cannot be changed because Drupal Canvas requires all Canvas pages to be in the site default language, and there are currently @count Canvas pages. One of them is the front page, so it cannot be deleted yet. To change the site default language:',
          ),
        ],
        'steps' => [
          '#theme' => 'item_list',
          '#list_type' => 'ol',
          '#items' => [
            $this->t('<a href=":front_url">Choose a front page</a> that is not a Canvas page.', [
              ':front_url' => Url::fromRoute('system.site_information_settings')->toString(),
            ]),
            $this->formatPlural(
              $canvas_page_count,
              '<a href=":pages_url">Delete the Canvas page</a>.',
              '<a href=":pages_url">Delete all Canvas pages</a>.',
              [':pages_url' => $pages_url],
            ),
          ],
        ],
      ];
    }

    // Send a lone page straight to its delete form, otherwise to the listing.
    $single_page = $this->canvasChecker->singleDeletablePage();
    $delete_url = $single_page === NULL
      ? $pages_url
      : $single_page->toUrl('delete-form')->toString();

    return [
      '#cache' => $cache,
      'reason' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->formatPlural(
          $canvas_page_count,
          'The site default language cannot be changed because Drupal Canvas requires all Canvas pages to be in the site default language, and there is currently 1 Canvas page. <a href=":delete_url">Delete it</a> to enable changing the site default language.',
          'The site default language cannot be changed because Drupal Canvas requires all Canvas pages to be in the site default language, and there are currently @count Canvas pages. <a href=":delete_url">Delete them</a> to enable changing the site default language.',
          [':delete_url' => $delete_url],
        ),
      ],
    ];
  }

  /**
   * Implements hook_modules_installed().
   *
   * Reacts to module installs by queueing lock-language config rewrites.
   *
   * We only act when a lock language is explicitly configured and skip all
   * behavior during config sync or installer bootstrap. When no lock language
   * is set, locale's translation import logic runs instead (if locale is
   * enabled).
   */
  #[Hook('modules_installed')]
  public function modulesInstalled(array $modules, bool $is_syncing): void {
    if ($is_syncing || InstallerKernel::installationAttempted()) {
      return;
    }

    if ($this->getLockedLangcode() === NULL) {
      $this->delegateToLocale($modules);
      return;
    }

    if ($batch = $this->batch->buildBatch()) {
      batch_set($batch);
    }
  }

  /**
   * Implements hook_themes_installed().
   *
   * Reacts to theme installs by queueing lock-language config rewrites.
   *
   * New theme config can contain language-sensitive values, so we run the same
   * batch update path as module installs once lock mode is enabled. When no
   * lock language is set, locale's translation import logic runs instead (if
   * locale is enabled).
   */
  #[Hook('themes_installed')]
  public function themesInstalled(array $themes): void {
    if ($this->configInstaller->isSyncing() || InstallerKernel::installationAttempted()) {
      return;
    }

    if ($this->getLockedLangcode() === NULL) {
      $this->delegateToLocale($themes);
      return;
    }

    if ($batch = $this->batch->buildBatch()) {
      batch_set($batch);
    }
  }

  /**
   * Implements hook_entity_access().
   *
   * Prevents deleting the language configured as configuration language.
   *
   * This protects module invariants and automatically removes delete UI where
   * language operations are rendered from entity access results.
   */
  #[Hook('entity_access')]
  public function entityAccess(EntityInterface $entity, $operation, AccountInterface $account): AccessResultInterface {
    $locked_langcode = $this->getLockedLangcode();
    if ($locked_langcode === NULL) {
      return AccessResult::neutral();
    }

    if ($entity->getEntityTypeId() === 'configurable_language' && $operation === 'delete') {
      $forbidden = $entity->id() === $locked_langcode;
      return AccessResult::forbiddenIf($forbidden)->addCacheableDependency($entity);
    }
    return AccessResult::neutral();
  }

  /**
   * Implements hook_form_FORM_ID_alter() for language_admin_overview_form().
   *
   * Core lets administrators pick the site default language with a radio
   * button in every row of the language table. This module removes those radio
   * buttons and offers a select list in a "Default language" fieldset below
   * the table instead. The fieldset has room to explain why the site default
   * language cannot be changed while Drupal Canvas pages exist.
   *
   * Because the radio buttons no longer show which language is the site
   * default, the table labels are annotated instead: "(Site default language)",
   * "(Configuration language)", or "(Site default and configuration language)"
   * when one language plays both roles.
   *
   * When follow-site-default is enabled, a submit handler is appended that
   * updates the lock and queues the rewrite batch on site default changes.
   */
  #[Hook('form_language_admin_overview_form_alter')]
  public function formLanguageAdminOverviewFormAlter(
    array &$form,
    FormStateInterface $form_state,
  ): void {
    $locked_langcode = $this->getLockedLangcode();
    $default_langcode = $this->languageManager->getDefaultLanguage()->getId();

    // The annotations and the select list depend on these settings.
    $form['#cache']['tags'][] = 'config:system.site';
    $form['#cache']['tags'][] = 'config:config_language_lock.settings';

    if (!isset($form['languages']) || !is_array($form['languages'])) {
      return;
    }

    // Drop the "Default" radio button column and annotate the labels instead.
    unset($form['languages']['#header']['default']);
    $options = [];
    foreach (Element::children($form['languages']) as $langcode) {
      $langcode = (string) $langcode;
      $row = &$form['languages'][$langcode];
      unset($row['default']);
      if (!isset($row['label']['#plain_text']) || !is_string($row['label']['#plain_text'])) {
        continue;
      }
      $options[$langcode] = $row['label']['#plain_text'];
      $annotation = $this->languageRowAnnotation($langcode, $default_langcode, $locked_langcode);
      if ($annotation !== NULL) {
        $row['label']['#plain_text'] .= ' (' . $annotation . ')';
      }
    }
    unset($row);

    if ($options === []) {
      return;
    }

    // Offer the site default language in its own fieldset below the table. The
    // select list submits the same form value as core's radio buttons did, so
    // core's validation and submit handlers keep working unchanged.
    $form['default_language'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Default language'),
      '#weight' => 5,
    ];
    $form['default_language']['site_default_language'] = [
      '#type' => 'select',
      '#title' => $this->t('Site default language'),
      '#options' => $options,
      '#default_value' => $default_langcode,
      '#parents' => ['site_default_language'],
    ];
    if (isset($form['actions'])) {
      $form['actions']['#weight'] = 10;
    }

    $notice = $this->canvasDefaultLanguageNotice();
    if ($notice !== NULL) {
      // Input from disabled elements is ignored, so the current site default
      // is kept. The validation handler is a safety net on top of that.
      $form['default_language']['site_default_language']['#disabled'] = TRUE;
      $form['default_language']['locked_notice'] = $notice + ['#weight' => 10];
      $form['#validate'][] = self::class . '::validateCanvasLanguageChange';
    }

    $follow = $this->configFactory
      ->get('config_language_lock.settings')
      ->get('follow_site_default');
    if ($follow) {
      $form['#submit'][] = self::class . ':languageAdminOverviewFormSubmit';
    }
  }

  /**
   * Builds the note shown after a language name in the language overview.
   *
   * @param string $langcode
   *   The language of the table row.
   * @param string $default_langcode
   *   The site default language.
   * @param string|null $locked_langcode
   *   The configuration language, or NULL when no lock is set.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup|null
   *   The note, or NULL when the language plays neither role.
   */
  protected function languageRowAnnotation(string $langcode, string $default_langcode, ?string $locked_langcode): ?TranslatableMarkup {
    $is_default = $langcode === $default_langcode;
    $is_locked = $langcode === $locked_langcode;
    if ($is_default && $is_locked) {
      return $this->t('Site default and configuration language');
    }
    if ($is_default) {
      return $this->t('Site default language');
    }
    if ($is_locked) {
      return $this->t('Configuration language');
    }
    return NULL;
  }

  /**
   * Submit handler for language overview form when follow-site-default is on.
   *
   * Updates locked_langcode to the new site default and queues the rewrite
   * batch so all configuration is rewritten to the new language.
   */
  public function languageAdminOverviewFormSubmit(
    array &$form,
    FormStateInterface $form_state,
  ): void {
    $new_default = $form_state->getValue('site_default_language');
    if (!is_string($new_default) || $new_default === '') {
      return;
    }

    $settings = $this->configFactory->get('config_language_lock.settings');
    if ($new_default === $settings->get('locked_langcode')) {
      return;
    }

    $this->configFactory->getEditable('config_language_lock.settings')
      ->set('locked_langcode', $new_default)
      ->save();

    if ($batch = $this->batch->buildBatch()) {
      batch_set($batch);
    }
  }

  /**
   * Validation handler that prevents site default language changes with Canvas.
   */
  public static function validateCanvasLanguageChange(
    array &$form,
    FormStateInterface $form_state,
  ): void {
    $current_default = \Drupal::languageManager()->getDefaultLanguage()->getId();
    $new_default = $form_state->getValue('site_default_language');
    if (is_string($new_default) && $new_default !== $current_default) {
      $form_state->setErrorByName('site_default_language', t('The site default language cannot be changed while Drupal Canvas pages exist. Remove all Canvas pages first.'));
    }
  }

  /**
   * Implements hook_form_alter().
   *
   * Hides language selectors on targeted config entity edit forms.
   *
   * We remove per-form language choice because this module enforces one
   * configuration language globally and applies it again on presave.
   */
  #[Hook('form_alter')]
  public function formAlter(array &$form, FormStateInterface $form_state): void {
    $locked_langcode = $this->getLockedLangcode();
    $form_object = $form_state->getFormObject();

    if ($locked_langcode !== NULL && $form_object instanceof EntityFormInterface) {
      $entity = $form_object->getEntity();
      $entity_type_id = $entity->getEntityTypeId();
      $locked_types = ['menu', 'taxonomy_vocabulary', 'date_format', 'view'];
      if ($entity instanceof ConfigEntityInterface && in_array($entity_type_id, $locked_types, TRUE)) {
        if (isset($form['langcode'])) {
          $form['langcode']['#access'] = FALSE;
        }
      }
    }

    if ($locked_langcode !== NULL && $form_object && $form_object->getFormId() === 'views_ui_edit_details_form') {
      if (isset($form['details']['langcode'])) {
        $form['details']['langcode']['#access'] = FALSE;
      }
    }
  }

  /**
   * Implements hook_entity_presave().
   *
   * Enforces locked language at write time for config entities with langcode.
   *
   * This is the final guarantee that saved config entities use the configured
   * lock language, regardless of form state, current request language, or
   * programmatic writes.
   */
  #[Hook('entity_presave')]
  public function entityPresave(EntityInterface $entity): void {
    $locked_langcode = $this->getLockedLangcode();
    if ($locked_langcode === NULL) {
      return;
    }

    if ($entity instanceof ConfigEntityInterface && $entity->getEntityType()->hasKey('langcode')) {
      $entity->set('langcode', $locked_langcode);
    }
  }

  /**
   * Delegates extension install handling to locale when it is enabled.
   *
   * @param array $extensions
   *   The installed extension names.
   */
  protected function delegateToLocale(array $extensions): void {
    if (!$this->moduleHandler->moduleExists('locale')) {
      return;
    }
    // New hook available from Drupal 11.5.x.
    $service_id = 'Drupal\locale\Hook\LocaleExtensionHooks';
    // @phpstan-ignore globalDrupalDependencyInjection.useDependencyInjection
    if (\Drupal::hasService($service_id)) {
      // @phpstan-ignore globalDrupalDependencyInjection.useDependencyInjection
      \Drupal::service($service_id)->extensionsInstalled($extensions);
    }
    elseif (function_exists('locale_system_update')) {
      // For Drupal versions prior to 11.5.x. 'module' is not technically
      // always correct, but has the same effect as 'theme', and this
      // deprecated function isn't going to change anymore.
      locale_system_update(['module' => $extensions]);
    }
  }

  /**
   * Gets configured lock language, if explicitly set to a known language.
   */
  protected function getLockedLangcode(): ?string {
    $this->configFactory->reset('config_language_lock.settings');
    $configured = $this->configFactory->get('config_language_lock.settings')->get('locked_langcode');
    $languages = $this->languageManager->getLanguages(LanguageInterface::STATE_ALL);
    if (is_string($configured) && isset($languages[$configured])) {
      return $configured;
    }

    return NULL;
  }

}
