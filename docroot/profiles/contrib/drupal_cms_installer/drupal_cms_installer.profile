<?php

/**
 * @file
 * Contains hook implementations and callbacks for the Drupal CMS installer.
 *
 * @internal
 *   Everything in the Drupal CMS installer is internal and may be changed or
 *   removed at any time without warning.
 */

declare(strict_types=1);

use Drupal\config_language_lock\ConfigLanguageLockBatch;
use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\Extension\ExtensionDiscovery;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Extension\ModuleInstallerInterface;
use Drupal\Core\Extension\ThemeExtensionList;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Installer\Exception\InstallerException;
use Drupal\Core\Language\Language;
use Drupal\Core\Language\LanguageManager;
use Drupal\Core\Recipe\Recipe;
use Drupal\Core\Recipe\RecipeRunner;
use Drupal\Core\Url;
use Drupal\drupal_cms_installer\ComposerExecutor;
use Drupal\drupal_cms_installer\FileTranslation;
use Drupal\drupal_cms_installer\Form\SiteNameForm;
use Drupal\drupal_cms_installer\Form\SiteSettingsForm;
use Drupal\drupal_cms_installer\Form\SiteTemplateForm;
use Drupal\drupal_cms_installer\SiteTemplate;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\locale\LocaleConfigBatch;
use Drupal\locale\LocaleDefaultOptions;
use Drupal\locale\LocaleFetch;
use Drupal\locale\LocaleProjectRepository;
use Drupal\user\Entity\User;

/**
 * Implements hook_install_tasks_alter().
 *
 * @phpstan-param array<mixed> $tasks
 */
