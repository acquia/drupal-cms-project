<?php

declare(strict_types=1);

namespace Drupal\Tests\eca_language\Kernel;

use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\eca\Entity\Eca;
use Drupal\eca\Token\TokenInterface;
use Drupal\eca_base\BaseEvents;
use Drupal\eca_base\Event\CustomEvent;
use Drupal\eca_language\Plugin\LanguageNegotiation\EcaLanguageNegotiation;
use Drupal\language\ConfigurableLanguageManagerInterface;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Entity\ContentLanguageSettings;
use Drupal\locale\StringStorageInterface;
use Drupal\node\Entity\Node;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use function current;
use function parse_url;
use PHPUnit\Framework\Attributes\Group;

/**
 * Kernel tests for plugins of the eca_language module.
 */
#[Group('eca')]
#[Group('eca_language')]
#[RunTestsInSeparateProcesses]
class LanguageTest extends KernelTestBase {

  use ContentTypeCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'filter',
    'text',
    'node',
    'eca',
    'language',
    'locale',
    'eca_language',
    'eca_base',
    'modeler_api',
    'content_translation',
  ];

  /**
   * The locale string storage.
   *
   * @var \Drupal\locale\StringStorageInterface
   */
  protected StringStorageInterface $localeStorage;

  /**
   * The configurable language manager.
   *
   * @var \Drupal\language\ConfigurableLanguageManagerInterface
   */
  protected ConfigurableLanguageManagerInterface $languageManager;

  /**
   * The token service.
   *
   * @var \Drupal\eca\Token\TokenInterface
   */
  protected TokenInterface $tokenService;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installSchema('node', ['node_access']);
    $this->installConfig(static::$modules);
    ConfigurableLanguage::createFromLangcode('de')->save();
    $this->localeStorage = $this->container->get('locale.storage');
    $this->languageManager = $this->container->get('language_manager');
    $this->tokenService = $this->container->get('eca.token_services');
    // The superuser (uid 1) bypasses entity access, so the action's
    // access() entity-level checks resolve to allowed in the happy path.
    User::create(['uid' => 1, 'name' => 'admin'])->save();
  }

  /**
   * Tests plugins of the eca_language module.
   */
  public function testLanguage(): void {
    // Set up language negotiation.
    $config = $this->config('language.types');
    $config->set('configurable', [
      LanguageInterface::TYPE_INTERFACE,
    ]);
    $config->set('negotiation', [
      LanguageInterface::TYPE_INTERFACE => [
        'enabled' => [EcaLanguageNegotiation::METHOD_ID => -20],
      ],
    ]);
    $config->save();
    // This config does the following:
    // 1. It reacts upon language negotiation.
    // 2. Upon that, it sets german as negotiated language.
    $eca_config_values = [
      'langcode' => 'en',
      'status' => TRUE,
      'id' => 'eca_language_negotiation',
      'label' => 'ECA language negotiation',
      'modeller' => 'fallback',
      'version' => '1.0.0',
      'events' => [
        'language_negotiation' => [
          'plugin' => 'eca_language:negotiate',
          'label' => 'ECA language negotiation',
          'configuration' => [],
          'successors' => [
            ['id' => 'set_german_language', 'condition' => ''],
          ],
        ],
      ],
      'conditions' => [],
      'gateways' => [],
      'actions' => [
        'set_german_language' => [
          'plugin' => 'eca_set_current_langcode',
          'label' => 'Set german language',
          'configuration' => [
            'langcode' => 'de',
          ],
          'successors' => [],
        ],
      ],
    ];
    $ecaConfig = Eca::create($eca_config_values);
    $ecaConfig->save();

    /** @var \Drupal\Core\Action\ActionManager $action_manager */
    $action_manager = \Drupal::service('plugin.manager.action');
    $action_manager->createInstance('eca_reset_language_negotiation')->execute();
    $this->assertEquals('de', $this->languageManager->getCurrentLanguage()->getId());

    $action_manager->createInstance('eca_get_current_langcode', ['token_name' => 'langcode'])->execute();
    $this->assertEquals('de', (string) $this->tokenService->replaceClear('[langcode]'));

    $action_manager->createInstance('eca_set_current_langcode', ['langcode' => 'en'])->execute();
    $this->assertEquals('en', $this->languageManager->getCurrentLanguage()->getId());

    $action_manager->createInstance('eca_get_current_langcode', ['token_name' => 'langcode'])->execute();
    $this->assertEquals('en', (string) $this->tokenService->replaceClear('[langcode]'));
  }

  /**
   * Test the language negotiation URL.
   *
   * Test that the decorated language manager use also the
   * language set by eca_set_current_langcode for url generator.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testLanguageNegotiationUrl(): void {
    // We need to reset the container or we get a false-positive.
    \Drupal::service('kernel')->rebuildContainer();
    $this->languageManager = $this->container->get('language_manager');

    $config = $this->config('language.negotiation');
    $config->set('url.prefixes.en', 'en');
    $config->save();

    $eca_config_values = [
      'langcode' => 'en',
      'status' => TRUE,
      'id' => 'language_negotiation_url',
      'label' => 'Language negotiation url',
      'modeller' => 'fallback',
      'version' => '1.0.0',
      'events' => [
        'language_negotiation_url' => [
          'plugin' => 'eca_base:eca_custom',
          'label' => 'Language negotiation url',
          'configuration' => [
            'event_id' => 'language_negotiation_url',
          ],
          'successors' => [
            ['id' => 'set_german_language', 'condition' => ''],
          ],
        ],
      ],
      'conditions' => [],
      'gateways' => [],
      'actions' => [
        'set_german_language' => [
          'plugin' => 'eca_set_current_langcode',
          'label' => 'Set german language',
          'configuration' => [
            'langcode' => 'de',
          ],
          'successors' => [
            ['id' => 'print_message', 'condition' => ''],
          ],
        ],
        'print_message' => [
          'plugin' => 'action_message_action',
          'label' => 'Print message',
          'configuration' => [
            'message' => '[site:url]',
          ],
          'successors' => [],
        ],
      ],
    ];

    $ecaConfig = Eca::create($eca_config_values);
    $ecaConfig->save();

    // Language is default.
    $this->assertEquals('en', $this->languageManager->getCurrentLanguage(LanguageInterface::TYPE_URL)->getId());
    $this->assertEquals('/en', parse_url($this->tokenService->replaceClear('[site:url]'), PHP_URL_PATH));

    $this->container->get('event_dispatcher')->dispatch(new CustomEvent('language_negotiation_url'), BaseEvents::CUSTOM);

    // Language is unchanged by the event.
    $this->assertEquals('en', $this->languageManager->getCurrentLanguage(LanguageInterface::TYPE_URL)->getId());
    \Drupal::service('kernel')->rebuildContainer();
    // Also after rebuild.
    $this->assertEquals('en', $this->languageManager->getCurrentLanguage(LanguageInterface::TYPE_URL)->getId());
    $this->assertEquals('/en', parse_url($this->tokenService->replaceClear('[site:url]'), PHP_URL_PATH));

    // The model set the language to de therefore we expect that
    // also url token use this language.
    $messages = $this->container->get('messenger')->messagesByType(MessengerInterface::TYPE_STATUS);
    $this->assertEquals('/de', parse_url((string) current($messages), PHP_URL_PATH));
  }

  /**
   * Tests the happy path of the "eca_create_entity_translation" action.
   */
  public function testCreateEntityTranslation(): void {
    $this->createTranslatableArticleType();

    /** @var \Drupal\Core\Action\ActionManager $action_manager */
    $action_manager = \Drupal::service('plugin.manager.action');
    /** @var \Drupal\content_translation\ContentTranslationManagerInterface $translation_manager */
    $translation_manager = \Drupal::service('content_translation.manager');
    /** @var \Drupal\Core\Session\AccountSwitcherInterface $account_switcher */
    $account_switcher = \Drupal::service('account_switcher');

    $node = Node::create([
      'type' => 'article',
      'title' => 'Hello!',
      'langcode' => 'en',
      'uid' => 1,
      'status' => 1,
    ]);
    $node->save();

    // Switch to the superuser so the entity-level 'create' access check in
    // access() resolves to allowed.
    $account_switcher->switchTo(User::load(1));

    $action = $action_manager->createInstance('eca_create_entity_translation', [
      'source_langcode' => 'en',
      'target_langcode' => 'de',
    ]);

    // The action only runs when access() grants it, so verify that first.
    $this->assertTrue($action->access($node), 'Access is granted for a valid translation request.');

    $action->execute($node);

    // The German translation now exists and is pre-filled from the source.
    $this->assertTrue($node->hasTranslation('de'), 'The target translation was created.');
    $translation = $node->getTranslation('de');
    $this->assertEquals('Hello!', $translation->label(), 'The translation is pre-filled from the source.');

    // The new translation is flagged as outdated ("needs to be updated").
    $metadata = $translation_manager->getTranslationMetadata($translation);
    $this->assertTrue($metadata->isOutdated(), 'The new translation is marked as outdated.');
    $this->assertEquals('en', $metadata->getSource(), 'The source language is recorded on the translation.');

    $account_switcher->switchBack();
  }

  /**
   * Tests the access() guards of the "eca_create_entity_translation" action.
   */
  public function testCreateEntityTranslationAccess(): void {
    /** @var \Drupal\Core\Action\ActionManager $action_manager */
    $action_manager = \Drupal::service('plugin.manager.action');

    // A non-translatable object must be forbidden.
    $action = $action_manager->createInstance('eca_create_entity_translation', [
      'source_langcode' => 'en',
      'target_langcode' => 'de',
    ]);
    $config_entity = ConfigurableLanguage::load('de');
    $this->assertFalse($action->access($config_entity), 'Access is forbidden for a non-translatable entity.');

    // Content translation not enabled for the type/bundle must be forbidden.
    $this->createContentType([
      'type' => 'page',
      'name' => 'Page',
    ]);
    $page = Node::create([
      'type' => 'page',
      'title' => 'No translation here',
      'langcode' => 'en',
      'uid' => 1,
      'status' => 1,
    ]);
    $page->save();
    $action = $action_manager->createInstance('eca_create_entity_translation', [
      'source_langcode' => 'en',
      'target_langcode' => 'de',
    ]);
    $this->assertFalse($action->access($page), 'Access is forbidden when content translation is not enabled.');

    // Identical source and target languages must be forbidden.
    $this->createTranslatableArticleType();
    $node = Node::create([
      'type' => 'article',
      'title' => 'Hello!',
      'langcode' => 'en',
      'uid' => 1,
      'status' => 1,
    ]);
    $node->save();
    $action = $action_manager->createInstance('eca_create_entity_translation', [
      'source_langcode' => 'en',
      'target_langcode' => 'en',
    ]);
    $this->assertFalse($action->access($node), 'Access is forbidden when source and target languages are identical.');

    // An already existing target translation must be forbidden.
    $node->addTranslation('de', [
      'title' => 'Hallo!',
      'langcode' => 'de',
    ])->save();
    $action = $action_manager->createInstance('eca_create_entity_translation', [
      'source_langcode' => 'en',
      'target_langcode' => 'de',
    ]);
    $this->assertFalse($action->access($node), 'Access is forbidden when the target translation already exists.');
  }

  /**
   * Creates a translation-enabled "article" content type.
   */
  protected function createTranslatableArticleType(): void {
    $this->createContentType([
      'type' => 'article',
      'name' => 'Article',
    ]);
    ContentLanguageSettings::create([
      'id' => 'node.article',
      'target_entity_type_id' => 'node',
      'target_bundle' => 'article',
      'default_langcode' => LanguageInterface::LANGCODE_DEFAULT,
      'language_alterable' => TRUE,
    ])->save();
    \Drupal::service('content_translation.manager')
      ->setEnabled('node', 'article', TRUE);
  }

}
