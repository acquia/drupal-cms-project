<?php

namespace Drupal\Tests\eca_views\Kernel;

use Drupal\Core\Config\Schema\TypedConfigInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\views\Entity\View;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel tests for the config schema of the "eca" views access plugin.
 *
 * Core resolves the access options of a display as
 * "views.access.[%parent.type]" in "views.data_types.schema.yml", but it ships
 * no "views.access.*" wildcard - unlike "views.argument_default.*". Every
 * access plugin therefore has to declare its own schema entry, and without
 * "views.access.eca" a view whose display uses the plugin produces
 * configuration with no matching schema. That is an error under the strict
 * config schema checking which KernelTestBase and BrowserTestBase enable, so
 * the plugin could not be exercised through stored view configuration at all.
 *
 * The mapping is deliberately empty, and that is not an oversight. The plugin
 * stores no options: it overrides only create(), access(),
 * alterRouteDefinition() and the cacheability methods, and neither it nor
 * AccessPluginBase declares defineOptions(), so the chain terminates at
 * PluginBase::defineOptions() returning an empty array. The binding between a
 * view and the ECA model deciding access lives on the ECA side instead, where
 * "eca.event.plugin.eca_views:access" carries "view_id" and "display_id".
 * On the route path the plugin sets a literal "_eca_views_access_check"
 * requirement carrying no plugin state, and \Drupal\eca_views\AccessCheck
 * re-resolves the view from the route parameters, so a plugin option could not
 * reach the decision there either. Stored configuration is always
 * "access: {type: eca, options: {}}".
 *
 * @see \Drupal\eca_views\Plugin\views\access\Eca
 * @see \Drupal\eca_views\AccessCheck
 */
#[Group('eca')]
#[Group('eca_views')]
#[RunTestsInSeparateProcesses]
class ViewsAccessSchemaTest extends KernelTestBase {

  /**
   * The ID of the view used throughout this test.
   */
  private const VIEW_ID = 'test_view';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'views',
    'eca',
    'eca_views',
    'modeler_api',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installConfig(static::$modules);
  }

  /**
   * Tests that a config schema for the views access plugin exists.
   */
  public function testAccessPluginSchemaExists(): void {
    $this->assertTrue(
      \Drupal::service('config.typed')->hasConfigSchema('views.access.eca'),
      'A config schema exists for the "eca" views access plugin.'
    );
  }

  /**
   * Tests that a view using the access plugin survives a save round trip.
   *
   * Saving is what fails without the schema entry: the strict config schema
   * checking enabled by KernelTestBase rejects the stored access options.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testViewUsingAccessPluginRoundTrips(): void {
    $this->createViewWithEcaAccess()->save();

    $saved = View::load(self::VIEW_ID);
    $this->assertInstanceOf(View::class, $saved);

    $access = $saved->getDisplay('default')['display_options']['access'];
    $this->assertSame('eca', $access['type'], 'The saved view retains the "eca" access plugin.');
    $this->assertSame([], $access['options'], 'The saved view stores no access plugin options.');
  }

  /**
   * Tests that the saved view raises no config validation constraints.
   *
   * The schema checker alone would also pass on a mapping declared without any
   * constraints, so this covers the "FullyValidatable" half of the entry: an
   * option added to the plugin without a matching schema update has to fail
   * loudly rather than slip through.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testSavedViewHasNoValidationViolations(): void {
    $this->createViewWithEcaAccess()->save();

    $saved = View::load(self::VIEW_ID);
    $this->assertInstanceOf(View::class, $saved);

    $typed_config = \Drupal::service('config.typed')
      ->createFromNameAndData('views.view.' . self::VIEW_ID, $saved->toArray());
    $this->assertInstanceOf(TypedConfigInterface::class, $typed_config);

    $violations = [];
    foreach ($typed_config->validate() as $violation) {
      $violations[$violation->getPropertyPath()] = (string) $violation->getMessage();
    }
    $this->assertSame([], $violations, 'The saved view raises no config validation constraints.');
  }

  /**
   * Builds a view whose default display uses the ECA access plugin.
   *
   * @return \Drupal\views\Entity\View
   *   The unsaved view.
   */
  private function createViewWithEcaAccess(): View {
    return View::create([
      'id' => self::VIEW_ID,
      'label' => 'Test View',
      'base_table' => 'users_field_data',
      'base_field' => 'uid',
      'display' => [
        'default' => [
          'display_plugin' => 'default',
          'id' => 'default',
          'display_title' => 'Default',
          'position' => 0,
          'display_options' => [
            'access' => [
              'type' => 'eca',
              'options' => [],
            ],
          ],
        ],
      ],
    ]);
  }

}