function drupal_cms_installer_install_tasks_alter(array &$tasks): void {
  // Add a translator that always loads this profile's translations from a file.
  // This avoids us needing to import the profile's translations, which are
  // useless after installation, into the database. Because translators are
  // tagged services, this works in all stages of the installer.
  Drupal::translation()->addTranslator(
    new FileTranslation(
      Drupal::service('kernel')->getSitePath() . '/files/translations',
      Drupal::service(FileSystemInterface::class),
    ),
  );

  $insert_before = function (string $key, array $additions) use (&$tasks): void {
    $key = array_search($key, array_keys($tasks), TRUE);
    if ($key === FALSE) {
      return;
    }
    // This isn't very clean, but it's the only way to positionally splice
    // into an associative (and therefore by definition unordered) array.
    $tasks_before = array_slice($tasks, 0, $key, TRUE);
    $tasks_after = array_slice($tasks, $key, NULL, TRUE);
    $tasks = $tasks_before + $additions + $tasks_after;
  };

  // Skip the standalone language selection step in favor of the language
  // switcher added to the early installer by the theme.
  // @see drupal_cms_installer_theme_preprocess_install_page()
  $tasks['install_select_language']['run'] = INSTALL_TASK_SKIP;

  // Install in English by default.
  global $install_state;
  $install_state['parameters']['langcode'] ??= 'en';

  // Once a site template has been chosen, the installer may have decided on a
  // different site default language than the one chosen at the start, because
  // the template ships its content in specific languages. Core derives the
  // site default language from the `langcode` parameter on every request, so
  // that decision has to be applied again on every request.
  // @see \Drupal\drupal_cms_installer\Form\SiteTemplateForm::submitForm()
  // @see install_begin_request()
  $template = Drupal::state()->get(SiteTemplateForm::STATE_KEY);
  if ($template instanceof SiteTemplate && $template->userChosenLangcode) {
    $site_default_langcode = $template->getDefaultLangcode();

    // Install in the chosen language.
    $install_state['parameters']['langcode'] = $site_default_langcode;
    Drupal::service('language.default')->set(
      new Language(['id' => $site_default_langcode]),
    );
    // Keep talking to the person in the language they chose, even if the site
    // is being set up in another one.
    Drupal::translation()->setDefaultLangcode($template->userChosenLangcode);

    // Core deletes English at the end of a non-English install, unless the
    // profile asks to keep it. If the site template ships English content, we
    // definitely need to keep English around.
    // @see install_download_additional_translations_operations()
    $install_state['profile_info']['keep_english'] = $site_default_langcode !== 'en' && in_array('en', $template->availableLangcodes, TRUE);

    // When the user's chosen language and the template's default language
    // differ, the site needs both languages. That means Language and Locale
    // must be installed. Core normally installs Locale on its own when the
    // site default language is not English, but the template may have
    // changed the default to English, so we can't rely on core to do it.
    // @see install_profile_info()
    if ($template->userChosenLangcode !== $site_default_langcode) {
      $install_state['profile_info']['install'][] = 'language';
      $install_state['profile_info']['install'][] = 'locale';
    }
  }
  // Once the site template has been applied, add the chosen language if the
  // site was set up in another one. This runs before core imports translations
  // for every language on the site.
  $insert_before('install_import_translations', [
    'drupal_cms_installer_add_chosen_language' => [],
  ]);

  // We might need to download translations if not installing in English.
  $tasks['install_download_translation']['run'] = $install_state['parameters']['langcode'] === 'en'
    ? INSTALL_TASK_SKIP
    : INSTALL_TASK_RUN_IF_REACHED;
  // If translations will be downloaded, ensure that we also download the
  // translations for this profile.
  $tasks['install_download_translation']['function'] = 'drupal_cms_installer_download_translations';

  // We need to override the database settings form because form alter hooks are
  // not invoked in the early installer.
  $tasks['install_settings_form']['function'] = SiteSettingsForm::class;

  // When we install the profile itself, we'll also need User to configure the
  // site and administrator account.
  $install_profile_task = [
    'function' => 'drupal_cms_installer_install_profile',
  ] + $tasks['install_install_profile'];

  $configure_form_task = $tasks['install_configure_form'];
  unset($tasks['install_install_profile'], $tasks['install_configure_form']);

  // Setting the site name is the last step of the early installer, which is
  // when it's still safe to change the language.
  $insert_before('install_base_system', [
    SiteNameForm::class => [
      'display_name' => t('Name your site'),
      'type' => 'form',
      'run' => array_key_exists('name', $install_state['parameters'])
        ? INSTALL_TASK_SKIP
        : INSTALL_TASK_RUN_IF_REACHED,
      'function' => SiteNameForm::class,
    ],
  ]);

  // Before applying any recipes:
  // - Install the profile itself.
  // - Choose a name for the site.
  // - Choose a site template.
  // - Set up the administrator account.
  $insert_before('install_profile_modules', [
    'install_install_profile' => $install_profile_task,
    SiteTemplateForm::class => [
      'display_name' => t('Choose site template'),
      'type' => 'form',
      'run' => $install_state['parameters'][SiteTemplateForm::TASK_ID] ?? INSTALL_TASK_RUN_IF_REACHED,
      'function' => SiteTemplateForm::class,
    ],
    'install_configure_form' => $configure_form_task,
  ]);

  // Wrap the install_profile_modules() function, which returns a batch job, and
  // add all the necessary operations to apply the chosen template recipe.
  $tasks['install_profile_modules']['function'] = 'drupal_cms_installer_apply_recipes';

  // Replace locale's config langcode rewrite batch with config_language_lock's.
  $tasks['install_finish_translations']['function'] = 'drupal_cms_installer_finish_translations';

  // `drupal_cms_installer_finished()` takes several seconds: it uninstalls this
  // profile, rebuilds the router, and flushes all caches. Nothing is rendered
  // while it runs, so without this the browser sits on the last batch page,
  // showing a progress bar that reached 100% seconds ago. Display a page of our
  // own first, which stays on screen for the duration.
  $insert_before('install_finished', [
    'drupal_cms_installer_almost_ready' => [
      'function' => 'drupal_cms_installer_almost_ready',
      'display_name' => t('Finishing up'),
      'type' => 'normal',
    ],
  ]);

  // When the installation is finished, perform additional cleanup tasks (i.e.,
  // uninstall this profile).
  $tasks['install_finished']['function'] = 'drupal_cms_installer_finished';
}

/**
 * Install task to show a holding page while the installer finishes up.
 *
 * @param array<mixed> $install_state
 *   The current install state.
 *
 * @return array<mixed>
 *   A render array for the page to display.
 *
 * @see install_run_tasks()
 * @see install_drupal()
 */
