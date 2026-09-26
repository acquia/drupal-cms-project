<?php

namespace Drupal\drupal_cms_installer\Form;

use Composer\InstalledVersions;
use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\State\StateInterface;
use Drupal\drupal_cms_installer\ComposerExecutor;
use Drupal\drupal_cms_installer\SiteTemplate;
use GuzzleHttp\ClientInterface;
use Psr\Http\Client\ClientExceptionInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Yaml\Exception\ParseException;

/**
 * Defines a form to choose a site template.
 *
 * @internal
 *   Everything in the Drupal CMS installer is internal and may be changed or
 *   removed at any time without warning. External code should not interact
 *   with this class.
 */
final class SiteTemplateForm extends FormBase {

  /**
   * The state key which holds the chosen SiteTemplate object.
   */
  public const string STATE_KEY = 'drupal_cms_installer.site_template';

  /**
   * An identifier for this task, to mark it as completed.
   */
  public const string TASK_ID = 'template';

  public function __construct(
    protected readonly ClientInterface $http,
    #[Autowire(service: 'cache.default')] protected readonly CacheBackendInterface $cache,
    #[Autowire(param: 'site.path')] protected readonly string $sitePath,
    protected readonly StateInterface $state,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'installer_site_template_form';
  }

  /**
   * {@inheritdoc}
   *
   * @phpstan-param array<string, mixed> $form
   * @phpstan-param array<string, mixed> $install_state
   *
   * @phpstan-return array<mixed>
   */
  public function buildForm(array $form, FormStateInterface $form_state, array $install_state = []): array {
    $all_choices = iterator_to_array(SiteTemplate::findAll());

    // Load additional choices. If any of them are already in the code base, the
    // ones that are physically present will "win".
    foreach ($this->getCuratedList() as $name => $values) {
      $all_choices[$name] ??= new SiteTemplate(...$values);
    }
    // Store the choices because we'll need them for validation and submission.
    $form_state->set('site_templates', $all_choices);

    $blank = 'drupal_cms_blank';
    // Must be called `add_ons` to agree with the theme.
    $form['add_ons'] = [
      '#options' => [],
      '#type' => 'radios',
      '#required' => TRUE,
      '#default_value' => array_key_exists($blank, $all_choices) ? $blank : array_key_first($all_choices),
    ];
    // If installing non-interactively (i.e., at the command line) choose
    // Starter by default because Blank looks like an error if you don't know
    // what to expect.
    $starter = 'drupal_cms_starter';
    if (empty($install_state['interactive']) && array_key_exists($starter, $all_choices)) {
      $form['add_ons']['#default_value'] = $starter;
    }

    // Premium site templates may require an access (license) key.
    $form['access_key'] = [
      '#type' => 'container',
      '#theme_wrappers' => ['container__access_key'],
      '#tree' => TRUE,
    ];
    foreach ($all_choices as $key => $choice) {
      $form['add_ons'][$key] = [
        '#theme_wrappers' => [
          'form_element__site_template' => ['item' => $choice],
        ],
        '#description' => $choice->description,
      ];
      // @phpstan-ignore offsetAccess.nonOffsetAccessible
      $form['add_ons']['#options'][$key] = $choice->name;

      $form['access_key'][$key] = [
        '#type' => 'textfield',
        // Only visible when the associated site template is chosen.
        '#states' => [
          'visible' => ['input[name="add_ons"]' => ['value' => $key]],
        ],
        '#attributes' => [
          'data-for' => $key,
          'data-validation-url' => $choice->keyValidationUrl?->toString(),
          // Pattern is required for JS .checkValidity() function.
          'pattern' => '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}',
          // Placeholder is required for CSS's show/hide functionality to work.
          'placeholder' => 'XXXXXXXX-XXXX-XXXX-XXXX-XXXXXXXXXXXX',
        ],
        '#access' => $choice->price > 0,
      ];
    }
    // @phpstan-ignore offsetAccess.nonOffsetAccessible
    $form['add_ons'][$blank]['#weight'] = -100;

    $form['actions'] = [
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Next'),
        '#button_type' => 'primary',
        '#op' => 'submit',
      ],
      '#type' => 'actions',
    ];
    $form['#title'] = $this->t('Choose a site template');

