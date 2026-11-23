<?php

declare(strict_types=1);

namespace Drupal\Tests\google_tag\Kernel\Form;

use Drupal\Core\Url;
use Drupal\google_tag\Entity\TagContainer;
use Drupal\Tests\google_tag\Kernel\GoogleTagTestCase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @coversDefaultClass \Drupal\google_tag\Form\TagContainerForm
 * @group google_tag
 */
#[Group('google_tag')]
#[RunTestsInSeparateProcesses]
final class TagContainerFormTest extends GoogleTagTestCase {
  use UserCreationTrait;

  /**
   * Tests the form.
   */
  public function testForm(): void {
    $user = $this->createUser(['administer google_tag_container']);
    $this->container->get('current_user')->setAccount($user);

    $uri = Url::fromRoute('entity.google_tag_container.single_form')->toString();
    $response = $this->doRequest(Request::create($uri));
    self::assertEquals(200, $response->getStatusCode());

    $form_data = [
      'accounts' => [
        ['value' => 'GT-XXXXXX', 'weight' => 0],
        ['value' => 'UA-XXXXXX', 'weight' => 1],
        ['value' => 'GTM-XX3XXX', 'weight' => 1],
      ],
      'status' => TRUE,
      'form_build_id' => (string) $this->cssSelect('input[name="form_build_id"]')[0]->attributes()->value[0],
      'form_token' => (string) $this->cssSelect('input[name="form_token"]')[0]->attributes()->value[0],
      'form_id' => (string) $this->cssSelect('input[name="form_id"]')[0]->attributes()->value[0],
      'op' => (string) $this->cssSelect('#edit-submit')[0]->attributes()->value[0],
    ];

    $request = Request::create($uri, 'POST', $form_data);
    $response = $this->doRequest($request);
    self::assertEquals(303, $response->getStatusCode());
    $request = Request::create($response->headers->get('Location'));
    $this->doRequest($request);
    self::assertStringContainsString(
      'The configuration options have been saved.',
      $this->getRawContent()
    );

    $config = $this->config('google_tag.settings');
    self::assertNotEmpty($config->get('default_google_tag_entity'));
    $google_tag_id = $config->get('default_google_tag_entity');
    /** @var \Drupal\google_tag\Entity\TagContainer $google_tag */
    $google_tag = $this->container->get('entity_type.manager')->getStorage('google_tag_container')->load($google_tag_id);
    self::assertEmpty($google_tag->get('conditions'));
    self::assertTrue($google_tag->status());
  }

  /**
   * Tests that every submitted ID is saved, in weight order.
   *
   * @see https://www.drupal.org/project/google_tag/issues/3482534
   */
  public function testMultipleIds(): void {
    $this->createDefaultContainer(['GT-AAAAAA', 'UA-BBBBBB', 'G-CCCCCC', 'AW-DDDDDD', 'DC-EEEEEE']);
    $response = $this->submitAccounts([
      ['value' => 'GT-AAAAAA', 'weight' => 0],
      ['value' => 'UA-BBBBBB', 'weight' => 1],
      // A blank row is dropped rather than saved as an empty ID.
      ['value' => '', 'weight' => 2],
      // Rows sharing a weight are all kept.
      ['value' => 'AW-DDDDDD', 'weight' => 1],
      ['value' => 'DC-EEEEEE', 'weight' => -1],
    ]);
    self::assertSame(303, $response->getStatusCode());

    $google_tag = TagContainer::load('foo');
    self::assertSame(
      ['DC-EEEEEE', 'GT-AAAAAA', 'UA-BBBBBB', 'AW-DDDDDD'],
      $google_tag->get('tag_container_ids')
    );
  }

  /**
   * Tests that the form is not saved when every ID is blank.
   */
  public function testBlankIds(): void {
    $this->createDefaultContainer(['GT-AAAAAA', 'UA-BBBBBB']);
    $response = $this->submitAccounts([
      ['value' => '', 'weight' => 0],
      ['value' => '', 'weight' => 1],
    ]);
    self::assertSame(200, $response->getStatusCode());
    self::assertStringContainsString('Enter at least one Google Tag ID.', $this->getRawContent());
    self::assertSame(['GT-AAAAAA', 'UA-BBBBBB'], TagContainer::load('foo')->get('tag_container_ids'));
  }

  /**
   * Creates the default tag container and logs in a user who can edit it.
   *
   * @param string[] $ids
   *   The Google Tag IDs.
   */
  private function createDefaultContainer(array $ids): void {
    TagContainer::create([
      'id' => 'foo',
      'label' => 'Foo',
      'tag_container_ids' => $ids,
    ])->save();
    $this->config('google_tag.settings')->set('default_google_tag_entity', 'foo')->save();
    $this->container->get('current_user')->setAccount($this->createUser(['administer google_tag_container']));
  }

