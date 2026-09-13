<?php

declare(strict_types=1);

namespace Drupal\Tests\modeler_api\Unit;

use Drupal\modeler_api\Theme;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the owner and modeler matching of a theme definition.
 *
 * A theme restricts itself to model owners and modelers through two
 * independent lists, and an empty list means "all". Getting that wrong in
 * either direction is invisible on the surface: a theme either silently
 * disappears from the settings form, or it gets offered for a combination
 * whose CSS it was never written for.
 */
#[CoversClass(Theme::class)]
#[Group('modeler_api')]
class ThemeTest extends UnitTestCase {

  /**
   * Builds a theme with the given owner and modeler restrictions.
   *
   * @param string[] $owners
   *   The model owner plugin IDs the theme is limited to.
   * @param string[] $modelers
   *   The modeler plugin IDs the theme is limited to.
   *
   * @return \Drupal\modeler_api\Theme
   *   The theme under test.
   */
  protected function buildTheme(array $owners, array $modelers): Theme {
    return new Theme(
      id: 'my_theme',
      label: 'My theme',
      description: '',
      provider: 'my_module',
      libraries: ['my_module/my_theme'],
      owners: $owners,
      modelers: $modelers,
    );
  }

  /**
   * Provides the owner/modeler matching matrix.
   *
   * @return array<string, array{string[], string[], string, string, bool}>
   *   Test cases with owner restrictions, modeler restrictions, the owner and
   *   modeler being checked, and the expected result.
   */
  public static function appliesToProvider(): array {
    return [
      'no restriction applies everywhere' => [[], [], 'eca', 'workflow_modeler', TRUE],
      'owner restriction matches' => [['eca'], [], 'eca', 'workflow_modeler', TRUE],
      'owner restriction does not match' => [['eca'], [], 'ai_agents', 'workflow_modeler', FALSE],
      'modeler restriction matches' => [[], ['workflow_modeler'], 'eca', 'workflow_modeler', TRUE],
      'modeler restriction does not match' => [[], ['workflow_modeler'], 'eca', 'bpmn_io', FALSE],
      'both restrictions match' => [['eca'], ['workflow_modeler'], 'eca', 'workflow_modeler', TRUE],
      'only the owner matches' => [['eca'], ['workflow_modeler'], 'eca', 'bpmn_io', FALSE],
      'only the modeler matches' => [['eca'], ['workflow_modeler'], 'ai_agents', 'workflow_modeler', FALSE],
      'neither matches' => [['eca'], ['workflow_modeler'], 'ai_agents', 'bpmn_io', FALSE],
      'any entry of a list matches' => [['eca', 'ai_agents'], [], 'ai_agents', 'bpmn_io', TRUE],
    ];
  }

  /**
   * Tests appliesTo() across the owner/modeler matching matrix.
   *
   * @param string[] $owners
   *   The model owner plugin IDs the theme is limited to.
   * @param string[] $modelers
   *   The modeler plugin IDs the theme is limited to.
   * @param string $ownerId
   *   The model owner plugin ID being checked.
   * @param string $modelerId
   *   The modeler plugin ID being checked.
   * @param bool $expected
   *   The expected result.
   */
  #[DataProvider('appliesToProvider')]
  public function testAppliesTo(array $owners, array $modelers, string $ownerId, string $modelerId, bool $expected): void {
    $this->assertSame($expected, $this->buildTheme($owners, $modelers)->appliesTo($ownerId, $modelerId));
  }

  /**
   * Tests that all properties are exposed through their getters.
   */
  public function testGetters(): void {
    $theme = new Theme(
      id: 'my_dark_theme',
      label: 'Dark',
      description: 'A dark canvas with light strokes.',
      provider: 'my_module',
      libraries: ['my_module/theme_base', 'my_module/theme_dark'],
      owners: ['eca'],
      modelers: ['workflow_modeler'],
      weight: -10,
    );

    $this->assertSame('my_dark_theme', $theme->getId());
    $this->assertSame('Dark', $theme->getLabel());
    $this->assertSame('A dark canvas with light strokes.', $theme->getDescription());
    $this->assertSame('my_module', $theme->getProvider());
    $this->assertSame(['my_module/theme_base', 'my_module/theme_dark'], $theme->getLibraries());
    $this->assertSame(['eca'], $theme->getOwners());
    $this->assertSame(['workflow_modeler'], $theme->getModelers());
    $this->assertSame(-10, $theme->getWeight());
  }

  /**
   * Tests that the weight is optional and defaults to zero.
   *
   * The parameter is appended to the constructor, so every existing call site
   * that does not know about it has to keep working.
   */
  public function testWeightDefaultsToZero(): void {
    $this->assertSame(0, $this->buildTheme(['eca'], [])->getWeight());
  }

}
