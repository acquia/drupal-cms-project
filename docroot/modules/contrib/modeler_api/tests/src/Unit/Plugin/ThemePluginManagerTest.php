<?php

declare(strict_types=1);

namespace Drupal\Tests\modeler_api\Unit\Plugin;

use Drupal\modeler_api\Plugin\ThemePluginManager;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests how the theme plugin manager turns YAML definitions into themes.
 *
 * The manager is the only guard against a broken theme definition. A theme
 * without a label could not be selected in the settings form, and a theme
 * without an asset library would be selectable but have no effect at all, so
 * both are dropped during discovery rather than surfacing as a dead option.
 */
#[CoversClass(ThemePluginManager::class)]
#[Group('modeler_api')]
class ThemePluginManagerTest extends UnitTestCase {

  /**
   * Builds a theme plugin manager exposing a fixed set of definitions.
   *
   * @param array $definitions
   *   The plugin definitions that discovery would have produced.
   *
   * @return \Drupal\modeler_api\Plugin\ThemePluginManager
   *   The theme plugin manager under test.
   */
  protected function buildManager(array $definitions): ThemePluginManager {
    return new class($definitions) extends ThemePluginManager {

      /**
       * Constructs the manager with a fixed set of definitions.
       *
       * The parent constructor is deliberately not called: it wires up YAML
       * discovery, the module handler and the cache backend, none of which are
       * available in a unit test. Discovery is replaced by returning the given
       * definitions verbatim from getDefinitions().
       *
       * @param array $testDefinitions
       *   The plugin definitions to expose.
       */
      public function __construct(protected array $testDefinitions) {}

      /**
       * {@inheritdoc}
       */
      public function getDefinitions(): array {
        return $this->testDefinitions;
      }

    };
  }

  /**
   * Tests that a complete definition becomes a fully populated theme.
   */
  public function testValidDefinitionCreatesTheme(): void {
    $manager = $this->buildManager([
      'dark' => [
        'label' => 'Dark',
        'description' => 'A dark canvas with light strokes.',
        'libraries' => ['my_module/theme_base', 'my_module/theme_dark'],
        'owners' => ['eca'],
        'modelers' => ['workflow_modeler'],
        'provider' => 'my_module',
      ],
    ]);

    $themes = $manager->getAllThemes();

    $this->assertSame(['dark'], array_keys($themes));
    $theme = $themes['dark'];
    $this->assertSame('dark', $theme->getId());
    $this->assertSame('Dark', $theme->getLabel());
    $this->assertSame('A dark canvas with light strokes.', $theme->getDescription());
    $this->assertSame('my_module', $theme->getProvider());
    $this->assertSame(['my_module/theme_base', 'my_module/theme_dark'], $theme->getLibraries());
    $this->assertSame(['eca'], $theme->getOwners());
    $this->assertSame(['workflow_modeler'], $theme->getModelers());
  }

  /**
   * Tests that the optional keys default to empty values.
   */
  public function testMinimalDefinitionCreatesUnrestrictedTheme(): void {
    $manager = $this->buildManager([
      'plain' => [
        'label' => 'Plain',
        'libraries' => ['my_module/plain'],
      ],
    ]);

    $theme = $manager->getTheme('plain');

    $this->assertNotNull($theme);
    $this->assertSame('', $theme->getDescription());
    $this->assertSame('', $theme->getProvider());
    $this->assertSame([], $theme->getOwners());
    $this->assertSame([], $theme->getModelers());
    $this->assertTrue($theme->appliesTo('any_owner', 'any_modeler'));
  }

  /**
   * Tests that a definition without a label is skipped.
   */
  public function testDefinitionWithoutLabelIsSkipped(): void {
    $manager = $this->buildManager([
      'no_label' => [
        'libraries' => ['my_module/no_label'],
      ],
      'empty_label' => [
        'label' => '',
        'libraries' => ['my_module/empty_label'],
      ],
    ]);

    $this->assertSame([], $manager->getAllThemes());
    $this->assertNull($manager->getTheme('no_label'));
    $this->assertNull($manager->getTheme('empty_label'));
  }

  /**
   * Tests that a definition without any asset library is skipped.
   */
  public function testDefinitionWithoutLibrariesIsSkipped(): void {
    $manager = $this->buildManager([
      'no_libraries' => [
        'label' => 'No libraries',
      ],
      'empty_libraries' => [
        'label' => 'Empty libraries',
        'libraries' => [],
      ],
      'blank_libraries' => [
        'label' => 'Blank libraries',
        'libraries' => ['', ''],
      ],
    ]);

    $this->assertSame([], $manager->getAllThemes());
  }

  /**
   * Tests that a single library string is accepted as a shorthand.
   */
  public function testSingleLibraryStringIsNormalizedToList(): void {
    $manager = $this->buildManager([
      'shorthand' => [
        'label' => 'Shorthand',
        'libraries' => 'my_module/shorthand',
        'owners' => 'eca',
      ],
    ]);

    $theme = $manager->getTheme('shorthand');

    $this->assertNotNull($theme);
    $this->assertSame(['my_module/shorthand'], $theme->getLibraries());
    $this->assertSame(['eca'], $theme->getOwners());
  }