    return $form;
  }

  /**
   * Returns a value object with information about the chosen site template.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   *
   * @return \Drupal\drupal_cms_installer\SiteTemplate
   *   A value object with information about the chosen site template.
   */
  private function getChoice(FormStateInterface $form_state): SiteTemplate {
    $choice = $form_state->getValue('add_ons');
    $all_choices = $form_state->get('site_templates');
    assert(is_array($all_choices) && array_key_exists($choice, $all_choices));
    return $all_choices[$choice];
  }

  /**
   * {@inheritdoc}
   *
   * @phpstan-param array<mixed> $form
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);

    $site_template = $this->getChoice($form_state);
    // If the package is provided by an alternate repository (i.e., not
    // Packagist or packages.drupal.org), make Composer aware of it.
    if (empty($site_template->repository)) {
      return;
    }
    $url = parse_url($site_template->repository);
    assert(is_array($url) && array_key_exists('host', $url));
    $url = rtrim($url['host'] . ':' . ($url['port'] ?? ''), ':');
    // Only Composer repositories are supported.
    ComposerExecutor::execute('repository', 'add', hash('xxh3', $url), 'composer', $site_template->repository);

    // Alternate repositories might require an access key. If one was entered,
    // configure Composer to use it for this repository.
    $choice = $form_state->getValue('add_ons');
    $access_key = trim($form_state->getValue(['access_key', $choice]));
    if (empty($access_key)) {
      return;
    }

    // Confirm that the package is available on the repository. If it's not, the
    // access key might be invalid.
    $auth_key = 'bearer.' . $url;
    // @todo $access_key is user input, should we sanitize or validate it?
    //   Or just rely on Symfony's Process constructor handle that for us?
    ComposerExecutor::execute('config', $auth_key, $access_key);
    try {
      ComposerExecutor::execute('show', '--all', $site_template->locator);
    }
    catch (ProcessFailedException) {
      $form_state->setErrorByName("access_key[$choice]", $this->t('The access key you entered did not grant access to the package. Contact the seller for support.'));
      ComposerExecutor::execute('config', '--unset', $auth_key);
    }
  }

  /**
   * {@inheritdoc}
   *
   * @phpstan-param array<string, mixed> $form
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    global $install_state;
    $site_template = $this->getChoice($form_state);

    // Record which language the user chose so that the template can decide
    // which language the site should be set up in. The installer reads this
    // from the SiteTemplate stored in state on every request from here on.
    // @see drupal_cms_installer_install_tasks_alter()
    $site_template->userChosenLangcode = $install_state['parameters']['langcode'] ?? 'en';
    $this->state->set(self::STATE_KEY, $site_template);

    // The template may want a different default language than the one the user
    // chose. Core derives the site default language from this parameter, and it
    // already wrote the chosen language into the site configuration when it
    // installed the System module. Both have to agree with the template's
    // intended default language. When they already match, this is a no-op.
    // @see drupal_install_system()
    $default_langcode = $site_template->getDefaultLangcode();
    $install_state['parameters']['langcode'] = $default_langcode;
    $this->configFactory()
      ->getEditable('system.site')
      ->set('langcode', $default_langcode)
      ->set('default_langcode', $default_langcode)
      ->save();
    // The configuration language was locked to the chosen language before
    // the template was known. It has to match the site default language.
    // @see drupal_cms_installer_install_profile()
    $this->configFactory()
      ->getEditable('config_language_lock.settings')
      ->set('locked_langcode', $default_langcode)
      ->save();

    // Mark the task as finished.
    $install_state['parameters'][self::TASK_ID] = INSTALL_TASK_SKIP;
  }

  /**
   * Returns a curated list of site template information.
   *
   * @return iterable<string, array<mixed>>
   *   An iterable of information about site templates, keyed by machine name.
   */
  private function getCuratedList(): iterable {
    $messenger = $this->messenger();

    // First and foremost, ensure the file system is writable. If it's not, then
    // there's no point in showing the list of site templates because you
    // probably won't be able to install any of them anyway.
    ['install_path' => $project_root] = InstalledVersions::getRootPackage();
    if (!is_writable($project_root)) {
      $messenger->addWarning(
        $this->t('Only showing site templates that are already downloaded, because %dir is not writable.', [
          '%dir' => realpath($project_root),
        ]),
      );
      return [];
    }

    // Allow the list of site templates to be defined per-site. This is helpful
    // for testing, or for hosts which want to limit the available choices. This
    // is an official extension point and can be relied upon.
    // @api
    $list = @include $this->sitePath . '/site-templates.php';
    if (is_iterable($list)) {
      return $list;
    }

    // If the original file exists, read it directly. It should not be included
    // in releases of the installer.
    // @see .gitattributes
    $file = dirname(__DIR__, 2) . '/site-templates.yml';
    if (file_exists($file)) {
      return Yaml::decode((string) file_get_contents($file));
    }

    // @see site-templates.yml
    $url = 'https://git.drupalcode.org/api/v4/projects/204857/repository/files/site-templates.yml/raw?ref=HEAD';
    $cid = hash('xxh32', $url);

    $cached = $this->cache->get($cid);
    if ($cached) {
      return $cached->data;
    }

    $list = [];
    try {
      $list = Yaml::decode(
        (string) $this->http->request('GET', $url)->getBody(),
      );
    }
    catch (ParseException | ClientExceptionInterface $e) {
      $messenger->addWarning($e->getMessage());
    }
    $this->cache->set($cid, $list);
    return $list;
  }

}
