<?php

declare(strict_types=1);

namespace Drupal\Tests\trash\Kernel;

use Drupal\redirect\Entity\Redirect;
use Drupal\trash\Exception\UnrestorableEntityException;
use Drupal\trash\TrashViewBuilder;

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
    $this->installConfig(['redirect', 'system']);

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

  /**
   * Tests the trash listing for an entity with a composite label field.
   *
   * Redirect's label key is the composite 'redirect_source' field, which is
   * exposed in Views data per column ('redirect_source__path') rather than
   * under the bare field name. Both the rendered Title column and the exposed
   * Title filter must therefore target the main-property column.
   *
   * The IgnoreDeprecations attribute is used because rendering the redirect
   * trash listing loads the redirect module's plugins, which still use the
   * deprecated annotation style.
   */
  public function testTrashViewsListing(): void {
    $storage = \Drupal::entityTypeManager()->getStorage('redirect');

    foreach (['alpha-source', 'beta-source'] as $source) {
      $redirect = $storage->create();
      assert($redirect instanceof Redirect);
      $redirect->setSource($source);
      $redirect->setRedirect('node');
      $redirect->save();
      $redirect->delete();
    }

    $entity_type = \Drupal::entityTypeManager()->getDefinition('redirect');
    $view_builder = $this->container->get(TrashViewBuilder::class);
    $renderer = $this->container->get('renderer');

    // The Title column renders each redirect source path from the composite
    // label field's main-property column.
    $view = $view_builder->buildView($entity_type);
    $view->setDisplay('default');
    $build = $view->preview('default');
    $output = (string) $renderer->renderInIsolation($build);
    $this->assertStringContainsString('Title', $output);
    $this->assertStringContainsString('alpha-source', $output);
    $this->assertStringContainsString('beta-source', $output);

    // Filtering by one source path via the exposed Title filter, which is keyed
    // by the resolved main-property column, narrows the listing.
    $view = $view_builder->buildView($entity_type);
    $view->setDisplay('default');
    $view->setExposedInput(['redirect_source__path' => 'alpha']);
    $build = $view->preview('default');
    $output = (string) $renderer->renderInIsolation($build);
    $this->assertStringContainsString('alpha-source', $output);
    $this->assertStringNotContainsString('beta-source', $output);
  }

}