  /**
   * Tests that an unknown theme ID resolves to NULL rather than throwing.
   */
  public function testGetThemeReturnsNullForUnknownId(): void {
    $manager = $this->buildManager([
      'plain' => [
        'label' => 'Plain',
        'libraries' => ['my_module/plain'],
      ],
    ]);

    $this->assertNull($manager->getTheme('does_not_exist'));
  }

  /**
   * Tests that getThemesFor() only returns themes for the given combination.
   */
  public function testGetThemesForFiltersByOwnerAndModeler(): void {
    $manager = $this->buildManager([
      'everywhere' => [
        'label' => 'Everywhere',
        'libraries' => ['my_module/everywhere'],
      ],
      'eca_only' => [
        'label' => 'ECA only',
        'libraries' => ['my_module/eca_only'],
        'owners' => ['eca'],
      ],
      'workflow_only' => [
        'label' => 'Workflow Modeler only',
        'libraries' => ['my_module/workflow_only'],
        'modelers' => ['workflow_modeler'],
      ],
      'eca_in_workflow' => [
        'label' => 'ECA in the Workflow Modeler',
        'libraries' => ['my_module/eca_in_workflow'],
        'owners' => ['eca'],
        'modelers' => ['workflow_modeler'],
      ],
      'invalid' => [
        'label' => 'Invalid',
      ],
    ]);

    $this->assertSame(
      ['everywhere', 'eca_only', 'workflow_only', 'eca_in_workflow'],
      array_keys($manager->getThemesFor('eca', 'workflow_modeler')),
    );
    $this->assertSame(
      ['everywhere', 'eca_only'],
      array_keys($manager->getThemesFor('eca', 'bpmn_io')),
    );
    $this->assertSame(
      ['everywhere', 'workflow_only'],
      array_keys($manager->getThemesFor('ai_agents', 'workflow_modeler')),
    );
    $this->assertSame(
      ['everywhere'],
      array_keys($manager->getThemesFor('ai_agents', 'bpmn_io')),
    );
  }

  /**
   * Tests that a reserved theme ID never becomes a theme.
   *
   * Both IDs are settings values with a meaning of their own, so a theme using
   * one of them could never be told apart from the option it shadows.
   */
  public function testReservedThemeIdsAreSkipped(): void {
    $manager = $this->buildManager([
      'auto' => [
        'label' => 'Auto',
        'libraries' => ['my_module/auto'],
        'owners' => ['eca'],
      ],
      'default' => [
        'label' => 'Default',
        'libraries' => ['my_module/default'],
        'owners' => ['eca'],
      ],
      'legit' => [
        'label' => 'Legit',
        'libraries' => ['my_module/legit'],
        'owners' => ['eca'],
      ],
    ]);

    $this->assertSame(['legit'], array_keys($manager->getAllThemes()));
    $this->assertNull($manager->getTheme('auto'));
    $this->assertNull($manager->getTheme('default'));
    $this->assertSame('legit', $manager->resolveTheme('eca', 'workflow_modeler')?->getId());
  }

  /**
   * Tests that the weight is read from the definition and defaults to zero.
   */
  public function testWeightIsReadFromDefinition(): void {
    $manager = $this->buildManager([
      'weighted' => [
        'label' => 'Weighted',
        'libraries' => ['my_module/weighted'],
        'weight' => -5,
      ],
      'weighted_as_string' => [
        'label' => 'Weighted as string',
        'libraries' => ['my_module/weighted_as_string'],
        'weight' => '7',
      ],
      'unweighted' => [
        'label' => 'Unweighted',
        'libraries' => ['my_module/unweighted'],
      ],
      'bogus_weight' => [
        'label' => 'Bogus weight',
        'libraries' => ['my_module/bogus_weight'],
        'weight' => 'heavy',
      ],
    ]);

    $this->assertSame(-5, $manager->getTheme('weighted')?->getWeight());
    $this->assertSame(7, $manager->getTheme('weighted_as_string')?->getWeight());
    $this->assertSame(0, $manager->getTheme('unweighted')?->getWeight());
    $this->assertSame(0, $manager->getTheme('bogus_weight')?->getWeight());
  }

  /**
   * Tests that the more specific theme wins the automatic selection.
   *
   * The definitions are ordered so that both discovery order and the
   * alphabetical tie-break would produce the other theme, leaving specificity
   * as the only reason for the expected result.
   */
  public function testResolveThemePrefersTheMoreSpecificTheme(): void {
    $manager = $this->buildManager([
      'a_owner_only' => [
        'label' => 'ECA anywhere',
        'libraries' => ['my_module/a_owner_only'],
        'owners' => ['eca'],
      ],
      'z_owner_and_modeler' => [
        'label' => 'ECA in the Workflow Modeler',
        'libraries' => ['my_module/z_owner_and_modeler'],
        'owners' => ['eca'],
        'modelers' => ['workflow_modeler'],
      ],
    ]);

    $this->assertSame('z_owner_and_modeler', $manager->resolveTheme('eca', 'workflow_modeler')?->getId());
    // For another modeler the specific theme does not apply at all, so the
    // less specific one is the only candidate left.
    $this->assertSame('a_owner_only', $manager->resolveTheme('eca', 'bpmn_io')?->getId());
  }

