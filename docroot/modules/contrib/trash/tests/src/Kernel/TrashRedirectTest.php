<?php

declare(strict_types=1);

namespace Drupal\Tests\trash\Kernel;

use Drupal\redirect\Entity\Redirect;
use Drupal\trash\Exception\UnrestorableEntityException;

/**
 * Tests Trash integration with the Redirect module.
 *
 * @group trash
 */
class TrashRedirectTest extends TrashKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'link',
    'path_alias',
    'redirect',
    'views',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('redirect');
    $this->installEntitySchema('path_alias');
    $this->installConfig(['redirect']);

    $this->enableEntityTypesForTrash(['redirect']);
  }

  /**
   * Test deleting redirects.
   */
  public function testDeletingRedirects(): void {
    $storage = \Drupal::entityTypeManager()->getStorage('redirect');
    /** @var \Drupal\redirect\RedirectRepository $repository */
    $repository = \Drupal::service('redirect.repository');

    $redirect = $storage->create();
    assert($redirect instanceof Redirect);
    $redirect->setSource('test-source');
    $redirect->setRedirect('node');
    $redirect->save();

    $this->assertNotEmpty($repository->findBySourcePath('test-source'));

    $redirect->delete();
    $this->assertEmpty($repository->findBySourcePath('test-source'));

    // Create a new redirect using the same source as the deleted one.
    $new_redirect = $storage->create();
    assert($new_redirect instanceof Redirect);
    $new_redirect->setSource('test-source');
    $new_redirect->setRedirect('user');
    $new_redirect->save();

    $found = $repository->findBySourcePath('test-source');
    $this->assertCount(1, $found);

    $new_redirect = reset($found);
    assert($new_redirect instanceof Redirect);
    $this->assertEquals('/user', $new_redirect->getRedirectUrl()->toString());

    // Check that restoring the original redirect is not possible.
    $this->expectException(UnrestorableEntityException::class);
    $this->expectExceptionMessage('There is an existing redirect with the same source URL.');
    $storage->restoreFromTrash([$redirect]);
  }

}