function drupal_cms_installer_almost_ready(array &$install_state): array {
  // This is a bit strange, but core does it elsewhere so we're just
  // blindly following precedent here; ah well.
  $url = Url::fromUri('base:install.php', [
    'query' => $install_state['parameters'],
    'script' => '',
  ])->toString(TRUE)->getGeneratedUrl();

  return [
    // The page template renders a heading of its own from #title, above
    // whatever the task returns. This page needs its heading inside its own
    // layout, alongside the spinner, so it renders one here and leaves the
    // template nothing to render.
    '#title' => '',
    'finishing' => [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => ['class' => ['cms-installer__finishing']],
      'text' => [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#attributes' => ['class' => ['cms-installer__finishing-text']],
        'heading' => [
          '#type' => 'html_tag',
          '#tag' => 'h2',
          '#attributes' => ['class' => ['cms-installer__main-heading']],
          '#value' => t('Your site is almost ready'),
        ],
        'help' => [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#attributes' => ['class' => ['cms-installer__subhead']],
          '#value' => t('This will only take a moment.'),
        ],
      ],
      'spinner' => [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#attributes' => [
          'class' => ['cms-installer__finishing-spinner'],
          'role' => 'status',
        ],
        'label' => [
          '#type' => 'html_tag',
          '#tag' => 'span',
          '#attributes' => ['class' => ['visually-hidden']],
          '#value' => t('Finishing installation.'),
        ],
      ],
    ],
    // When the installation's heavy batch jobs are done, install_finished runs
    // and does some clean-up tasks that can take several seconds. Instead of
    // leaving the final batch job showing as hanging, we instead display this
    // page, with a spinner and final message, then immediately refresh.
    '#attached' => [
      'html_head' => [
        [
          [
            '#tag' => 'meta',
            '#attributes' => [
              'http-equiv' => 'Refresh',
              'content' => '0; URL=' . $url,
            ],
          ],
          'drupal_cms_installer_almost_ready_refresh',
        ],
      ],
    ],
  ];
}

/**
 * Installs the profile.
 *
 * This is only overridden to ensure that User and Config Language Lock are
 * installed first. We need Config Language Lock set up as early as possible
 * so all configuration gets saved in the proper language. And need User and
 * its configuration to set up the administrator account properly.
 *
 * @param array<mixed> $install_state
 *   The current installation state.
 */
function drupal_cms_installer_install_profile(array &$install_state): void {
  Drupal::service(ModuleInstallerInterface::class)->install([
    'user',
    'config_language_lock',
    // This module allows an initially monolingual site to become multilingual
    // in the future. Our site template base recipe installs it, but we also
    // install it here to support site templates which *don't* use our base.
    'recipe_locale',
  ]);

  // Set the locked langcode early so that hook_entity_presave() enforces the
  // chosen install language on all config entities saved during recipe apply.
  // Without this, config entities are saved with the langcode from their
  // shipped config files (usually 'en'), because the locked_langcode
  // setting is not yet configured at that point. Some entities like webform
  // have side effects that depend on the langcode, eg. new path aliases
  // being created, so the language code needs to be right out of the gate.
  // Also make the lock follow the site default language, so that changing the
  // default language later keeps the configuration language in sync with it.
  // @todo Remove after https://www.drupal.org/project/drupal/issues/3337864
  // and https://git.drupalcode.org/project/canvas/-/work_items/3591976
  Drupal::configFactory()
    ->getEditable('config_language_lock.settings')
    ->set('locked_langcode', $install_state['parameters']['langcode'] ?? 'en')
    ->set('follow_site_default', TRUE)
    ->save();

  install_install_profile($install_state);
}

/**
 * Finishes translations at the end of installation.
 *
 * This replaces `install_finish_translations()` to swap Locale's config
 * langcode rewrite batch with the one from config_language_lock.
 *
 * @return array<mixed>
 *   An array of batch definitions.
 *
 * @todo Remove after https://www.drupal.org/project/drupal/issues/3337864
 */
function drupal_cms_installer_finish_translations(): array {
  // Add batch items to download translations for extensions.
  $projects = Drupal::service(LocaleProjectRepository::class)->buildProjects();
  $languages = Drupal::languageManager()->getLanguages();
  $batches = [];
  if (count($projects) > 1) {
    $batch = Drupal::service(LocaleFetch::class)
      ->buildUpdateBatch(
        [],
        array_keys($languages),
        LocaleDefaultOptions::updateOptions(),
      );
    if ($batch) {
      $batches[] = $batch;
    }
  }

  // Use config_language_lock's batch instead of Locale's config rewrite.
  $batch = Drupal::service(ConfigLanguageLockBatch::class)
    ->buildBatch(FALSE);
  if ($batch) {
    $batches[] = $batch;
  }

  // Rewrite config with translations.
  $batch = Drupal::service(LocaleConfigBatch::class)
    ->buildBatch([], array_keys($languages), [], FALSE);
  if ($batch) {
    $batches[] = $batch;
  }
  return $batches;
}

/**
 * Uninstalls the profile.
 *
 * @param array<mixed> $install_state
 *   The current install state.
 */