  /**
   * Tests that the lowest weight wins within the same specificity.
   *
   * Both discovery order and the alphabetical tie-break would produce the
   * heavier theme, so only the weight can explain the expected result.
   */
  public function testResolveThemeOrdersByWeightWithinTheSameTier(): void {
    $manager = $this->buildManager([
      'a_heavy' => [
        'label' => 'Heavy',
        'libraries' => ['my_module/a_heavy'],
        'owners' => ['eca'],
        'weight' => 10,
      ],
      'z_light' => [
        'label' => 'Light',
        'libraries' => ['my_module/z_light'],
        'owners' => ['eca'],
        'weight' => -5,
      ],
    ]);

    $this->assertSame('z_light', $manager->resolveTheme('eca', 'workflow_modeler')?->getId());
  }

  /**
   * Tests that the theme ID breaks a tie between equally weighted themes.
   *
   * The definitions are in reverse alphabetical order, so a result following
   * discovery order would be the other theme. Discovery order is deliberately
   * not part of the contract, because it follows module weight.
   */
  public function testResolveThemeBreaksTieByThemeId(): void {
    $manager = $this->buildManager([
      'zulu' => [
        'label' => 'Zulu',
        'libraries' => ['my_module/zulu'],
        'owners' => ['eca'],
      ],
      'alpha' => [
        'label' => 'Alpha',
        'libraries' => ['my_module/alpha'],
        'owners' => ['eca'],
      ],
    ]);

    $this->assertSame('alpha', $manager->resolveTheme('eca', 'workflow_modeler')?->getId());
  }

  /**
   * Tests that a theme without any restriction is never selected on its own.
   *
   * Such a theme applies everywhere by design. Picking it automatically would
   * let any module change the styling of every canvas on the site merely by
   * being installed, so it stays available as an explicit choice only, even
   * when its weight would put it first.
   */
  public function testResolveThemeIgnoresUnrestrictedThemes(): void {
    $manager = $this->buildManager([
      'everywhere' => [
        'label' => 'Everywhere',
        'libraries' => ['my_module/everywhere'],
        'weight' => -100,
      ],
      'eca_only' => [
        'label' => 'ECA only',
        'libraries' => ['my_module/eca_only'],
        'owners' => ['eca'],
        'weight' => 100,
      ],
    ]);

    $this->assertSame('eca_only', $manager->resolveTheme('eca', 'workflow_modeler')?->getId());
    // The unrestricted theme is still offered in the settings form.
    $this->assertSame(
      ['everywhere', 'eca_only'],
      array_keys($manager->getThemesFor('eca', 'workflow_modeler')),
    );
    // And it is the only theme left for a combination the other one does not
    // apply to, yet it is still not selected automatically.
    $this->assertSame(
      ['everywhere'],
      array_keys($manager->getThemesFor('ai_agents', 'workflow_modeler')),
    );
    $this->assertNull($manager->resolveTheme('ai_agents', 'workflow_modeler'));
  }

  /**
   * Tests that a theme built for another combination is not selected.
   */
  public function testResolveThemeSkipsNonMatchingThemes(): void {
    $manager = $this->buildManager([
      'ai_agents_only' => [
        'label' => 'AI Agents only',
        'libraries' => ['my_module/ai_agents_only'],
        'owners' => ['ai_agents'],
      ],
      'bpmn_io_only' => [
        'label' => 'One modeler only',
        'libraries' => ['my_module/bpmn_io_only'],
        'modelers' => ['bpmn_io'],
      ],
    ]);

    $this->assertNull($manager->resolveTheme('eca', 'workflow_modeler'));
    $this->assertSame('ai_agents_only', $manager->resolveTheme('ai_agents', 'workflow_modeler')?->getId());
    $this->assertSame('bpmn_io_only', $manager->resolveTheme('eca', 'bpmn_io')?->getId());
  }

  /**
   * Tests that nothing is selected when no theme qualifies at all.
   *
   * NULL is the signal for the caller to leave the modeler alone, which is the
   * same outcome as the explicit default option.
   */
  public function testResolveThemeReturnsNullWhenNothingQualifies(): void {
    $this->assertNull($this->buildManager([])->resolveTheme('eca', 'workflow_modeler'));

    $manager = $this->buildManager([
      'invalid' => [
        'label' => 'Invalid',
        'owners' => ['eca'],
      ],
    ]);
    $this->assertNull($manager->resolveTheme('eca', 'workflow_modeler'));
  }

}
