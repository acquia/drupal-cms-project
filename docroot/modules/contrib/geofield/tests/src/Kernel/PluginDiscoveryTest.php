<?php

namespace Drupal\Tests\geofield\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\KernelTests\KernelTestBase;
use Drupal\geofield\Plugin\GeofieldBackend\GeofieldBackendDefault;
use Drupal\geofield\Plugin\GeofieldBackend\GeofieldBackendPostgis;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the discovery of Geofield's own plugin types.
 *
 * The annotations are kept beside the attributes for the PHPUnit releases
 * that Drupal 10 runs, which read only the annotations.
 *
 * @group geofield
 */
#[Group('geofield')]
#[RunTestsInSeparateProcesses]
class PluginDiscoveryTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['geofield'];

  /**
   * The backends are discovered from their attributes.
   */
  public function testBackendsAreDiscovered(): void {
    $definitions = $this->container->get('plugin.manager.geofield_backend')->getDefinitions();
    ksort($definitions);

    $this->assertSame(['geofield_backend_default', 'geofield_backend_postgis'], array_keys($definitions));
    $this->assertSame(GeofieldBackendDefault::class, $definitions['geofield_backend_default']['class']);
    $this->assertSame('Default (WKT)', (string) $definitions['geofield_backend_default']['admin_label']);
    $this->assertSame(GeofieldBackendPostgis::class, $definitions['geofield_backend_postgis']['class']);
    $this->assertSame('PostGIS Geometry', (string) $definitions['geofield_backend_postgis']['admin_label']);
  }

  /**
   * The proximity sources are discovered from their attributes.
   */
  public function testProximitySourcesAreDiscovered(): void {
    $definitions = $this->container->get('plugin.manager.geofield_proximity_source')->getDefinitions();
    ksort($definitions);

    $this->assertSame([
      'geofield_client_location_origin',
      'geofield_context_filter',
      'geofield_manual_origin',
      'geofield_origin_from_proximity_filter',
    ], array_keys($definitions));
    $this->assertSame(['filter'], $definitions['geofield_client_location_origin']['context']);
    $this->assertTrue($definitions['geofield_client_location_origin']['exposedOnly']);
    $this->assertSame([], $definitions['geofield_manual_origin']['context']);
    $this->assertFalse(isset($definitions['geofield_manual_origin']['exposedOnly']));
  }

  /**
   * The source select offers each source in the contexts it declares.
   *
   * This is what the manager does with the definitions, through isset() on
   * keys a plugin may leave out, so it fails if an omitted key arrives as
   * anything but NULL.
   *
   * @dataProvider sourceOptionsProvider
   */
  #[DataProvider('sourceOptionsProvider')]
  public function testSourceOptionsPerContext(string $context, bool $exposed, array $expected): void {
    $form = [];
    $this->container->get('plugin.manager.geofield_proximity_source')
      ->buildCommonFormElements($form, new FormState(), ['exposed' => $exposed, 'exposed_units' => FALSE], $context);

    $options = array_keys($form['source']['#options']);
    sort($options);
    $this->assertSame($expected, $options);
  }

  /**
   * A source that sets no_ui is discovered but left out of the source select.
   */
  public function testNoUiSourceIsLeftOutOfTheSelect(): void {
    $this->enableModules(['geofield_proximity_source_test']);
    $manager = $this->container->get('plugin.manager.geofield_proximity_source');

    $definitions = $manager->getDefinitions();
    $this->assertArrayHasKey('geofield_test_hidden_origin', $definitions);
    $this->assertTrue($definitions['geofield_test_hidden_origin']['no_ui']);

    $form = [];
    $manager->buildCommonFormElements($form, new FormState(), ['exposed' => FALSE, 'exposed_units' => FALSE], 'sort');
    $options = array_keys($form['source']['#options']);
    sort($options);
    $this->assertSame([
      'geofield_context_filter',
      'geofield_manual_origin',
      'geofield_origin_from_proximity_filter',
    ], $options);
  }

  /**
   * Tests discovery of annotated plugins supplied by another module.
   *
   * @dataProvider legacyPluginProvider
   */
  #[DataProvider('legacyPluginProvider')]
  public function testAnnotatedPluginsAreDiscoverable(string $service_id, string $plugin_id, string $annotation_name, string $attribute_class, string $plugin_class): void {
    $this->enableModules(['geofield_legacy_plugin_test']);
    $manager = $this->container->get($service_id);
    $expected_message = sprintf(
      'Using @%s annotation for plugin with ID %s is deprecated and is removed from drupal:13.0.0. Use a %s attribute instead. See https://www.drupal.org/node/3395575',
      $annotation_name,
      $plugin_id,
      $attribute_class,
    );
    $messages = [];
    // Verify only the expected annotation notice across PHPUnit versions.
    // Leave all other errors and deprecations with the original handler.
    $previous_handler = NULL;
    $previous_handler = set_error_handler(static function (int $severity, string $message, string $file, int $line) use (&$messages, &$previous_handler, $expected_message): bool {
      if ($severity === E_USER_DEPRECATED && $message === $expected_message) {
        $messages[] = $message;
        return TRUE;
      }
      return $previous_handler ? (bool) $previous_handler($severity, $message, $file, $line) : FALSE;
    });
    try {
      $definitions = $manager->getDefinitions();
    }
    finally {
      restore_error_handler();
    }

    $this->assertArrayHasKey($plugin_id, $definitions);
    $this->assertSame($plugin_class, $definitions[$plugin_id]['class']);
    $this->assertInstanceOf($plugin_class, $manager->createInstance($plugin_id));
    if (version_compare(\Drupal::VERSION, '11.2.0', '>=')) {
      $this->assertSame([$expected_message], $messages);
    }
    else {
      $this->assertSame([], $messages);
    }
  }

  /**
   * Data provider for testSourceOptionsPerContext().
   */
  public static function sourceOptionsProvider(): array {
    return [
      'filter, not exposed' => ['filter', FALSE, ['geofield_manual_origin']],
      'filter, exposed' => ['filter', TRUE, ['geofield_client_location_origin', 'geofield_manual_origin']],
      'sort' => [
        'sort',
        FALSE,
        [
          'geofield_context_filter',
          'geofield_manual_origin',
          'geofield_origin_from_proximity_filter',
        ],
      ],
    ];
  }

  /**
   * Data provider for testAnnotatedPluginsAreDiscoverable().
   */
  public static function legacyPluginProvider(): array {
    return [
      'backend' => [
        'plugin.manager.geofield_backend',
        'geofield_test_annotated_backend',
        'GeofieldBackend',
        'Drupal\\geofield\\Attribute\\GeofieldBackend',
        'Drupal\\geofield_legacy_plugin_test\\Plugin\\GeofieldBackend\\AnnotatedBackend',
      ],
      'proximity source' => [
        'plugin.manager.geofield_proximity_source',
        'geofield_test_annotated_origin',
        'GeofieldProximitySource',
        'Drupal\\geofield\\Attribute\\GeofieldProximitySource',
        'Drupal\\geofield_legacy_plugin_test\\Plugin\\GeofieldProximitySource\\AnnotatedOrigin',
      ],
    ];
  }

}