function drupal_cms_installer_finished(array &$install_state): void {
  install_finished($install_state);
  // The site template is only needed while installing.
  Drupal::state()->delete(SiteTemplateForm::STATE_KEY);

  // Uninstall the profile if it is still installed. Uninstalling flushes all
  // cache bins. Ran install_finished() earlier, so it still runs on warm caches
  // and completes faster.
  // @todo Remove this fallback when the minimum core version is 11.5.
  if (Drupal::moduleHandler()->moduleExists('drupal_cms_installer')) {
    Drupal::service(ModuleInstallerInterface::class)->uninstall([
      'drupal_cms_installer',
    ]);
  }

  // Clear all previous status messages to avoid clutter, including the
  // pointless "Congratulations, you installed Drupal!" message set by
  // `install_finished()`.
  $messenger = Drupal::messenger();
  $messenger->deleteByType($messenger::TYPE_STATUS);
}

/**
 * Downloads translations for the install profile.
 *
 * This wraps `install_download_translation()` and downloads core translations
 * last. If the core translations fail to download, the install process will
 * stop with an exception.
 *
 * @param array<mixed> $install_state
 *   The current install state.
 *
 * @return mixed
 *   Return value from `install_download_translation()`.
 */
function drupal_cms_installer_download_translations(array &$install_state): mixed {
  // Scan for already-downloaded translations.
  $install_state['translations'] += install_find_translations();

  // If we already have the necessary translations, there's nothing to do.
  $language = $install_state['parameters']['langcode'];
  if (isset($install_state['translations'][$language])) {
    return NULL;
  }

  // Temporarily disable the interactive installer so that
  // `install_download_translation()` won't reload the page.
  $was_interactive = $install_state['interactive'];
  $install_state['interactive'] = FALSE;

  $original_server_pattern = $install_state['server_pattern'];
  try {
    // Construct a download URL for the profile. We can't rely on
    // `install_download_translation()` to do this for us, because it is
    // hard-coded to download the translation for core.
    $install_state['server_pattern'] = strtr($original_server_pattern, [
      '%project' => 'drupal_cms_installer',
      '%version' => FileTranslation::version(),
    ]);
    install_download_translation($install_state);
  }
  catch (InstallerException) {
    // If there's an error, `install_display_requirements()`, which is called
    // by `install_download_translation()`, will throw. That's a pity but it's
    // probably better to just keep going, even with missing translations.
  }
  finally {
    // Download core translations as normal.
    $install_state['server_pattern'] = $original_server_pattern;
    $install_state['interactive'] = $was_interactive;
    return install_download_translation($install_state);
  }
}

/**
 * Install task to apply all queued recipes.
 *
 * Recipes that are not yet in the code base will be required using Composer,
 * then applied in a subsequent batch job.
 *
 * @param array<mixed> $install_state
 *   The current install state.
 *
 * @return array<mixed>
 *   A batch job to execute.
 */
function drupal_cms_installer_apply_recipes(array &$install_state): array {
  // Let `install_profile_modules()` generate the initial batch job, to which
  // we will add operations.
  $batch = install_profile_modules($install_state);
  $batch['title'] = t('Setting up your site');

  // Always apply the administrator role recipe.
  $recipe = Recipe::createFromDirectory('core/recipes/administrator_role');
  $operations = [
    ...RecipeRunner::toBatchOperations($recipe),
    ['_drupal_cms_installer_mark_recipe_applied', [$recipe->path]],
  ];

  $site_template = Drupal::state()->get(SiteTemplateForm::STATE_KEY);
  // If no site template has been chosen, there's nothing else to do here.
  if (empty($site_template)) {
    return $batch;
  }
  assert($site_template instanceof SiteTemplate);
  $locator = $site_template->locator;

  // If the locator is a directory, the recipe is already present in the
  // code base and we just need to apply it as per usual.
  if (is_dir($locator)) {
    $site_template = Recipe::createFromDirectory($locator);
    $operations = array_merge($operations, RecipeRunner::toBatchOperations($site_template));
    $operations[] = ['_drupal_cms_installer_mark_recipe_applied', [$locator]];
  }
  // Otherwise, prepend an operation to require the recipe via Composer,
  // then generate an additional batch job to apply it. We prepend the
  // operation so that all the necessary dependencies will be physically
  // present before we apply or install anything.
  else {
    array_unshift($batch['operations'], ['_drupal_cms_installer_require_recipe', [$site_template]]);
    $batch['init_message'] = t('Installing %name. This may take a few minutes.', [
      '%name' => $locator,
    ]);
  }

  // Only do each recipe's batch operations once.
  foreach ($operations as $operation) {
    if (!in_array($operation, $batch['operations'], TRUE)) {
      $batch['operations'][] = $operation;
    }
  }
  return $batch;
}

