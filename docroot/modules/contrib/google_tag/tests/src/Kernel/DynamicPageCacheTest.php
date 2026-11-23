<?php

declare(strict_types=1);

namespace Drupal\Tests\google_tag\Kernel;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\PageCache\ChainRequestPolicy;
use Drupal\Core\Url;
use Drupal\google_tag\Entity\TagContainer;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tests delayed events on pages served by the Dynamic Page Cache.
 *
 * @group google_tag
 */
#[Group('google_tag')]
#[RunTestsInSeparateProcesses]
final class DynamicPageCacheTest extends GoogleTagTestCase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['dynamic_page_cache', 'google_tag_test'];

  /**
   * The event expected on the page after a delayed generate_lead event.
   */
  private const EVENT = [
    'name' => 'generate_lead',
    'data' => [
      'currency' => 'USD',
      'value' => '12',
    ],
  ];

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container): void {
    parent::register($container);
    // The default policy denies every command line request, which includes
    // kernel test requests. Keep only the policies added by modules.
    if ($container->hasDefinition('dynamic_page_cache_request_policy')) {
      $container->getDefinition('dynamic_page_cache_request_policy')
        ->setClass(ChainRequestPolicy::class);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    TagContainer::create([
      'id' => 'foo',
      'weight' => 10,
      'tag_container_ids' => ['G-XYZ1234'],
      'events' => [
        'generate_lead' => [
          'value' => '12',
          'currency' => 'USD',
        ],
      ],
    ])->save();
  }

  /**
   * Tests a delayed event on a page already in the Dynamic Page Cache.
   */
  public function testDelayedEventOnCachedPage(): void {
    $this->requestPage('a');
    $response = $this->requestPage('a');
    self::assertSame('HIT', $response->headers->get('X-Drupal-Dynamic-Cache'));
    $this->assertEvents([]);

    $this->container->get('google_tag.event_collector')->addDelayedEvent('generate_lead');

    // The cached copy has no events, so it must not be served.
    $response = $this->requestPage('a');
    self::assertNotSame('HIT', $response->headers->get('X-Drupal-Dynamic-Cache'));
    $this->assertEvents([self::EVENT]);

    // The event is sent once only.
    $response = $this->requestPage('a');
    self::assertSame('HIT', $response->headers->get('X-Drupal-Dynamic-Cache'));
    $this->assertEvents([]);
  }

  /**
   * Tests a delayed event on a page not yet in the Dynamic Page Cache.
   */
  public function testDelayedEventOnUncachedPage(): void {
    $this->container->get('google_tag.event_collector')->addDelayedEvent('generate_lead');

    $response = $this->requestPage('b');
    self::assertNotSame('HIT', $response->headers->get('X-Drupal-Dynamic-Cache'));
    $this->assertEvents([self::EVENT]);

    // The page holding the event must not have been cached, otherwise every
    // later visit sends the event again.
    $this->requestPage('b');
    $this->assertEvents([]);
    $response = $this->requestPage('b');
    self::assertSame('HIT', $response->headers->get('X-Drupal-Dynamic-Cache'));
    $this->assertEvents([]);
  }

  /**
   * Requests the test page with a fresh event collector.
   *
   * @param string $name
   *   Test page name; each name is cached separately.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The response.
   */
  private function requestPage(string $name): Response {
    // Only events stored in the session may survive between requests.
    $this->container->set('google_tag.event_collector', NULL);
    $url = Url::fromRoute('google_tag_test.page', ['name' => $name]);
    return $this->doRequest(Request::create($url->toString()));
  }

  /**
   * Asserts the gtag events on the last requested page.
   *
   * @param array $events
   *   The expected events.
   */
  private function assertEvents(array $events): void {
    self::assertArrayHasKey('gtag', $this->drupalSettings);
    self::assertSame($events, $this->drupalSettings['gtag']['events']);
  }

}
