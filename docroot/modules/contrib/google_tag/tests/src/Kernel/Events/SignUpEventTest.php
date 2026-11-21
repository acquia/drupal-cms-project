<?php

declare(strict_types=1);

namespace Drupal\Tests\google_tag\Kernel\Events;

use Drupal\Core\Url;
use Drupal\google_tag\Entity\TagContainer;
use Drupal\Tests\google_tag\Kernel\GoogleTagTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\TestWith;
use Symfony\Component\HttpFoundation\Request;

/**
 * Signup event test.
 *
 * @group google_tag
 */
#[Group('google_tag')]
#[RunTestsInSeparateProcesses]
final class SignUpEventTest extends GoogleTagTestCase {

  /**
   * Tests signup event.
   *
   * @testWith ["[site:url]"]
   *           [null]
   */
  #[TestWith(['[site:url]'])]
  #[TestWith([NULL])]
  public function testEvent(?string $method): void {
    $config = $method !== NULL ? ['method' => $method] : [];
    TagContainer::create([
      'id' => 'foo',
      'weight' => 10,
      'tag_container_ids' => ['G-XXXXXX'],
      'events' => ['sign_up' => $config],
    ])->save();

    $uri = Url::fromRoute('user.register')->toString();

    $this->doRequest(Request::create($uri));

    $form_data = [
      'mail' => 'foo@example.com',
      'name' => 'name',
      'form_build_id' => (string) $this->cssSelect('input[name="form_build_id"]')[0]->attributes()->value[0],
      'form_id' => (string) $this->cssSelect('input[name="form_id"]')[0]->attributes()->value[0],
      'op' => (string) $this->cssSelect('input[name="op"]')[0]->attributes()->value[0],
    ];

    $request = Request::create($uri, 'POST', $form_data);
    // We need to start a session since \Drupal\Core\StackMiddleware\Session
    // does not due to command line.
    $session = $this->container->get('session');
    $session->start();
    $request->setSession($session);

    $response = $this->doRequest($request);
    self::assertEquals(303, $response->getStatusCode());
    // The redirect is followed in a new PHP request in the real world, so drop
    // the collector's in-memory events; only events kept in the session count.
    $this->container->set('google_tag.event_collector', NULL);
    $request = Request::create($response->headers->get('Location'));
    $this->doRequest($request);

    $this->assertGoogleTagEvents([
      [
        'name' => 'sign_up',
        'data' => [
          'method' => $method && str_starts_with($method, '[')
            ? $this->container->get('token')->replace($method, [], ['clear' => TRUE])
            : 'CMS',
        ],
      ],
    ]);
  }

}