  /**
   * Gets the form and submits it with the given accounts.
   *
   * @param array $accounts
   *   The account rows, each with a value and a weight.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The response to the submission.
   */
  private function submitAccounts(array $accounts): Response {
    $this->doRequest(Request::create($this->getUri()));
    $form_data = [
      'accounts' => $accounts,
      'status' => TRUE,
      'form_build_id' => (string) $this->cssSelect('input[name="form_build_id"]')[0]->attributes()->value[0],
      'form_token' => (string) $this->cssSelect('input[name="form_token"]')[0]->attributes()->value[0],
      'form_id' => (string) $this->cssSelect('input[name="form_id"]')[0]->attributes()->value[0],
      'op' => (string) $this->cssSelect('input[data-drupal-selector="edit-submit"]')[0]->attributes()->value[0],
    ];
    return $this->doRequest(Request::create($this->getUri(), 'POST', $form_data));
  }

  /**
   * Gets the path of the tag container form.
   */
  private function getUri(): string {
    return Url::fromRoute('entity.google_tag_container.single_form')->toString();
  }

  /**
   * Tests that the edit form shows stored tag IDs without whitespace.
   *
   * Otherwise an ID stored with a leading space, for example by a recipe
   * input, fails the field pattern and the container cannot be saved.
   */
  public function testEditFormTrimsStoredTagId(): void {
    $user = $this->createUser(['administer google_tag_container']);
    $this->container->get('current_user')->setAccount($user);

    $entity = TagContainer::create(['id' => 'foo', 'label' => 'Foo']);
    $entity->save();
    $this->config('google_tag.container.foo')
      ->set('tag_container_ids', [' GTM-XXXXXX'])
      ->save();

    $response = $this->doRequest(Request::create($entity->toUrl('edit-form')->toString()));
    self::assertEquals(200, $response->getStatusCode());
    self::assertSame(
      'GTM-XXXXXX',
      (string) $this->cssSelect('input[name="accounts[0][value]"]')[0]->attributes()->value[0]
    );
  }

  /**
   * Tests that GTM environment IDs longer than seven characters are saved.
   *
   * @see https://www.drupal.org/project/google_tag/issues/3544798
   */
  public function testEnvironmentIdLength(): void {
    $user = $this->createUser(['administer google_tag_container']);
    $this->container->get('current_user')->setAccount($user);

    TagContainer::create([
      'id' => 'foo',
      'tag_container_ids' => ['GTM-XX3XXX'],
    ])->save();
    $this->config('google_tag.settings')
      ->set('default_google_tag_entity', 'foo')
      ->save();

    $uri = Url::fromRoute('entity.google_tag_container.single_form')->toString();
    $response = $this->doRequest(Request::create($uri));
    self::assertEquals(200, $response->getStatusCode());
    $environment_id_input = $this->cssSelect('input[name="advanced_settings[gtm][GTM-XX3XXX][environment_id]"]');
    self::assertCount(1, $environment_id_input);
    self::assertEquals('16', (string) $environment_id_input[0]->attributes()->maxlength[0]);

    $form_data = [
      'accounts' => [
        ['value' => 'GTM-XX3XXX', 'weight' => 0],
      ],
      'advanced_settings' => [
        'gtm' => [
          'GTM-XX3XXX' => [
            'data_layer' => 'dataLayer',
            'include_environment' => '1',
            'environment_id' => 'env-123456789012',
            'environment_token' => 'abcdefghijklmnopqrstuv',
          ],
        ],
      ],
      'status' => TRUE,
      'form_build_id' => (string) $this->cssSelect('input[name="form_build_id"]')[0]->attributes()->value[0],
      'form_token' => (string) $this->cssSelect('input[name="form_token"]')[0]->attributes()->value[0],
      'form_id' => (string) $this->cssSelect('input[name="form_id"]')[0]->attributes()->value[0],
      'op' => (string) $this->cssSelect('#edit-submit')[0]->attributes()->value[0],
    ];
    $response = $this->doRequest(Request::create($uri, 'POST', $form_data));
    self::assertEquals(303, $response->getStatusCode());

    /** @var \Drupal\google_tag\Entity\TagContainer $google_tag */
    $google_tag = $this->container->get('entity_type.manager')
      ->getStorage('google_tag_container')
      ->loadUnchanged('foo');
    $gtm_settings = $google_tag->getGtmSettings('GTM-XX3XXX');
    self::assertTrue((bool) $gtm_settings['include_environment']);
    self::assertSame('env-123456789012', $gtm_settings['environment_id']);
  }

