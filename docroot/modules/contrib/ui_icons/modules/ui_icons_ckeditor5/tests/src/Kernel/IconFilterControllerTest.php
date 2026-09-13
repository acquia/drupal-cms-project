<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_icons_ckeditor5\Kernel;

use Drupal\Core\Theme\Icon\IconDefinition;
use Drupal\KernelTests\KernelTestBase;
use Drupal\ui_icons_ckeditor5\Controller\IconFilterController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Test the IconFilterController class.
 *
 * @internal
 */
#[CoversClass(IconFilterController::class)]
#[Group('ui_icons')]
class IconFilterControllerTest extends KernelTestBase {

  /**
   * Icon pack from ui_icons_test module.
   */
  private const TEST_ICON_PACK_ID = 'test_path';

  /**
   * Icon from ui_icons_test module.
   */
  private const TEST_ICON_ID = 'foo';

  /**
   * Icon filename from ui_icons_test module.
   */
  private const TEST_ICON_FILENAME = 'foo.png';

  /**
   * Icon class from ui_icons_test module.
   */
  private const TEST_ICON_CLASS = 'foo';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'ui_icons',
    'ui_icons_ckeditor5',
    'ui_icons_test',
  ];

  /**
   * The IconFilterController instance.
   *
   * @var \Drupal\ui_icons_ckeditor5\Controller\IconFilterController
   */
  protected $controller;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['system', 'ui_icons']);

    $this->controller = new IconFilterController(
      $this->container->get('renderer')
    );
  }

  /**
   * Test the preview method.
   */
  public function testPreview(): void {
    $icon_id = IconDefinition::createIconId(self::TEST_ICON_PACK_ID, self::TEST_ICON_ID);
    // Test case 1: Valid icon request.
    $request = new Request(['icon_id' => $icon_id]);
    $response = $this->controller->preview($request);
    $this->assertEquals(200, $response->getStatusCode());
    $this->assertStringContainsString('<img', $response->getContent());
    $this->assertStringContainsString(self::TEST_ICON_CLASS, $response->getContent());
    $this->assertStringContainsString(self::TEST_ICON_FILENAME, $response->getContent());
    // Test case 2: Valid icon request with settings.
    $settings = json_encode(['width' => 100, 'height' => 100]);
    $request = new Request([
      'icon_id' => $icon_id,
      'settings' => $settings,
    ]);
    $response = $this->controller->preview($request);
    $this->assertEquals(200, $response->getStatusCode());
    $this->assertStringContainsString('<img', $response->getContent());
    $this->assertStringContainsString('width="100"', $response->getContent());
    $this->assertStringContainsString('height="100"', $response->getContent());
    // Test case 3: Invalid icon ID.
    $request = new Request(['icon_id' => 'invalid:icon']);
    $response = $this->controller->preview($request);
    $this->assertEquals(404, $response->getStatusCode());
    // Test case 4: Missing icon ID.
    $request = new Request();
    $this->expectException(NotFoundHttpException::class);
    $this->controller->preview($request);
  }

}
