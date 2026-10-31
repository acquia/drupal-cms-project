<?php

namespace Drupal\Tests\eca\Kernel;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that every ECA plugin has a configuration schema section.
 *
 * A plugin without its schema mapping is not validated at all: typed
 * configuration falls back to "undefined", so none of the plugin's keys are
 * described, constrained or type-checked, and no violation is ever reported
 * for them. The mappings are named:
 * - "action.configuration.<plugin id>" for actions,
 * - "eca.event.plugin.<plugin id>" for events,
 * - "eca.condition.plugin.<plugin id>" for conditions.
 *
 * This test walks the plugins the way the ECA user interface collects them and
 * fails as soon as an ECA plugin is added without the matching schema section.
 */
#[Group('eca')]
#[Group('eca_core')]
#[RunTestsInSeparateProcesses]
class PluginConfigSchemaTest extends KernelTestBase {

  /**
   * Action plugin ID prefixes that cannot have a schema section.
   *
   * Pre-configured actions are derived from action configuration entities, so
   * their derivative ID embeds an entity ID that may itself contain a dot
   * (for example "user_add_role_action.administrator"). Typed configuration
   * builds its fallback names by replacing the part after the last dot, so
   * neither the concrete name nor a wildcard can address these derivatives.
   *
   * @see \Drupal\eca\Plugin\Action\PreConfiguredActionDeriver
   */
  private const EXCLUDED_ACTION_PREFIXES = [
    'eca_preconfigured_action:',
  ];

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'file',
    'filter',
    'image',
    'language',
    'migrate',
    'node',
    'breakpoint',
    'responsive_image',
    'serialization',
    'views',
    'workflows',
    'content_moderation',
    'modeler_api',
    'eca',
    'eca_access',
    'eca_base',
    'eca_cache',
    'eca_config',
    'eca_content',
    'eca_endpoint',
    'eca_file',
    'eca_form',
    'eca_htmx',
    'eca_language',
    'eca_log',
    'eca_menu',
    'eca_migrate',
    'eca_misc',
    'eca_node_access',
    'eca_queue',
    'eca_render',
    'eca_ui',
    'eca_user',
    'eca_views',
    'eca_workflow',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['user']);
  }

  /**
   * Tests that all ECA actions are covered by a configuration schema section.
   */
  public function testAllEcaActionsHaveConfigSchema(): void {
    $this->assertPluginsHaveConfigSchema(
      $this->container->get('eca.service.action')->actions(),
      'action.configuration.',
      100,
      self::EXCLUDED_ACTION_PREFIXES,
    );
  }

  /**
   * Tests that all ECA events are covered by a configuration schema section.
   */
  public function testAllEcaEventsHaveConfigSchema(): void {
    $this->assertPluginsHaveConfigSchema(
      $this->container->get('eca.service.event')->events(),
      'eca.event.plugin.',
      50,
    );
  }

  /**
   * Tests that all ECA conditions are covered by a schema section.
   */
  public function testAllEcaConditionsHaveConfigSchema(): void {
    $this->assertPluginsHaveConfigSchema(
      $this->container->get('eca.service.condition')->conditions(),
      'eca.condition.plugin.',
      20,
    );
  }

  /**
   * Tests that only render element actions inherit render element config.
   */
  public function testRenderElementActionSchemaInheritance(): void {
    $typedConfigManager = $this->container->get('config.typed');
    $renderElementKeys = ['name', 'token_name', 'weight', 'mode'];

    foreach ([
      'action.configuration.eca_render_markup',
      'action.configuration.eca_render_serialize:serialization',
      'action.configuration.eca_render_unserialize:serialization',
      'action.configuration.eca_htmx_element',
    ] as $schemaType) {
      $mapping = $typedConfigManager->getDefinition($schemaType)['mapping'];
      $this->assertSame(
        $renderElementKeys,
        array_values(array_intersect($renderElementKeys, array_keys($mapping))),
        sprintf('%s inherits render element configuration.', $schemaType),
      );
    }

    foreach ([
      'action.configuration.eca_render_alter_link_add_attribute',
      'action.configuration.eca_render_alter_link_add_class',
      'action.configuration.eca_render_alter_link_add_query_argument',
      'action.configuration.eca_render_alter_link_set_absolute',
      'action.configuration.eca_render_alter_link_set_language',
      'action.configuration.eca_render_alter_link_set_text',
      'action.configuration.eca_render_alter_link_set_title',
      'action.configuration.eca_render_alter_link_set_url',
    ] as $schemaType) {
      $mapping = $typedConfigManager->getDefinition($schemaType)['mapping'];
      $this->assertSame(
        [],
        array_values(array_intersect($renderElementKeys, array_keys($mapping))),
        sprintf('%s does not inherit render element configuration.', $schemaType),
      );
    }
  }

  /**
   * Asserts that all given ECA plugins have a configuration schema section.
   *
   * @param \Drupal\Component\Plugin\PluginInspectionInterface[] $plugins
   *   The plugin instances to check, as collected by ECA itself. Plugins that
   *   are not provided by ECA bring their own schema, which is not this
   *   project's responsibility, and are skipped.
   * @param string $prefix
   *   The schema name prefix the plugin ID is appended to.
   * @param int $minimum
   *   The lowest plausible number of ECA plugins to be checked. A safety net:
   *   should collecting the plugins ever silently stop working, the assertion
   *   on the missing sections would pass without testing anything.
   * @param string[] $excluded_prefixes
   *   Plugin ID prefixes to skip.
   */
  private function assertPluginsHaveConfigSchema(array $plugins, string $prefix, int $minimum, array $excluded_prefixes = []): void {
    $typedConfigManager = $this->container->get('config.typed');

    $missing = [];
    $checked = 0;
    foreach ($plugins as $plugin) {
      assert($plugin instanceof PluginInspectionInterface);
      $pluginId = $plugin->getPluginId();
      $provider = $plugin->getPluginDefinition()['provider'] ?? '';
      if ($provider !== 'eca' && !str_starts_with($provider, 'eca_')) {
        continue;
      }
      foreach ($excluded_prefixes as $excluded_prefix) {
        if (str_starts_with($pluginId, $excluded_prefix)) {
          continue 2;
        }
      }
      $checked++;
      $name = $prefix . $pluginId;
      $definition = $typedConfigManager->getDefinition($name, FALSE);
      if (($definition['type'] ?? 'undefined') === 'undefined') {
        $missing[] = $name;
      }
    }

    $this->assertGreaterThan($minimum, $checked);
    sort($missing);
    $this->assertSame([], $missing);
  }

}