  /**
   * Tests that saving the form keeps the tag IDs in weight order.
   *
   * @param array<int, int> $weights
   *   The weight to submit for each of the three tag IDs, in their stored
   *   order.
   * @param string[] $expected
   *   The expected tag IDs after saving.
   *
   * @dataProvider providerTagIdWeights
   */
  #[DataProvider('providerTagIdWeights')]
  public function testTagIdWeights(array $weights, array $expected): void {
    $user = $this->createUser(['administer google_tag_container']);
    $this->container->get('current_user')->setAccount($user);

    $tag_ids = ['G-AAAAAA', 'UA-BBBBBB', 'GT-CCCCCC'];
    TagContainer::create([
      'id' => 'foo',
      'label' => 'Foo',
      'tag_container_ids' => $tag_ids,
    ])->save();

    $uri = Url::fromRoute('entity.google_tag_container.edit_form', ['google_tag_container' => 'foo'])->toString();
    $response = $this->doRequest(Request::create($uri));
    self::assertEquals(200, $response->getStatusCode());

    $form_data = [
      'status' => TRUE,
      'form_build_id' => (string) $this->cssSelect('input[name="form_build_id"]')[0]->attributes()->value[0],
      'form_token' => (string) $this->cssSelect('input[name="form_token"]')[0]->attributes()->value[0],
      'form_id' => (string) $this->cssSelect('input[name="form_id"]')[0]->attributes()->value[0],
      'op' => (string) $this->cssSelect('input[data-drupal-selector="edit-submit"]')[0]->attributes()->value[0],
    ];
    foreach ($tag_ids as $index => $tag_id) {
      $form_data['accounts'][$index] = ['value' => $tag_id, 'weight' => $weights[$index]];
    }
    $response = $this->doRequest(Request::create($uri, 'POST', $form_data));
    self::assertEquals(303, $response->getStatusCode());

    $google_tag = $this->container->get('entity_type.manager')->getStorage('google_tag_container')->loadUnchanged('foo');
    self::assertInstanceOf(TagContainer::class, $google_tag);
    self::assertSame($expected, $google_tag->get('tag_container_ids'));
    self::assertSame($expected[0], $google_tag->getDefaultTagId());

    // The edit form lists the tag IDs in their saved order.
    $this->doRequest(Request::create($uri));
    $rendered = array_map(
      static fn ($input) => (string) $input->attributes()->value[0],
      $this->cssSelect('input[name$="[value]"][name^="accounts["]')
    );
    self::assertSame($expected, $rendered);
  }

  /**
   * Data provider for ::testTagIdWeights().
   *
   * @return array<string, array{array<int, int>, string[]}>
   *   The test cases.
   */
  public static function providerTagIdWeights(): array {
    return [
      'reordered' => [
        [2, 0, 1],
        ['UA-BBBBBB', 'GT-CCCCCC', 'G-AAAAAA'],
      ],
      'shared weights keep every tag ID' => [
        [1, 0, 0],
        ['UA-BBBBBB', 'GT-CCCCCC', 'G-AAAAAA'],
      ],
      'unchanged' => [
        [0, 1, 2],
        ['G-AAAAAA', 'UA-BBBBBB', 'GT-CCCCCC'],
      ],
    ];
  }

  /**
   * Tests the label given when a container is created.
   *
   * @dataProvider providerNewContainerLabel
   */
  #[DataProvider('providerNewContainerLabel')]
  public function testNewContainerLabel(string $label, string $expected_label): void {
    $user = $this->createUser(['administer google_tag_container']);
    $this->container->get('current_user')->setAccount($user);

    $uri = Url::fromRoute('entity.google_tag_container.single_form')->toString();
    $this->submitContainerForm($uri, [
      'label' => $label,
      'accounts' => [
        ['value' => 'G-XXXXXX', 'weight' => 0],
      ],
    ]);

    $google_tag_id = $this->config('google_tag.settings')->get('default_google_tag_entity');
    self::assertStringStartsWith('G-XXXXXX.', $google_tag_id);
    $google_tag = TagContainer::load($google_tag_id);
    self::assertNotNull($google_tag);
    self::assertSame($expected_label, $google_tag->label());
  }

  /**
   * Data provider for ::testNewContainerLabel().
   *
   * @return array<string, array{string, string}>
   *   The submitted label and the label expected on the saved container.
   */
  public static function providerNewContainerLabel(): array {
    return [
      'custom label' => ['Marketing site', 'Marketing site'],
      'empty label falls back to the tag ID' => ['', 'G-XXXXXX'],
      'blank label falls back to the tag ID' => ['   ', 'G-XXXXXX'],
    ];
  }

