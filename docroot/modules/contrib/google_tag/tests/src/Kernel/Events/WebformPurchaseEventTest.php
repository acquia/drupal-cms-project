<?php

declare(strict_types=1);

namespace Drupal\Tests\google_tag\Kernel\Events;

use Drupal\google_tag\Entity\TagContainer;
use Drupal\Tests\google_tag\Kernel\GoogleTagTestCase;
use Drupal\webform\Entity\Webform;
use Drupal\webform\Entity\WebformSubmission;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;

/**
 * Webform purchase event test.
 *
 * @group google_tag
 */
#[Group('google_tag')]
#[RunTestsInSeparateProcesses]
final class WebformPurchaseEventTest extends GoogleTagTestCase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'path',
    'field',
    'filter',
    'webform',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installSchema('webform', ['webform']);
    $this->installConfig(['filter', 'webform']);
    $this->installEntitySchema('webform_submission');

    TagContainer::create([
      'id' => 'foo',
      'weight' => 10,
      'tag_container_ids' => ['G-XYZ1234'],
      'events' => ['webform_purchase' => []],
    ])->save();

    $webform = Webform::create([
      'id' => 'contact_test',
      'title' => 'Contact test',
      'elements' => "name:\n  '#type': textfield\n  '#title': Name",
    ]);
    $handler = $this->container->get('plugin.manager.webform.handler')
      ->createInstance('google_tag_webform_purchase', [
        'handler_id' => 'google_tag_purchase',
        'status' => TRUE,
        'weight' => 0,
      ]);
    $webform->addWebformHandler($handler);
    $webform->save();
  }

  /**
   * Tests the purchase event survives the redirect after submission.
   */
  public function testEventAfterRedirect(): void {
    $uri = Webform::load('contact_test')->toUrl()->toString();
    $this->doRequest(Request::create($uri));

    $form_data = [
      'name' => 'Foo',
      'form_build_id' => (string) $this->cssSelect('input[name="form_build_id"]')[0]->attributes()->value[0],
      'form_id' => (string) $this->cssSelect('input[name="form_id"]')[0]->attributes()->value[0],
      'op' => (string) $this->cssSelect('[name="op"]')[0]->attributes()->value[0],
    ];

    $request = Request::create($uri, 'POST', $form_data);
    // We need to start a session since \Drupal\Core\StackMiddleware\Session
    // does not due to command line.
    $session = $this->container->get('session');
    $session->start();
    $request->setSession($session);

    $response = $this->doRequest($request);
    self::assertEquals(303, $response->getStatusCode());
    $submissions = WebformSubmission::loadMultiple();
    self::assertCount(1, $submissions);
    $submission = reset($submissions);

    // The kernel container is shared between requests, so drop the collector
    // to make sure only events stored in the session reach the next page.
    $this->container->set('google_tag.event_collector', NULL);
    $request = Request::create($response->headers->get('Location'));
    $request->setSession($session);
    $this->doRequest($request);

    $this->assertGoogleTagEvents([
      [
        'name' => 'webform_purchase',
        'data' => [
          'webform_id' => 'contact_test',
          'submission_id' => $submission->id(),
        ],
      ],
    ]);
  }

}
