<?php

declare(strict_types=1);

namespace Drupal\Tests\google_tag\Kernel;

use Drupal\Core\Url;
use Drupal\google_tag\Entity\TagContainer;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests page attachment hooks.
 *
 * @group google_tag
 */
#[Group('google_tag')]
#[RunTestsInSeparateProcesses]
final class PageAttachmentsHookTest extends GoogleTagTestCase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['google_tag_test'];

  /**
   * Tests that there are no attachments when there's no config.
   */
  public function testNoEmbedOnNoConfig(): void {
    $page = [];
    $this->container
      ->get('main_content_renderer.html')
      ->invokePageAttachmentHooks($page);
    self::assertEquals($this->container->get('entity_type.manager')->getDefinition('google_tag_container')->getListCacheTags(), $page['#cache']['tags']);
    self::assertEquals(-1, $page['#cache']['max-age']);
    self::assertNotContains('google_tag/gtag', $page['#attached']['library']);
    self::assertNotContains('drupalSettings', $page['#attached']);
  }

  /**
   * Tests that there are valid attachments when there's google_tag configured.
   */
  public function testAttachments(): void {
    $entity = TagContainer::create([
      'id' => 'foo',
      // https://developers.google.com/tag-platform/gtagjs/configure#:~:text=What%20is%20a%20tag%20ID%20and%20where%20to%20find%20it%3F
      // @todo need unit test on config entity for this and the methods of default and additional.
      'tag_container_ids' => [
        'GT-XXXXXX',
        'G-XXXXXX',
        'AW-XXXXXX',
        'DC-XXXXXX',
        'UA-XXXXXX',
      ],
      'conditions' => [
        'request_path' => [
          'id' => 'request_path',
          'negate' => TRUE,
          'pages' => '<front>',
        ],
      ],
      'events' => [
        'route_name' => [],
      ],
    ]);
    $entity->save();

    $page = [];
    $this->container
      ->get('main_content_renderer.html')
      ->invokePageAttachmentHooks($page);
    self::assertEquals(['config:google_tag_container_list', 'config:google_tag.container.foo'], $page['#cache']['tags']);
    self::assertEquals(-1, $page['#cache']['max-age']);
    self::assertContains('google_tag/gtag', $page['#attached']['library']);
    self::assertEquals([
      'gtag' => [
        'tagId' => 'GT-XXXXXX',
        'otherIds' => [
          'G-XXXXXX',
          'AW-XXXXXX',
          'DC-XXXXXX',
          'UA-XXXXXX',
        ],
        'events' => [
          [
            'name' => 'route_name',
            'data' => [
              'route_name' => '<none>',
            ],
          ],
        ],
        'additionalConfigInfo' => [],
        'consentMode' => FALSE,
      ],
    ], $page['#attached']['drupalSettings']);

  }

  /**
   * Tests that a GTM-only container still sends events to the data layer.
   */
  public function testGtmOnlyAttachments(): void {
    TagContainer::create([
      'id' => 'foo',
      'tag_container_ids' => ['GTM-XXXXXX'],
      'events' => [
        'route_name' => [],
      ],
    ])->save();

    $page = [];
    $this->container
      ->get('main_content_renderer.html')
      ->invokePageAttachmentHooks($page);
    self::assertContains('google_tag/gtm', $page['#attached']['library']);
    self::assertContains('google_tag/gtag', $page['#attached']['library']);
    self::assertNotContains('google_tag/gtag.ajax', $page['#attached']['library']);
    self::assertEquals([
      'tagId' => '',
      'otherIds' => [],
      'events' => [
        [
          'name' => 'route_name',
          'data' => [
            'route_name' => '<none>',
          ],
        ],
      ],
      'additionalConfigInfo' => [],
      'consentMode' => FALSE,
    ], $page['#attached']['drupalSettings']['gtag']);
  }

  /**
   * Tests hook_page_top for google_tag attachments.
   */
  public function testPageTopAttachments(): void {
    TagContainer::create([
      'id' => 'foo',
      'tag_container_ids' => [
        'GTM-XXXXXX',
        'GTM-YYYYYY',
        'GT-XXXXXX',
        'G-XXXXXX',
        'AW-XXXXXX',
        'DC-XXXXXX',
        'UA-XXXXXX',
      ],
    ])->save();

    $page = [];
    $this->container
      ->get('main_content_renderer.html')->buildPageTopAndBottom($page);
    self::assertEquals([
      '#cache' => [
        'contexts' => [],
        'tags' => ['config:google_tag_container_list', 'config:google_tag.container.foo'],
        'max-age' => -1,
      ],
      'google_tag_gtm_iframe' => [
        '0' => [
          '#theme' => 'google_tag_gtm_iframe',
          '#url' => Url::fromUri('https://www.googletagmanager.com/ns.html', ['query' => ['id' => 'GTM-XXXXXX']]),
        ],
        '1' => [
          '#theme' => 'google_tag_gtm_iframe',
          '#url' => Url::fromUri('https://www.googletagmanager.com/ns.html', ['query' => ['id' => 'GTM-YYYYYY']]),
        ],
      ],
    ], $page['page_top']
    );
  }

  /**
   * Tests that the noscript fallback can be turned off per GTM ID.
   */
  public function testPageTopNoscriptDisabled(): void {
    $entity = TagContainer::create([
      'id' => 'foo',
      'tag_container_ids' => [
        'GTM-XXXXXX',
        'GTM-YYYYYY',
      ],
      'advanced_settings' => [
        'gtm' => [
          'GTM-XXXXXX' => [
            'data_layer' => 'dataLayer',
            'include_noscript' => FALSE,
          ],
        ],
      ],
    ]);
    $entity->save();

    // GTM-YYYYYY has no stored settings, so it keeps the fallback.
    $page = [];
    $this->container
      ->get('main_content_renderer.html')->buildPageTopAndBottom($page);
    self::assertEquals([
      [
        '#theme' => 'google_tag_gtm_iframe',
        '#url' => Url::fromUri('https://www.googletagmanager.com/ns.html', ['query' => ['id' => 'GTM-YYYYYY']]),
      ],
    ], $page['page_top']['google_tag_gtm_iframe']);
    self::assertContains('config:google_tag.container.foo', $page['page_top']['#cache']['tags']);

    $entity->set('advanced_settings', [
      'gtm' => [
        'GTM-XXXXXX' => ['include_noscript' => FALSE],
        'GTM-YYYYYY' => ['include_noscript' => FALSE],
      ],
    ])->save();
    $this->container->set('google_tag.tag_container_resolver', NULL);

    $page = [];
    $this->container
      ->get('main_content_renderer.html')->buildPageTopAndBottom($page);
    self::assertArrayNotHasKey('google_tag_gtm_iframe', $page['page_top']);
    self::assertContains('config:google_tag.container.foo', $page['page_top']['#cache']['tags']);

    // The GTM script is still attached.
    $page = [];
    $this->container
      ->get('main_content_renderer.html')
      ->invokePageAttachmentHooks($page);
    self::assertContains('google_tag/gtm', $page['#attached']['library']);
  }

  /**
   * Tests gtm attachments in drupal settings in hook_page_attachments.
   */
  public function testGtmAttachments(): void {
    $allowlist_classes = 'google' . PHP_EOL . 'nonGoogleIframes';
    $blocklist_classes = 'nonGoogleScripts' . PHP_EOL . 'customPixels';
    TagContainer::create([
      'id' => 'foo',
      'tag_container_ids' => [
        'GTM-XXXXXX',
        'GTM-YYYYYY',
        'GT-XXXXXX',
        'G-XXXXXX',
        'AW-XXXXXX',
        'DC-XXXXXX',
        'UA-XXXXXX',
      ],
      'advanced_settings' => [
        'gtm' => [
          'GTM-XXXXXX' => [
            'include_classes' => TRUE,
            'allowlist_classes' => $allowlist_classes,
            'blocklist_classes' => $blocklist_classes,
          ],
          'GTM-YYYYYY' => [
            'include_classes' => TRUE,
            'allowlist_classes' => $allowlist_classes,
            'blocklist_classes' => $blocklist_classes,
          ],
        ],
      ],
    ])->save();
    $page = [];
    $this->container
      ->get('main_content_renderer.html')
      ->invokePageAttachmentHooks($page);
    self::assertContains('google_tag/gtm', $page['#attached']['library']);
    self::assertEquals([
      'tagIds' => ['GTM-XXXXXX', 'GTM-YYYYYY'],
      'settings' => [
        'include_classes' => TRUE,
        'allowlist_classes' => explode(PHP_EOL, $allowlist_classes),
        'blocklist_classes' => explode(PHP_EOL, $blocklist_classes),
      ],
    ],
    $page['#attached']['drupalSettings']['gtm']);
  }

  /**
   * Tests that empty class lines are not sent as allowed or blocked classes.
   *
   * An allowlist holding only an empty string allows no tag types at all.
   *
   * @see https://www.drupal.org/project/google_tag/issues/3343483
   */
  public function testGtmEmptyClassLists(): void {
    TagContainer::create([
      'id' => 'foo',
      'tag_container_ids' => ['GTM-XXXXXX'],
      'advanced_settings' => [
        'gtm' => [
          'GTM-XXXXXX' => [
            'include_classes' => TRUE,
            'allowlist_classes' => '',
            'blocklist_classes' => "\r\nnonGoogleScripts\r\n\r\n customPixels \n",
          ],
        ],
      ],
    ])->save();
    $page = [];
    $this->container
      ->get('main_content_renderer.html')
      ->invokePageAttachmentHooks($page);
    $settings = $page['#attached']['drupalSettings']['gtm']['settings'];
    self::assertSame([], $settings['allowlist_classes']);
    self::assertSame(['nonGoogleScripts', 'customPixels'], $settings['blocklist_classes']);
  }

  /**
   * Tests that surrounding whitespace is removed from tag IDs on save.
   */
  public function testTagIdWhitespaceTrimmedOnSave(): void {
    TagContainer::create([
      'id' => 'foo',
      'tag_container_ids' => [' GTM-XXXXXX', "G-XXXXXX \n", "\tAW-XXXXXX"],
    ])->save();
    self::assertSame(
      ['GTM-XXXXXX', 'G-XXXXXX', 'AW-XXXXXX'],
      $this->config('google_tag.container.foo')->get('tag_container_ids')
    );
  }

  /**
   * Tests that stored tag IDs with whitespace still render valid URLs.
   *
   * Recipe inputs and config imports can store IDs the form would reject,
   * such as an ID pasted with a leading space.
   */
  public function testTagIdWhitespaceInStoredConfig(): void {
    TagContainer::create([
      'id' => 'foo',
    ])->save();
    // Write the IDs past the entity, as a config import would.
    $this->config('google_tag.container.foo')
      ->set('tag_container_ids', [' GTM-XXXXXX', 'GTM-YYYYYY ', ' G-XXXXXX', ' AW-XXXXXX '])
      ->save();
    $this->container->get('entity_type.manager')
      ->getStorage('google_tag_container')
      ->resetCache();

    $page = [];
    $renderer = $this->container->get('main_content_renderer.html');
    $renderer->invokePageAttachmentHooks($page);
    self::assertSame(['GTM-XXXXXX', 'GTM-YYYYYY'], $page['#attached']['drupalSettings']['gtm']['tagIds']);
    self::assertSame('G-XXXXXX', $page['#attached']['drupalSettings']['gtag']['tagId']);
    self::assertSame(['AW-XXXXXX'], $page['#attached']['drupalSettings']['gtag']['otherIds']);

    $page = [];
    $renderer->buildPageTopAndBottom($page);
    self::assertEquals(
      Url::fromUri('https://www.googletagmanager.com/ns.html', ['query' => ['id' => 'GTM-XXXXXX']]),
      $page['page_top']['google_tag_gtm_iframe'][0]['#url']
    );
  }

}