  /**
   * Tests that the label of an existing container can be changed.
   */
  public function testEditContainerLabel(): void {
    $user = $this->createUser(['administer google_tag_container']);
    $this->container->get('current_user')->setAccount($user);

    TagContainer::create([
      'id' => 'G-XXXXXX.123',
      'label' => 'G-XXXXXX',
      'tag_container_ids' => ['G-XXXXXX'],
    ])->save();

    $uri = Url::fromRoute('entity.google_tag_container.edit_form', [
      'google_tag_container' => 'G-XXXXXX.123',
    ])->toString();
    // The optional label sits in the advanced settings, not at the top.
    $this->doRequest(Request::create($uri));
    self::assertCount(1, $this->cssSelect('#advanced-settings-wrapper input[name="label"]'));
    self::assertCount(1, $this->cssSelect('input[name="label"]'));
    // The advanced settings start collapsed.
    self::assertCount(1, $this->cssSelect('#advanced-settings-wrapper > details:not([open])'));

    $this->submitContainerForm($uri, [
      'label' => 'Example domain',
      'accounts' => [
        ['value' => 'G-YYYYYY', 'weight' => 0],
      ],
    ]);

    $storage = $this->container->get('entity_type.manager')->getStorage('google_tag_container');
    $storage->resetCache();
    // The machine name stays as it was; only the label changes.
    self::assertSame(['G-XXXXXX.123'], array_keys($storage->loadMultiple()));
    $google_tag = $storage->load('G-XXXXXX.123');
    self::assertSame('Example domain', $google_tag->label());
    self::assertSame(['G-YYYYYY'], $google_tag->get('tag_container_ids'));

    // Clearing the label falls back to the first tag ID, as on the add form.
    $this->submitContainerForm($uri, [
      'label' => '  ',
      'accounts' => [
        ['value' => 'G-YYYYYY', 'weight' => 0],
      ],
    ]);
    $storage->resetCache();
    self::assertSame('G-YYYYYY', $storage->load('G-XXXXXX.123')->label());
  }

  /**
   * Submits the tag container form at the given URI.
   *
   * @param string $uri
   *   The form URI.
   * @param array $values
   *   The form values to submit.
   */
  private function submitContainerForm(string $uri, array $values): void {
    $response = $this->doRequest(Request::create($uri));
    self::assertEquals(200, $response->getStatusCode());

    $values += [
      'status' => TRUE,
      'form_build_id' => (string) $this->cssSelect('input[name="form_build_id"]')[0]->attributes()->value[0],
      'form_token' => (string) $this->cssSelect('input[name="form_token"]')[0]->attributes()->value[0],
      'form_id' => (string) $this->cssSelect('input[name="form_id"]')[0]->attributes()->value[0],
      'op' => (string) $this->cssSelect('input[data-drupal-selector="edit-submit"]')[0]->attributes()->value[0],
    ];
    $response = $this->doRequest(Request::create($uri, 'POST', $values));
    self::assertEquals(303, $response->getStatusCode());
  }

  /**
   * Tests turning off the noscript fallback for a GTM ID.
   */
  public function testNoscriptSetting(): void {
    $user = $this->createUser(['administer google_tag_container']);
    $this->container->get('current_user')->setAccount($user);

    $storage = $this->container->get('entity_type.manager')->getStorage('google_tag_container');
    $storage->create([
      'id' => 'foo',
      'label' => 'Foo',
      'tag_container_ids' => ['GTM-XXXXXX'],
    ])->save();

    $uri = Url::fromRoute('entity.google_tag_container.edit_form', ['google_tag_container' => 'foo'])->toString();
    $this->doRequest(Request::create($uri));
    $checkbox = $this->cssSelect('input[name="advanced_settings[gtm][GTM-XXXXXX][include_noscript]"]');
    self::assertCount(1, $checkbox);
    self::assertEquals('checked', (string) $checkbox[0]->attributes()->checked);

    // An unchecked checkbox is left out of the submission.
    $form_data = [
      'label' => 'Foo',
      'id' => 'foo',
      'accounts' => [
        ['value' => 'GTM-XXXXXX', 'weight' => 0],
      ],
      'status' => TRUE,
      'advanced_settings' => [
        'gtm' => [
          'GTM-XXXXXX' => [
            'data_layer' => 'dataLayer',
          ],
        ],
      ],
      'form_build_id' => (string) $this->cssSelect('input[name="form_build_id"]')[0]->attributes()->value[0],
      'form_token' => (string) $this->cssSelect('input[name="form_token"]')[0]->attributes()->value[0],
      'form_id' => (string) $this->cssSelect('input[name="form_id"]')[0]->attributes()->value[0],
      'op' => (string) $this->cssSelect('input[data-drupal-selector="edit-submit"]')[0]->attributes()->value[0],
    ];
    $response = $this->doRequest(Request::create($uri, 'POST', $form_data));
    self::assertEquals(303, $response->getStatusCode());

    $storage->resetCache();
    /** @var \Drupal\google_tag\Entity\TagContainer $google_tag */
    $google_tag = $storage->load('foo');
    self::assertFalse($google_tag->getGtmSettings('GTM-XXXXXX')['include_noscript']);
  }

}
