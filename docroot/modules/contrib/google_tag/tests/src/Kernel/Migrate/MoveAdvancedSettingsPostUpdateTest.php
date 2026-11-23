<?php

declare(strict_types=1);

namespace Drupal\Tests\google_tag\Kernel\Migrate;

use Drupal\google_tag\Entity\TagContainer;
use Drupal\Tests\google_tag\Kernel\GoogleTagTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests google_tag_post_update_move_advanced_settings().
 *
 * @group google_tag
 */
#[Group('google_tag')]
#[RunTestsInSeparateProcesses]
final class MoveAdvancedSettingsPostUpdateTest extends GoogleTagTestCase {

  /**
   * GTM settings as stored before 2.0.3.
   */
  private const LEGACY_GTM_SETTINGS = [
    'data_layer' => 'customLayer',
    'include_classes' => TRUE,
    'allowlist_classes' => 'google',
    'blocklist_classes' => 'customPixels',
    'include_environment' => TRUE,
    'environment_id' => 'env-7',
    'environment_token' => 'abc123',
  ];

  /**
   * Tests that the post update converts legacy settings per container.
   */
  public function testMoveAdvancedSettings(): void {
    // Loaded first, so the original code read an undefined variable here.
    TagContainer::create([
      'id' => 'a_gtag_only',
      'tag_container_ids' => ['G-XYZ1234'],
      'advanced_settings' => ['consent_mode' => TRUE],
    ])->save();
    $this->createLegacyContainer('b_legacy_gtm', ['GTM-AAAA111', 'G-XYZ1234', 'GTM-BBBB222'], TRUE);
    // Loaded after a legacy container, so it must not inherit its settings.
    $this->createLegacyContainer('c_gtag_legacy', ['G-XYZ1234']);
    $current_settings = [
      'gtm' => [
        'GTM-CCCC333' => ['data_layer' => 'firstLayer'] + self::LEGACY_GTM_SETTINGS,
        'GTM-DDDD444' => ['data_layer' => 'secondLayer'] + self::LEGACY_GTM_SETTINGS,
      ],
    ];
    TagContainer::create([
      'id' => 'd_current_gtm',
      'tag_container_ids' => ['GTM-CCCC333', 'GTM-DDDD444'],
      'advanced_settings' => $current_settings,
    ])->save();

    $this->container->get('module_handler')->loadInclude('google_tag', 'php', 'google_tag.post_update');
    google_tag_post_update_move_advanced_settings();

    $storage = $this->container->get('entity_type.manager')->getStorage('google_tag_container');
    $storage->resetCache();
    $containers = $storage->loadMultiple();

    self::assertSame(['consent_mode' => TRUE], $containers['a_gtag_only']->get('advanced_settings'));
    self::assertSame([
      'consent_mode' => TRUE,
      'gtm' => [
        'GTM-AAAA111' => self::LEGACY_GTM_SETTINGS,
        'GTM-BBBB222' => self::LEGACY_GTM_SETTINGS,
      ],
    ], $containers['b_legacy_gtm']->get('advanced_settings'));
    self::assertSame('customLayer', $containers['b_legacy_gtm']->getGtmSettings('GTM-BBBB222')['data_layer']);
    self::assertSame([], $containers['c_gtag_legacy']->get('advanced_settings'));
    self::assertSame($current_settings, $containers['d_current_gtm']->get('advanced_settings'));
  }

  /**
   * Creates a container holding GTM settings in the pre-2.0.3 format.
   *
   * The legacy format does not match the current schema, so it is written
   * straight to config storage, as it would be found in an old database.
   *
   * @param string $id
   *   The container ID.
   * @param string[] $tag_ids
   *   The tag IDs.
   * @param bool $consent_mode
   *   Whether to also store the consent_mode advanced setting.
   */
  private function createLegacyContainer(string $id, array $tag_ids, bool $consent_mode = FALSE): void {
    $container = TagContainer::create([
      'id' => $id,
      'tag_container_ids' => $tag_ids,
    ]);
    $container->save();
    $name = $container->getConfigDependencyName();
    $data = $this->container->get('config.storage')->read($name);
    $data['advanced_settings'] = ['gtm' => self::LEGACY_GTM_SETTINGS];
    if ($consent_mode) {
      $data['advanced_settings'] = ['consent_mode' => TRUE] + $data['advanced_settings'];
    }
    $this->container->get('config.storage')->write($name, $data);
    $this->container->get('config.factory')->reset($name);
  }

}