/**
 * Uses Composer to install a recipe, then queues a batch job to apply it.
 *
 * @param \Drupal\drupal_cms_installer\SiteTemplate $site_template
 *   An object with information about the site template recipe.
 * @param array $context
 *   The current batch context.
 */
function _drupal_cms_installer_require_recipe(SiteTemplate $site_template, array &$context): void {
  $package_name = $site_template->locator;

  // Allow the recipe to scaffold files into the project; for example, a site
  // template may wish to provide a default AGENTS.md file at the project root.
  ComposerExecutor::execute(
    'config',
    'extra.drupal-scaffold.allowed-packages',
    '--merge',
    '--json',
    json_encode([$package_name], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
  );
  ComposerExecutor::execute(
    'require',
    $package_name,
    '--minimal-changes',
    '--update-with-all-dependencies',
  );

  // Since the list of available extensions has changed, we need to reset all
  // extension discovery caches. Reflection is the only real way to do this.
  (new ReflectionProperty(ExtensionDiscovery::class, 'files'))
    ->setValue(NULL, []);
  Drupal::service(ModuleExtensionList::class)->reset();
  Drupal::service(ThemeExtensionList::class)->reset();

  // We have the recipe, so generate a batch job to apply it.
  $batch = new BatchBuilder();
  $recipe = Recipe::createFromDirectory($site_template->getPath());
  foreach (RecipeRunner::toBatchOperations($recipe) as [$callable, $arguments]) {
    $batch->addOperation($callable, $arguments);
  }
  $batch->addOperation('_drupal_cms_installer_mark_recipe_applied', [$package_name]);
  batch_set($batch->toArray());

  $context['message'] = t('Installed @name', ['@name' => $recipe->name]);
}

/**
 * Adds the user's chosen language to the site, if needed.
 *
 * When the site template ships its content in other languages, the site is set
 * up in the template's language instead of the chosen one. If the two differ,
 * the chosen language is created here. Language and Locale were installed along
 * with the profile, so this only has to create the language itself. Core's
 * translation tasks run next and import translations for every language on the
 * site.
 *
 * @see drupal_cms_installer_install_tasks_alter()
 * @see install_import_translations()
 */
function drupal_cms_installer_add_chosen_language(): void {
  $template = Drupal::state()->get(SiteTemplateForm::STATE_KEY);
  assert($template instanceof SiteTemplate);

  $chosen_langcode = $template->userChosenLangcode;
  // When these match, the site was set up in the chosen language directly.
  // User 1 was created in that language, and there's nothing else to do.
  // When they differ, the site was set up in the template's default language
  // (e.g., English) instead of the user's choice (e.g., Hungarian). Two
  // things need to happen: add the chosen language to the site, and update
  // user 1's preferred language so they keep seeing it.
  if ($chosen_langcode === $template->getDefaultLangcode()) {
    return;
  }
  $language = ConfigurableLanguage::load($chosen_langcode) ?: ConfigurableLanguage::createFromLangcode($chosen_langcode);
  if ($language->isNew()) {
    // Use the native name, as core does for the language chosen at the start
    // of the installer.
    // @see install_download_additional_translations_operations()
    $standard_languages = LanguageManager::getStandardLanguageList();
    if (isset($standard_languages[$chosen_langcode])) {
      $language->setName($standard_languages[$chosen_langcode][1]);
    }
    $language->save();
  }
  // The person who chose the language should keep seeing it.
  User::load(1)?->set('preferred_langcode', $chosen_langcode)
    ->set('preferred_admin_langcode', $chosen_langcode)
    ->save();
}

/**
 * Marks a particular recipe as having been applied.
 *
 *  This is done to increase fault tolerance. On hosting plans that don't have
 *  a ton of RAM or computing power to spare, the possibility of the installer
 *  timing out or failing in mid-stream is increased, especially with a big,
 *  complex distribution like Drupal CMS. Tracking the recipes which have been
 *  applied allows the installer to recover and "pick up where it left off",
 *  without applying recipes that have already been applied successfully. Once
 *  the install is done, the list of recipes is deleted.
 *
 * @param string $locator
 *   The path, or package name, of a recipe.
 */
function _drupal_cms_installer_mark_recipe_applied(string $locator): void {
  // This state key may be used by hosts that want to optimize the installation
  // process without hacking or forking the installer. Therefore, this key and
  // its behavior can be relied upon.
  // @api
  $key = 'drupal_cms_installer.applied_recipes';
  Drupal::state()->set($key, [
    $locator,
    ...Drupal::state()->get($key, []),
  ]);
}
