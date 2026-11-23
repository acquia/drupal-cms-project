<?php

declare(strict_types=1);

namespace Drupal\Tests\google_tag\Kernel;

use Drupal\Core\Url;
use Drupal\google_tag\Entity\TagContainer;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * Tests ordering tag containers on the collection page.
 *
 * @coversDefaultClass \Drupal\google_tag\TagContainerListBuilder
 * @group google_tag
 */
#[Group('google_tag')]
#[RunTestsInSeparateProcesses]
final class TagContainerListBuilderTest extends GoogleTagTestCase {

  use UserCreationTrait;

  /**
   * Tests that container weights can be changed from the listing.
   */
  public function testReorderContainers(): void {
    $this->config('google_tag.settings')->set('use_collection', TRUE)->save();
    TagContainer::create([
      'id' => 'first',
      'label' => 'First',
      'weight' => 0,
      'tag_container_ids' => ['G-FIRST123'],
    ])->save();
    TagContainer::create([
      'id' => 'second',
      'label' => 'Second',
      'weight' => 1,
      'tag_container_ids' => ['G-SECOND12'],
    ])->save();
    self::assertSame('first', $this->resolveContainerId());

    $user = $this->createUser(['administer google_tag_container']);
    $this->container->get('current_user')->setAccount($user);

    $uri = Url::fromRoute('entity.google_tag_container.collection')->toString();
    $response = $this->doRequest(Request::create($uri));
    self::assertEquals(200, $response->getStatusCode());
    self::assertCount(1, $this->cssSelect('table#edit-entities'));
    self::assertCount(2, $this->cssSelect('tr.draggable'));
    self::assertCount(1, $this->cssSelect('select[name="entities[first][weight]"]'));
    self::assertCount(1, $this->cssSelect('select[name="entities[second][weight]"]'));
    self::assertCount(1, $this->cssSelect('input[name="form_id"][value="google_tag_container_list"]'));

    $form_data = [
      'entities' => [
        'first' => ['weight' => 5],
        'second' => ['weight' => -5],
      ],
      'form_build_id' => (string) $this->cssSelect('input[name="form_build_id"]')[0]->attributes()->value[0],
      'form_token' => (string) $this->cssSelect('input[name="form_token"]')[0]->attributes()->value[0],
      'form_id' => (string) $this->cssSelect('input[name="form_id"]')[0]->attributes()->value[0],
      'op' => (string) $this->cssSelect('input[data-drupal-selector="edit-submit"]')[0]->attributes()->value[0],
    ];
    $response = $this->doRequest(Request::create($uri, 'POST', $form_data));
    self::assertEquals(303, $response->getStatusCode());
    $this->doRequest(Request::create($response->headers->get('Location')));
    self::assertStringContainsString('The container order has been saved.', $this->getRawContent());

    $storage = $this->container->get('entity_type.manager')->getStorage('google_tag_container');
    $storage->resetCache();
    self::assertSame(5, $storage->load('first')->get('weight'));
    self::assertSame(-5, $storage->load('second')->get('weight'));

    // The resolver uses the first enabled container by weight.
    $this->container->set('google_tag.tag_container_resolver', NULL);
    self::assertSame('second', $this->resolveContainerId());
  }

  /**
   * Resolves the tag container for a fresh front page request.
   *
   * @return string|null
   *   The resolved container ID, if any.
   */
  private function resolveContainerId(): ?string {
    $request = Request::create('/');
    $request->setSession(new Session(new MockArraySessionStorage()));
    $this->container->get('request_stack')->push($request);
    $resolved = $this->container->get('google_tag.tag_container_resolver')->resolve();
    $this->container->get('request_stack')->pop();
    return $resolved?->id();
  }

}
