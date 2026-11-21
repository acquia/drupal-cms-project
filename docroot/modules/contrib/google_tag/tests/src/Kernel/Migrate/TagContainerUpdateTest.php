<?php

namespace Drupal\Tests\google_tag\Kernel\Migrate;

use Drupal\Tests\google_tag\Kernel\GoogleTagTestCase;
use Drupal\google_tag\Entity\TagContainer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests hook update for migrating 1.x container entities to 2.x.
 *
 * @group google_tag
 */
#[Group('google_tag')]
#[RunTestsInSeparateProcesses]
class TagContainerUpdateTest extends GoogleTagTestCase {

  /**
   * Container entity id 1.
   */
  protected const CONTAINER_1 = 'test_gtm_1';

  /**
   * Container entity id 2.
   */
  protected const CONTAINER_2 = 'test_gtm_2';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'taxonomy',
    'language',
  ];

  /**
   * Tests that entities are migrated and google tag settings are migrated.
   *
   * @covers ::google_tag_update_8201
   *
   * @throws \Exception
   */
  public function testEntitiesExist(): void {
    $this->loadFixture('container_migrate.php');
    $this->container->get('module_handler')->loadInclude('google_tag', 'install');
    $sandbox = [];
    google_tag_update_8201($sandbox);
    $gtag_settings = $this->container->get('config.factory')->get('google_tag.settings');
    self::assertEquals(self::CONTAINER_1, $gtag_settings->get('default_google_tag_entity'));
    self::assertTrue($gtag_settings->get('use_collection'));
    $google_tag_storage = $this->container->get('entity_type.manager')->getStorage('google_tag_container');
    $tag_container1 = $google_tag_storage->load(self::CONTAINER_1);
    $tag_container2 = $google_tag_storage->load(self::CONTAINER_2);
    foreach ([$tag_container1, $tag_container2] as $container) {
      self::assertNotEmpty($container->get('tag_container_ids'));
      self::assertNull($container->get('container_id'));
      self::assertArrayHasKey('gtm', $container->get('advanced_settings'));
      self::assertNotEmpty($container->get('events'));
      $conditions = $container->get('conditions');
      self::assertNotEmpty($conditions);
      self::assertArrayHasKey('response_code', $conditions, 'Custom response code logic is migrated into conditions.');
      self::assertArrayNotHasKey('gtag_language', $conditions, 'Old conditions from 1.x are removed.');
      self::assertArrayHasKey('user_role', $conditions, 'Custom user role logic is converted into conditions.');
      self::assertArrayHasKey('request_path', $conditions, 'Custom request path role is converted into conditions');
    }
  }

  /**
   * Tests the upgrade of a container with unset path, role and status lists.
   *
   * @covers ::google_tag_update_8201
   *
   * @throws \Exception
   */
  public function testUnsetLegacyConditions(): void {
    $this->loadFixture('container_migrate.php');
    $this->removeLegacyKeys(self::CONTAINER_1, ['path_list', 'role_list', 'status_list']);
    $this->container->get('module_handler')->loadInclude('google_tag', 'install');
    $sandbox = [];
    google_tag_update_8201($sandbox);

    $tag_container = $this->container->get('entity_type.manager')
      ->getStorage('google_tag_container')
      ->load(self::CONTAINER_1);
    self::assertSame(['GTM-T26VRML'], $tag_container->get('tag_container_ids'));
    self::assertArrayHasKey('GTM-T26VRML', $tag_container->get('advanced_settings')['gtm']);
    $conditions = $tag_container->get('conditions');
    self::assertArrayNotHasKey('request_path', $conditions);
    self::assertArrayNotHasKey('response_code', $conditions);
    self::assertArrayNotHasKey('user_role', $conditions);
    self::assertArrayHasKey('language', $conditions);
  }

  /**
   * Tests the upgrade of a container whose 1.x settings were all removed.
   *
   * A configuration import run before database updates saves each 1.x
   * container with only its 2.x properties.
   *
   * @covers ::google_tag_update_8201
   *
   * @throws \Exception
   */
  public function testRemovedLegacySettings(): void {
    $this->loadFixture('container_migrate.php');
    // cspell:ignore whitelist, blacklist
    $this->removeLegacyKeys(self::CONTAINER_1, [
      'container_id',
      'data_layer',
      'include_classes',
      'whitelist_classes',
      'blacklist_classes',
      'include_environment',
      'environment_id',
      'environment_token',
      'path_toggle',
      'path_list',
      'role_toggle',
      'role_list',
      'status_toggle',
      'status_list',
    ]);
    $this->container->get('module_handler')->loadInclude('google_tag', 'install');
    $sandbox = [];
    google_tag_update_8201($sandbox);

    $google_tag_storage = $this->container->get('entity_type.manager')->getStorage('google_tag_container');
    $tag_container = $google_tag_storage->load(self::CONTAINER_1);
    self::assertSame([], $tag_container->get('tag_container_ids'));
    self::assertSame([], $tag_container->get('advanced_settings'));
    self::assertNotEmpty($tag_container->get('events'));
    $conditions = $tag_container->get('conditions');
    self::assertArrayNotHasKey('gtag_language', $conditions);
    self::assertArrayHasKey('language', $conditions);
    self::assertArrayNotHasKey('request_path', $conditions);
    self::assertArrayNotHasKey('user_role', $conditions);

    // The other container is still upgraded.
    self::assertSame(['GTM-T26VRML'], $google_tag_storage->load(self::CONTAINER_2)->get('tag_container_ids'));
  }

  /**
   * Removes keys from a container's stored configuration.
   *
   * @param string $entity_id
   *   The container entity ID.
   * @param string[] $keys
   *   The top-level keys to remove.
   */
  protected function removeLegacyKeys(string $entity_id, array $keys): void {
    $name = 'google_tag.container.' . $entity_id;
    $config_storage = $this->container->get('config.storage');
    $data = array_diff_key($config_storage->read($name), array_flip($keys));
    $config_storage->write($name, $data);
    $this->container->get('config.factory')->reset($name);
  }

  /**
   * Tests that there are no changes to config.
   *
   * If there are no existing container entities.
   *
   * @throws \Exception
   */
  public function testNoEntitiesExist(): void {
    // Add the original google_tag 1.x config.
    $this->loadFixture('container_migrate_settings.php');
    $this->container->get('module_handler')->loadInclude('google_tag', 'install');
    $sandbox = [];
    google_tag_update_8201($sandbox);
    /** @var \Drupal\Core\Config\ImmutableConfig $gtag_settings */
    $gtag_settings = $this->container->get('config.factory')->get('google_tag.settings');
    self::assertFalse($gtag_settings->get('use_collection'));
    self::assertEquals('', $gtag_settings->get('default_google_tag_entity'));
    self::assertEmpty($this->container->get('entity_type.manager')->getStorage('google_tag_container')->loadMultiple());
  }

  /**
   * Tests that the 1.x gtag_domain condition becomes a Domain condition.
   *
   * @param string $toggle
   *   The 1.x domain_toggle value.
   * @param bool $negate
   *   The expected negate value of the converted condition.
   *
   * @covers ::google_tag_update_8201
   *
   * @dataProvider providerDomainToggle
   *
   * @throws \Exception
   */
  #[DataProvider('providerDomainToggle')]
  public function testDomainConditionConverted(string $toggle, bool $negate): void {
    $this->enableModules(['google_tag_domain_test']);
    $this->setLegacyDomainCondition($toggle, [
      'example_com' => 'example_com',
      'example_org' => 'example_org',
    ]);
    $this->runUpgrade();
    $conditions = $this->loadContainer(self::CONTAINER_1)->get('conditions');
    self::assertArrayNotHasKey('gtag_domain', $conditions);
    self::assertEquals([
      'id' => 'domain',
      'domains' => [
        'example_com' => 'example_com',
        'example_org' => 'example_org',
      ],
      'negate' => $negate,
      'context_mapping' => [
        'domain' => '@domain.current_domain_context:domain',
      ],
    ], $conditions['domain']);
  }

  /**
   * Data provider for testDomainConditionConverted().
   *
   * @return array
   *   The 1.x toggle and the expected negate value.
   */
  public static function providerDomainToggle(): array {
    return [
      'include listed' => ['include listed', FALSE],
      'exclude listed' => ['exclude listed', TRUE],
    ];
  }

  /**
   * Tests that a 1.x gtag_domain condition with no domains is dropped.
   *
   * With no domains listed, a Domain condition would match every domain.
   *
   * @throws \Exception
   */
  public function testEmptyDomainConditionDropped(): void {
    $this->enableModules(['google_tag_domain_test']);
    $this->setLegacyDomainCondition('exclude listed', []);
    $this->runUpgrade();
    $conditions = $this->loadContainer(self::CONTAINER_1)->get('conditions');
    self::assertArrayNotHasKey('gtag_domain', $conditions);
    self::assertArrayNotHasKey('domain', $conditions);
  }

  /**
   * Tests that gtag_domain is dropped when no Domain condition exists.
   *
   * @throws \Exception
   */
  public function testDomainConditionWithoutDomainModule(): void {
    $this->setLegacyDomainCondition('include listed', ['example_com' => 'example_com']);
    $this->runUpgrade();
    $conditions = $this->loadContainer(self::CONTAINER_1)->get('conditions');
    self::assertArrayNotHasKey('gtag_domain', $conditions);
    self::assertArrayNotHasKey('domain', $conditions);
  }

  /**
   * Loads the 1.x fixture and adds a gtag_domain condition to container 1.
   *
   * The data is written straight to config storage, as a 1.x site has it.
   *
   * @param string $toggle
   *   The 1.x domain_toggle value.
   * @param array $domains
   *   The 1.x domain_list value.
   *
   * @throws \Exception
   */
  private function setLegacyDomainCondition(string $toggle, array $domains): void {
    $name = 'google_tag.container.' . self::CONTAINER_1;
    $this->loadFixture('container_migrate.php');
    $storage = $this->container->get('config.storage');
    $data = $storage->read($name);
    $data['conditions'] = [
      'gtag_domain' => [
        'id' => 'gtag_domain',
        'context_mapping' => [
          'entity:domain' => '@domain.current_domain_context:domain',
        ],
        'domain_toggle' => $toggle,
        'domain_list' => $domains,
      ],
    ];
    $storage->write($name, $data);
    $this->container->get('config.factory')->reset($name);
    $this->container->get('entity_type.manager')->getStorage('google_tag_container')->resetCache();
  }

  /**
   * Runs the 1.x upgrade and the post updates that follow it.
   */
  private function runUpgrade(): void {
    $module_handler = $this->container->get('module_handler');
    $module_handler->loadInclude('google_tag', 'install');
    $module_handler->loadInclude('google_tag', 'php', 'google_tag.post_update');
    $sandbox = [];
    google_tag_update_8201($sandbox);
    google_tag_post_update_move_advanced_settings();
  }

  /**
   * Loads a tag container, bypassing the static cache.
   *
   * @param string $id
   *   The container ID.
   *
   * @return \Drupal\google_tag\Entity\TagContainer
   *   The tag container.
   */
  private function loadContainer(string $id): TagContainer {
    $storage = $this->container->get('entity_type.manager')->getStorage('google_tag_container');
    $storage->resetCache([$id]);
    $container = $storage->load($id);
    self::assertInstanceOf(TagContainer::class, $container);
    return $container;
  }

  /**
   * Tests that disabled containers are upgraded too.
   *
   * @covers ::google_tag_update_8201
   *
   * @throws \Exception
   */
  public function testDisabledEntitiesUpgraded(): void {
    $this->loadFixture('container_migrate.php');
    // Disable the container that sorts first.
    $this->setLegacyContainerValues(self::CONTAINER_1, ['status' => FALSE, 'weight' => 0]);
    $this->setLegacyContainerValues(self::CONTAINER_2, ['weight' => 1]);
    $this->container->get('module_handler')->loadInclude('google_tag', 'install');
    $sandbox = [];
    google_tag_update_8201($sandbox);

    $gtag_settings = $this->container->get('config.factory')->get('google_tag.settings');
    self::assertEquals(self::CONTAINER_2, $gtag_settings->get('default_google_tag_entity'), 'The first enabled container is the default.');
    self::assertTrue($gtag_settings->get('use_collection'));
    $google_tag_storage = $this->container->get('entity_type.manager')->getStorage('google_tag_container');
    /** @var \Drupal\google_tag\Entity\TagContainer $disabled */
    $disabled = $google_tag_storage->load(self::CONTAINER_1);
    self::assertFalse($disabled->status(), 'The container stays disabled.');
    self::assertEquals(['GTM-T26VRML'], $disabled->get('tag_container_ids'));
    self::assertNull($disabled->get('container_id'));
    self::assertArrayHasKey('GTM-T26VRML', $disabled->get('advanced_settings')['gtm']);
    self::assertNotEmpty($disabled->get('events'));
    $conditions = $disabled->get('conditions');
    self::assertArrayNotHasKey('gtag_language', $conditions);
    self::assertArrayHasKey('language', $conditions);
    self::assertArrayHasKey('request_path', $conditions);
    self::assertArrayHasKey('response_code', $conditions);
    self::assertArrayHasKey('user_role', $conditions);
  }

  /**
   * Tests the default container when every container is disabled.
   *
   * @covers ::google_tag_update_8201
   *
   * @throws \Exception
   */
  public function testAllEntitiesDisabled(): void {
    $this->loadFixture('container_migrate.php');
    $this->setLegacyContainerValues(self::CONTAINER_1, ['status' => FALSE, 'weight' => 0]);
    $this->setLegacyContainerValues(self::CONTAINER_2, ['status' => FALSE, 'weight' => 1]);
    $this->container->get('module_handler')->loadInclude('google_tag', 'install');
    $sandbox = [];
    google_tag_update_8201($sandbox);

    $gtag_settings = $this->container->get('config.factory')->get('google_tag.settings');
    self::assertEquals(self::CONTAINER_1, $gtag_settings->get('default_google_tag_entity'));
    $google_tag_storage = $this->container->get('entity_type.manager')->getStorage('google_tag_container');
    foreach ([self::CONTAINER_1, self::CONTAINER_2] as $id) {
      self::assertEquals(['GTM-T26VRML'], $google_tag_storage->load($id)->get('tag_container_ids'));
    }
  }

  /**
   * Overrides values in a 1.x container loaded from the fixture.
   *
   * @param string $id
   *   The container ID.
   * @param array $values
   *   The values to set.
   */
  protected function setLegacyContainerValues(string $id, array $values): void {
    $name = 'google_tag.container.' . $id;
    $config_storage = $this->container->get('config.storage');
    $config_storage->write($name, $values + $config_storage->read($name));
    $this->container->get('config.factory')->reset($name);
  }

}
